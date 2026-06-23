# Velox

Velox is a WordPress speed plugin I built for a fairly specific setup and then kept growing into something general. The short version: it makes a site faster without piling a second cache on top of the one you already run, and without combining your CSS and JS into one file (which wrecks visual builders). It picks up the optimisation work your cache plugin and CDN leave on the table.

## Get it

Install it from the WordPress plugin directory — **https://wordpress.org/plugins/velox** — or just search "Velox" under **Plugins → Add New** in your dashboard and hit install. That's the place to download it and where updates come from.

## What it does

- **Images** — convert the whole media library to WebP, strip EXIF, shrink oversized uploads, and show you what you saved.
- **Media editing** — rename a file and it fixes every reference to it across your posts and meta so nothing breaks. Bulk alt text and titles too.
- **Performance** — defer JavaScript, optionally delay it, lazy-load below-the-fold images while leaving the hero image alone, and clear out front-end junk (emojis, embeds, query strings, dashicons…).
- **Unused CSS** — strip the CSS a page never uses, with a learning mode that keeps classes JavaScript adds after load.
- **Fonts** — host your Google Fonts locally instead of loading them from Google every visit.
- **Database** — clean revisions, auto-drafts, trash, stale transients and orphaned meta, on a weekly schedule if you want.
- **Per-page switches** — turn any feature off on a single page when something acts up.

The builder-aware part is the bit I'm most into: open it once, it works out whether you're on Oxygen, Bricks, Elementor, Divi, Beaver Builder, WPBakery, a block theme or nothing, and sets itself up for that — including the guardrails each one needs so it doesn't break your sliders, menus or animations.

Anything that can actually break a site sits behind a single "Risky mode" toggle.

## Development

This repo is where I develop Velox. Released versions live on the WordPress plugin directory; if you just want to run it, install it from there rather than cloning this.
