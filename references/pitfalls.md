# Pitfalls — Bẫy thường gặp toàn stack

## Elementor MCP (msrbuilds)

### 1. Settings format sai

❌ SAI: `add-heading(settings: {title: "Hello"})`
✅ ĐÚNG (flat): `add-heading(title: "Hello", header_size: "h1")`

Chỉ `add-container`, `update-element`, `update-widget` dùng `settings: {}`.

### 2. Typography không apply

Set `typography_font_size` mà không thấy đổi → thiếu:
```
typography_typography: "custom"
```

### 3. Background color không hiện

Thiếu:
```
background_background: "classic"
```

### 4. Element ID disappear sau edit

Element ID là 7 ký tự hex (vd `f8d1545`). LƯU lại sau mỗi add-*. Nếu mất, gọi `get-page-structure`.

### 5. Cache CSS cũ

Sau loạt edit, gọi `clear_elementor_cache(page_id: 123)`. Không clear → user thấy CSS cũ, tưởng MCP fail.

### 6. Pro widget trên Free

22 widget Pro chỉ chạy nếu Elementor Pro active. Trên Free → lỗi `widget_type_not_found`.

### 7. Elementor 4.0 Atomic Elements

MCP plugin v1.4 chưa support (issue #29). Nếu user dùng Elementor 4.0 → disable Atomic Elements ở Settings → Features hoặc downgrade 3.27.

### 8. Application Password label ≠ username

WordPress hiển thị "label" (vd "Claude MCP"). Nhưng auth dùng **login slug thực** (admin/email-slug). Sai → 401.

### 9. Concurrent edit

User edit page trong Elementor editor cùng lúc Claude Code MCP edit → conflict. User phải đóng Elementor editor trước khi MCP session bắt đầu.

### 10. Connection closed errors

Issue #27. Workaround:
- Restart `claude` session
- Verify endpoint: `curl https://<site>/wp-json/mcp/elementor-mcp-server`
- Check WordPress error log

## Astra

### 1. Cache local font thiếu Vietnamese
Xem vietnamese.md.

### 2. Mobile breakpoint sớm (921px)
Customize → Layout → Container → Mobile breakpoint: 768.

### 3. Header transparent + Elementor hero conflict
Set per-page: Page Settings → Header Style → Transparent.

## ACF / JetEngine

### 1. ACF field bind vào Elementor không update
Sau khi add ACF field mới:
- Save field group
- Reload Elementor editor (close + reopen, không phải refresh)
- Dynamic Tags dropdown mới hiện field

### 2. JetEngine Listing override Theme Builder
Cùng dùng JetEngine Listing và Elementor Theme Builder cho 1 CPT → conflict, JetEngine wins. Pick một.

## Rank Math

### 1. Sitemap không update sau publish
Tools → Database Tools → Update Schema → Update Sitemap.

### 2. Schema duplicate
Astra Local Business schema + Rank Math Local Business → duplicate. Disable Astra schema.

## WP Rocket

### 1. Combine CSS vỡ layout
Disable Combine CSS. HTTP/2 không cần.

### 2. Lazy load hero image gây LCP cao
Hero image: class `no-lazy` hoặc disable lazy load cho `.elementor-section:first-of-type img`.

### 3. Cache không clear sau MCP edit
WP Rocket cache page-level. Sau Claude Code edit:
- Settings → Cache → Clear Cache → All
- MCP `clear_elementor_cache` không đủ

## Cloudflare

### 1. Always Online cache phiên bản cũ
Disable nếu không cần. Hoặc Purge Everything sau deploy.

### 2. Mixed content sau migrate HTTP→HTTPS
SSL: Full (strict) + Auto HTTPS Rewrites ON + Always Use HTTPS ON.
DB còn URL http://: 
```bash
wp search-replace 'http://<site>' 'https://<site>' --skip-columns=guid
```

## Hosting (generic — chi tiết per provider trong deployment.md)

### Disk full do log
```bash
du -sh wp-content/debug.log
```
Nếu > 1GB: truncate hoặc disable WP_DEBUG_LOG production.

### SSL Let's Encrypt renew fail
Check DNS A record point đúng + port 80 mở.

### PHP memory limit thấp
wp-config: `define('WP_MEMORY_LIMIT', '512M');` + provider PHP settings tăng.
