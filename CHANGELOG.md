# Changelog

All notable changes to Velox. This file is the single source of truth — it shows
up both on the GitHub release and in the WordPress "View details" → Changelog tab.
Add a new section at the top for each release.

## 1.10.0
- New **Utilities** section: a hub for site and admin tools, each off by default and only loaded when you switch it on.
- **SVG uploads** — allow SVG in the media library, sanitised on upload so they can't carry scripts.
- **Duplicate post/page** — one-click "Duplicate" link on every post and page, clones it as a draft.
- Reworked the layout onto a Bootstrap-style container so content uses the full width cleanly.

## 1.9.3
- The setup wizard now stays closed once you skip or dismiss it; it only reopens when you open it yourself.
- Rewrote the plugin description (README + readme) in a plainer, more human voice.

## 1.9.2
- Moved the Velox admin menu down next to the other plugin menus, so WordPress's core/plugin divider no longer leaves a gap right under it.

## 1.9.1
- Sized and centred the Velox icon: 20px in the admin toolbar, 25px in the left admin menu.

## 1.9.0
- **Builder-aware setup wizard.** Velox now detects your page builder — Oxygen, Bricks, Elementor, Divi, Beaver Builder, WPBakery, Gutenberg/block themes, or none — and auto-configures the right JS exclusions, unused-CSS safelist and guardrails for it. A quick wizard runs on first launch (and can be re-run any time from Settings).
- **Per-builder guardrails:** keeps jQuery Migrate on Divi/Elementor/WPBakery/Beaver, disables YouTube facades on Divi, and never strips block CSS on block themes — the things that would otherwise break each builder.
- **Live builder switching:** change builders and Velox flags it on every screen, wipes the old performance settings, and reconfigures for the new one (your image/font/database settings are kept).
- **"Request my builder"** button emails us to add any builder that isn't listed yet.
- The plugin icon in the admin menu and toolbar is now the Velox logo.

## 1.8.1
- **Cache buttons now confirm what happened** — "All caches purged", "Cloudflare purged", or a clear "Error: …" telling you exactly what's missing (e.g. Cloudflare plugin not active).
- **Tested up to WordPress 7.0** — clears the "hasn't been tested with your version" warning.
- Rewrote the description, installation, FAQ and README to be clearer, friendlier and more useful, with more questions answered.

## 1.8.0
- **Per-page overrides:** a "Velox" box in the post/page editor to switch off JS, CSS or lazy-load (or everything) on a single page.
- **Quick-setup presets:** one-click "Safe defaults" and "Aggressive" buttons in Settings.
- **Live dashboard:** real status for CSS pages optimized, fonts hosted, DB rows cleanable and WebP engine.
- **Exclude first N images from lazy-load** so the hero/LCP image always loads eagerly.
- **WordPress 6.9 compatibility:** hardened defer/delay so bundled inline translation scripts no longer break script handling.
- **Smoother updates:** a single "Check again" now bypasses the release cache.
- **Thorough uninstall:** removes all Velox options, auto-learn data, per-page meta and generated cache folders (your media and WebP files are left untouched).

## 1.7.2
- Added plugin icon and banner artwork to the "View details" popup and the update notice.

## 1.7.1
- Richer "View details" popup: full description, installation steps, FAQ and a properly formatted changelog.
- Changelog now reads from this `CHANGELOG.md` so it stays in sync everywhere.

## 1.7.0
- **Auto-learn used-CSS engine** — zero setup. Learns the real classes from your visitors' browsers (so it sees JS-added classes) and trims the rest. Can only ever keep more CSS, never break a layout.
- Used-CSS engine selector: Auto-learn (default) · Cloudflare Browser Run · Local.
- Live status and "Reset auto-learn" control in the CSS tab.

## 1.6.0
- **Cloudflare Browser Run engine** for accurate, JS-aware unused-CSS removal with no software to install — renders each page via Cloudflare's API and trims against the real DOM.
- Scan & build workflow with per-page caching.

## 1.5.0
- **Strong CSS optimizations** (Risky mode): non-render-blocking CSS delivery, inline critical CSS, and a conservative local Remove-Unused-CSS that fails open.

## 1.4.0
- **Local Google Fonts optimizer** — one-click download + self-hosting of Google Fonts.
- Settings import / export.

## 1.3.0
- **Image pipeline:** preserve-EXIF toggle, max-width downscaling, and live before→after file-size estimates on every image.

## 1.2.0
- **Performance overhaul:** Risky-mode toggle, in-tab Clear Cache panel, fetchpriority on the LCP image, YouTube facade, lazy-render, configurable delay-JS timeout, and exposed defer/delay exclusion lists.

## 1.1.2
- UI/UX overhaul: wider layouts, consistent cards, fully styled Media Editor, clearer navigation.

## 1.1.1
- Fixed settings persistence (saves now stick) and a one-time heal for previously corrupted settings.
- Admin-bar cache menu gated to the Performance module. Simplified the Updates panel.

## 1.0.0
- Initial release: WebP conversion, media editor, performance tweaks, database cleanup, and the GitHub self-updater.
