# Astra Customizer — Settings hay dùng

Astra Free có Customizer rất sâu. Brand-specific (font, màu cụ thể) đọc từ CLAUDE.md project. File này chỉ chỉ ra path nào hay dùng.

## Global

`Customize → Global → Typography`
- Body font: từ CLAUDE.md, weight 400, size 16/16/16
- Headings font: từ CLAUDE.md, weight 700
- Line height body: 1.6
- Line height heading: 1.2

`Customize → Global → Colors`
- Theme color (link): primary brand color
- Link hover: darker shade
- Heading color: text-primary
- Body text: text-primary
- Background: background

`Customize → Global → Container`
- Container width: 1280px
- Container layout: Boxed (mặc định)

`Customize → Global → Buttons`
- Button radius: 8px
- Button padding: 16/32px
- Button typography: brand font weight 600

## Header

`Customize → Header Builder`
- Layout: Logo trái, Menu phải, CTA button bên phải
- Sticky on scroll: ON
- Transparent on hero: tùy site
- Mobile breakpoint: 1024px (Astra default 921 hơi sớm)

## Footer

`Customize → Footer Builder`
- Layout: 4 cột desktop, 2 cột tablet, 1 cột mobile
- Background: dark mode tùy brand

## Performance

`Customize → Performance`
- Load Google Fonts Locally: ON (giảm 1 external request)
- Preload Local Fonts: ON cho font chính
- Disable Block Editor styles: ON nếu không dùng Gutenberg

## Layout

`Customize → Layout → Sidebar`
- Default Layout: No Sidebar (cho mọi site marketing)
- Sidebar Style: Unboxed (nếu cần sidebar cho blog)

`Customize → Layout → Blog`
- Blog Layout: Grid hoặc Classic
- Posts per page: 9 (chia hết 3 cột)
- Excerpt length: 25 words

## Astra MCP (mới có từ v4.13)

Nếu connect Astra MCP, Claude điều khiển được hết settings trên qua natural language. 2 endpoint:
- `/wp-json/astra/v1/mcp` — chỉ Astra theme
- WordPress.com global MCP — toàn site

Setup: Plugins → Astra → MCP tab → Generate config

⚠️ Astra MCP **không build được landing page sections** — chỉ chỉnh theme settings global. Page content vẫn dùng msrbuilds/elementor-mcp.

## Bẫy Astra hay gặp

### Mobile breakpoint sớm (921px)
Mặc định Astra coi tablet là <922px → Customize → Layout → Container → Mobile breakpoint: 768.

### Cache local font thiếu Vietnamese
Sau khi bật Load Google Fonts Locally, có thể thiếu Vietnamese subset.
Fix: Astra → Performance → Flush local font cache → re-load page → check font-family.

### Header transparent + Elementor hero conflict
Header transparent on hero không tự auto detect hero section. Phải set per-page: Page Settings → Header Style → Transparent.

### Astra schema vs Rank Math schema duplicate
Disable Astra schema, giữ Rank Math.
