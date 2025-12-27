# Branding Assets for WordPress.org

WordPress.org requires specific banner and icon images for your plugin listing. These make your plugin look professional and stand out in the directory.

## Required Assets

### Plugin Icon

| File | Dimensions | Purpose |
|------|------------|---------|
| `icon-128x128.png` | 128 x 128 px | Standard resolution icon |
| `icon-256x256.png` | 256 x 256 px | Retina/high-DPI icon |
| `icon.svg` | N/A | Optional vector icon (preferred) |

### Plugin Banner

| File | Dimensions | Purpose |
|------|------------|---------|
| `banner-772x250.png` | 772 x 250 px | Standard resolution banner |
| `banner-1544x500.png` | 1544 x 500 px | Retina/high-DPI banner |

## Design Guidelines

### Icon Design

- **Style**: Simple, recognizable, works at small sizes
- **Colors**: Use brand colors consistently
- **Content**: Avoid text (illegible at small sizes)
- **Suggested concept**: Clock/timer icon with refresh arrows, or document with checkmark/freshness indicator

### Banner Design

- **Style**: Clean, modern, professional
- **Colors**: Complementary color scheme
- **Content**: Plugin name, optional tagline, relevant imagery
- **Suggested elements**:
  - "Content Freshness Monitor" text
  - Tagline: "Keep Your Content Fresh"
  - Visual: Content health gauge, calendar, or document icons
  - Color scheme: Fresh greens and clean whites

## Color Palette Suggestions

Based on the plugin's CSS styling:

| Color | Hex | Usage |
|-------|-----|-------|
| Fresh Green | `#46b450` | Primary brand color, "fresh" state |
| Aging Yellow | `#f0ad4e` | Warning state |
| Stale Red | `#dc3232` | Attention/stale state |
| Clean White | `#ffffff` | Background |
| Dark Gray | `#23282d` | Text |

## SVN Location

When uploading to WordPress.org SVN, assets go in the `/assets/` directory (root level, not inside your plugin folder):

```
your-plugin/
├── assets/                    ← WordPress.org assets folder
│   ├── banner-772x250.png
│   ├── banner-1544x500.png
│   ├── icon-128x128.png
│   ├── icon-256x256.png
│   ├── icon.svg              (optional)
│   ├── screenshot-1.png
│   └── ...
└── trunk/                     ← Plugin code folder
    ├── content-freshness-monitor.php
    ├── readme.txt
    └── ...
```

## Design Tools

Create assets with:
- [Figma](https://figma.com) (free, web-based)
- [Canva](https://canva.com) (free, templates available)
- Adobe Illustrator/Photoshop
- [GIMP](https://gimp.org) (free)
- [Inkscape](https://inkscape.org) (free, for SVG)

## Icon Concept Ideas

1. **Document with Clock**
   - Document icon with small clock overlay
   - Suggests content + time monitoring

2. **Circular Gauge**
   - Health meter style gauge
   - Pointer indicating "fresh" zone in green

3. **Refresh Calendar**
   - Calendar page with refresh/sync arrows
   - Represents content freshness tracking

4. **Checkmark Document**
   - Document with checkmark or status indicator
   - Green for fresh, red for stale

5. **Leaf/Growth Symbol**
   - Fresh leaf or sprout
   - Represents "freshness" metaphorically

## Banner Concept Ideas

1. **Dashboard Preview**
   - Stylized version of the plugin dashboard
   - Shows health score prominently

2. **Before/After**
   - Left side: Stale, dusty content icons
   - Right side: Fresh, vibrant content icons
   - Plugin name in center

3. **Gradient Health Bar**
   - Flowing gradient from red (stale) to green (fresh)
   - Plugin name overlaid

4. **Content Timeline**
   - Visual timeline showing content aging
   - Plugin name with tagline

## Technical Specifications

### PNG Requirements
- 24-bit color depth
- RGB color space
- Optimized file size (under 250KB)

### SVG Requirements (for icon)
- Clean, optimized paths
- No embedded raster images
- Viewbox attribute set correctly

## File Checklist

Before WordPress.org submission, ensure you have:

- [ ] `icon-128x128.png` (128x128, <50KB)
- [ ] `icon-256x256.png` (256x256, <100KB)
- [ ] `icon.svg` (optional, vector)
- [ ] `banner-772x250.png` (772x250, <150KB)
- [ ] `banner-1544x500.png` (1544x500, <250KB)

---

**Note**: WordPress.org will display a generic placeholder if these assets are missing. A well-designed icon and banner significantly improve plugin visibility and perceived quality.
