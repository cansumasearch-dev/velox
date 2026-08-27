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

This repo is where I develop Velox. If you just want to run it, install a
release rather than cloning this.

The repo root **is** the plugin root — `velox.php` sits at the top level. That
is not incidental: `.github/workflows/release.yml` copies the repo root into a
folder named `velox/` and zips it, so the archive unpacks to
`wp-content/plugins/velox/`. Moving the plugin files into a subfolder would
break the release build.

```
velox.php            plugin header, version constant, the include list
uninstall.php        what gets removed when the plugin is deleted
includes/            one class per module (class-velox-<module>.php)
  lang/de_DE.php     German dictionary, applied via the gettext filter
admin/
  views/             admin screens; views/utilities/<tool>.php per tool
  js/  css/          admin assets
assets/              front-end assets
bin/
  build.sh           checks, then builds build/velox-<version>.zip
  check-i18n.php     fails on any user-facing string with no German entry
build/               build output — gitignored, never shipped
```

### Building

```bash
bin/build.sh
```

It reads the version out of `velox.php` rather than taking it as an argument,
refuses to build if the `Version:` header and `VELOX_VERSION` disagree, runs
the PHP, JS and translation checks as gates, and reads the version back out of
the finished zip. It exits non-zero on any failure, so it is safe to chain.

### Releasing

Bump the version in **both** places in `velox.php`, add a `CHANGELOG.md` entry
at the top, commit, then push a tag:

```bash
git tag v4.8.0 && git push origin v4.8.0
```

The tag fires the release workflow, which builds the zip and publishes it.
Every install polls `releases/latest`, so the tag is what ships the update to
every site — push it deliberately.
