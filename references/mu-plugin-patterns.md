# MU-plugin patterns — surviving upstream-plugin misbehavior

Must-use (mu) plugins live in `wp-content/mu-plugins/` and auto-load on every request. WordPress provides no UI to deactivate them — which makes them ideal for **patching upstream-plugin misbehavior** that can't be fixed cleanly via filters / actions.

This file documents reusable patterns. For the "what is a mu-plugin" basics, see WordPress documentation. This is the patterns-only file.

## When mu-plugins are the right tool

✅ Upstream plugin has buggy / spammy behavior + maintainer won't fix (or hasn't released a fix yet)
✅ You can't modify the plugin file directly (would be overwritten on next update)
✅ The fix is needed on EVERY page load (regular Code Snippets fine too, but mu-plugin loads earlier + can't be accidentally deactivated)
✅ Cross-plugin compatibility shim (Astra Free + Elementor Theme Builder bridge — see [`astra-customizer.md`](astra-customizer.md))

❌ Site-specific CSS overrides — use Code Snippets or kit `custom_css`
❌ One-time admin task — use Code Snippets "Run Once" mode
❌ Anything you'd want to test/disable from wp-admin — mu-plugins can't be toggled there

## Pattern 1 — Suppress an anonymous Closure registered by upstream

**The trap**: WordPress `remove_action()` requires a reference equal to what was added. When the upstream plugin registered the callback via an **anonymous Closure**, there's no reference you can pass to remove it:

```php
// Upstream plugin somewhere:
add_action( 'init', function () {
    error_log( 'spam every page load' );
}, 20 );

// Your mu-plugin trying to remove it — FAILS:
remove_action( 'init', /* what goes here?? */, 20 );
// remove_action requires an exact match — anonymous Closures can't be matched.
```

The closure is in `$wp_filter['init']->callbacks[20]` keyed by a generated hash. You can find + unset it directly via reflection on the source file:

```php
// wp-content/mu-plugins/suppress-rogue-callback.php
<?php
/**
 * Suppress anonymous Closure registered by <upstream-plugin> on <hook> at priority <N>.
 *
 * Standard remove_action() cannot remove Closures (no reference to pass).
 * Hook at priority N-1, inspect $wp_filter, match closure source file via
 * ReflectionFunction::getFileName(), unset matching keys.
 */
add_action( 'init', function () {
    global $wp_filter;

    $hook     = 'init';
    $priority = 20;
    $needle   = 'wordpress-wae/includes/class-mcp-adapter.php';  // path fragment of the offending plugin

    if ( ! isset( $wp_filter[ $hook ]->callbacks[ $priority ] ) ) {
        return;
    }

    foreach ( $wp_filter[ $hook ]->callbacks[ $priority ] as $key => $cb ) {
        $fn = $cb['function'] ?? null;

        // Only inspect Closures (not [object, 'method'] callbacks)
        if ( ! ( $fn instanceof \Closure ) ) {
            continue;
        }

        try {
            $reflection = new \ReflectionFunction( $fn );
            $source     = $reflection->getFileName();

            if ( $source && strpos( $source, $needle ) !== false ) {
                unset( $wp_filter[ $hook ]->callbacks[ $priority ][ $key ] );
                // Optional: log that we suppressed it (once per request)
                error_log( "Suppressed closure from $source on $hook priority $priority" );
            }
        } catch ( \ReflectionException $e ) {
            // Reflection failed — skip this callback
        }
    }
}, 19 ); // priority 19 = run BEFORE priority 20 where the target lives
```

**Why this works**:
- `ReflectionFunction::getFileName()` returns the absolute path of the file where the Closure was defined
- Matching by path fragment is robust: survives plugin updates as long as the file path stays similar
- Hook at priority `N-1` so you inspect + unset BEFORE the target callback runs at `N`
- MU-plugins auto-load → no accidental deactivation

**Real use case**: `mcp-wp-capabilities` v1.0.0 left two `error_log()` calls inside its `mcp_adapter_init` action at priority 20, no debug flag to disable. Spammed ~5KB per page load, log grew to 29MB+ in days. This mu-plugin pattern suppressed the spam without modifying the upstream plugin file. Survived plugin updates.

### Variants

**Suppress a NAMED callback** that's still hard to remove (e.g. class instance not available globally):
```php
foreach ( $wp_filter[ $hook ]->callbacks[ $priority ] as $key => $cb ) {
    $fn = $cb['function'] ?? null;
    // Match by class name + method
    if ( is_array( $fn ) && is_object( $fn[0] ) && get_class( $fn[0] ) === 'TargetPlugin\Class' && $fn[1] === 'target_method' ) {
        unset( $wp_filter[ $hook ]->callbacks[ $priority ][ $key ] );
    }
}
```

**Suppress all callbacks on a hook from one plugin**:
```php
foreach ( $wp_filter[ $hook ]->callbacks as $priority => $callbacks ) {
    foreach ( $callbacks as $key => $cb ) {
        $fn = $cb['function'] ?? null;
        $source = null;
        if ( $fn instanceof \Closure ) {
            $source = ( new \ReflectionFunction( $fn ) )->getFileName();
        } elseif ( is_array( $fn ) && is_object( $fn[0] ) ) {
            $source = ( new \ReflectionClass( $fn[0] ) )->getFileName();
        }
        if ( $source && strpos( $source, $needle ) !== false ) {
            unset( $wp_filter[ $hook ]->callbacks[ $priority ][ $key ] );
        }
    }
}
```

## Pattern 2 — Conditional override (only when target plugin is active)

```php
// wp-content/mu-plugins/elementor-pro-shim.php
<?php
if ( ! defined( 'ELEMENTOR_PRO_VERSION' ) ) {
    return;  // Elementor Pro not active — skip the patch entirely
}

// Now apply the patch
add_filter( 'some_filter', function () { /* ... */ } );
```

Don't fire patches blindly. Check the target plugin is actually loaded; otherwise the mu-plugin can cause obscure errors when the target is deactivated.

## Pattern 3 — Bridge between two plugins

Astra Free does not auto-suppress its header when an Elementor Theme Builder template is active. Bridge via mu-plugin — see [`references/astra-customizer.md`](astra-customizer.md) "Astra Free + Elementor Theme Builder bridge" for the full recipe. The pattern:

1. Check both plugins are loaded
2. Remove the Astra default behavior via filter
3. Inject the Elementor location via `wp_body_open` / `astra_footer` action

## Pattern 4 — Polyfill for a missing core function

When upstream code references a function that exists in newer WP versions but not older ones:

```php
// wp-content/mu-plugins/polyfill-wp-69.php
<?php
if ( ! function_exists( 'wp_register_ability' ) ) {
    function wp_register_ability( $name, $args = [] ) {
        // ... shim implementation ...
    }
}
```

Use sparingly — usually it's better to upgrade WordPress core than to maintain a polyfill long-term.

## Pattern 5 — Force a setting (override a constant or option)

```php
// wp-content/mu-plugins/force-disable-something.php
<?php
add_filter( 'pre_option_some_option_name', function () {
    return 'forced-value';  // overrides DB value before it's read
});
```

`pre_option_<name>` short-circuits the option lookup — useful when the wp-admin UI keeps re-enabling a setting (e.g. WC Coming Soon, see [`pitfalls.md`](pitfalls.md)).

## File naming + organization

```
wp-content/mu-plugins/
├── _site-config.php              # main project shim file
├── suppress-rogue-callback.php   # one suppression per file (easier to disable)
├── elementor-bridge.php          # cross-plugin bridges
└── abilities-show-in-rest.php    # this stack's WAE compatibility patch
```

Conventions:
- One concern per file → easy to rename `.php` → `.php.disabled` if needed for emergency rollback
- Prefix with `_` for the main config file → sorts to top
- Keep files small (<200 lines each) — mu-plugins are runtime-loaded on every request, parsing time matters

## Anti-patterns

❌ **Modifying `wp-includes/` or `wp-admin/` core files** — gets overwritten on every WordPress update. Use mu-plugins instead.

❌ **Editing the upstream plugin's PHP files directly** — gets overwritten on next plugin update. Use a mu-plugin that hooks earlier.

❌ **Putting site-specific business logic in mu-plugins** — mu-plugins should be infrastructure / compatibility / patches. Business logic belongs in a child theme or a custom plugin.

❌ **Adding `*.php` files to mu-plugins root that you forget about** — every file auto-loads. Six months later when something breaks, you've lost track of why a closure on `init` is firing. Document each file with a comment header explaining what it does + why.

❌ **Using a mu-plugin when a filter would work** — filters are more discoverable + can be removed with `remove_filter()`. Only reach for mu-plugin patterns when filters fail (e.g. Closure suppression, force-override against a stubborn upstream).

## Cross-references

- [`references/astra-customizer.md`](astra-customizer.md) — concrete mu-plugin bridge for Astra Free + Elementor Theme Builder
- [`references/pitfalls.md`](pitfalls.md) — when `remove_action()` failure is the symptom, this pattern is the fix
- [`references/wp-abilities.md`](wp-abilities.md) — the `abilities-show-in-rest.php` mu-plugin uses the same reflection pattern in a different shape
- [`references/security.md`](security.md) — mu-plugins run with full WordPress permissions — apply same review discipline as core
