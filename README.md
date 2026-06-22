# Velox

All-in-one WordPress performance, WebP optimization and media toolkit — built
for the **Oxygen Classic + WP Fastest Cache + Cloudflare** stack. It complements
your cache plugin instead of fighting it: no second page cache, no CSS/JS
combine (which breaks Oxygen), just the optimizations those tools *don't* cover.

White / black / `#2ab7f1`. iPhone-and-Nike clean. One consistent UI across every
tab.

## What's inside

- **Dashboard** — library stats at a glance, jump into any tool.
- **Images** — bulk JPG/PNG → WebP at a quality you choose, live progress, a
  before/after drag comparator, and a saved-bytes ring. Originals kept as
  fallback. Optional opt-in front-end WebP serving for browsers that support it.
- **Media** — give every image alt text, title and caption; **rename the actual
  file** with serialization-safe search/replace so nothing breaks in post
  content or Oxygen meta. Bulk import/export in `Dateiname | Alt-Text | Titel`
  format.
- **Performance** — Oxygen/WPFC-aware tweaks: clean `<head>`, kill emojis,
  defer scripts (with an exclusion list), DNS-prefetch/preconnect, heartbeat
  control, revision limits. All off by default; flip one at a time.
- **Database** — live counts for revisions, transients, orphaned meta, etc.
  Clean per-item or all at once, optimize tables, optional weekly auto-clean.
- **Settings** — master on/off per module, image defaults, and the self-updater.

## Updating & install

See **[UPDATING.md](UPDATING.md)** for the full step-by-step: installing the
zip, putting it on GitHub, public vs private, the read-only token, and the
bump-version → tag → auto-release loop that pushes updates to every install.

## Requirements

WordPress 6.0+, PHP 7.4+. WebP needs the Imagick **or** GD PHP extension with
WebP support (Velox shows which it found on the Dashboard).
