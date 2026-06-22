# Velox

**The WordPress speed toolkit that works *with* your stack, not against it.**

Velox is a performance, image and media plugin built for one specific setup — **Oxygen + WP Fastest Cache + Cloudflare** — and the whole idea is that it *complements* those tools instead of fighting them. No second page cache. No CSS/JS combining (which breaks Oxygen anyway). Just the optimizations your cache plugin and CDN don't already cover, with everything risky tucked behind a single switch so you can't blow up your own site by accident.

---

## Why I built it

Every "all-in-one" speed plugin I tried did one of three things: clashed with WP Fastest Cache, broke Oxygen the moment I turned on CSS combining, or drowned me in 200 toggles I didn't understand. So I built the one I actually wanted to use on agency sites — small, honest about what it does, and safe by default.

If you run a different stack, Velox still works — but it's happiest on the one above.

---

## What's inside

**Performance**
Defer & delay JavaScript (with Oxygen/jQuery-aware exclusions), prioritise the hero image for a faster LCP, swap heavy YouTube embeds for click-to-load facades, lazy-render offscreen sections, add Speculation Rules, and switch off a long list of WordPress bloat (emojis, embeds, dashicons, XML-RPC, heartbeat, query strings, and more). A single **Risky mode** switch keeps the aggressive stuff hidden until you want it.

**Smart CSS**
Non-render-blocking CSS delivery, inline critical CSS, and **Remove Unused CSS** with three engines you can pick from:

- **Auto-learn** *(default, zero setup)* — learns which CSS your pages actually use by measuring it in your real visitors' browsers. Because it's a real browser, it sees classes added by JavaScript too. The key safety property: it can only ever keep **more** CSS, never less, so it physically cannot break your layout.
- **Cloudflare** — renders each page through Cloudflare Browser Run for accurate, JS-aware trimming from day one. Optional; needs a free Cloudflare token.
- **Local** — instant, reads the server HTML. Fast but blind to JS-added classes (use the safelist).

**Fonts**
Pull your Google Fonts local in one click — faster, no third-party request, and GDPR-friendly.

**Images**
Bulk-convert JPG/PNG to WebP with live before→after savings on every file, strip EXIF, and downscale oversized images to a max width. Your originals are kept.

**Media editor**
Set alt text and titles in a grid, bulk-apply with a simple `filename | alt | title` format, and rename files safely (references get fixed).

**Database**
Clear revisions, auto-drafts, trash, spam, expired transients and orphaned meta — then optimise your tables. Optional weekly auto-clean.

**Per-page control**
A "Velox" box in the post/page editor lets you switch off JS, CSS or lazy-load — or all of Velox — on a single page when something acts up. The rest of your site stays optimised.

---

## Quick start

1. **Plugins → Add New → Upload Plugin**, drop in the zip, activate. (Updating? Choose *Replace current with uploaded* — your settings stick.)
2. Open **Velox → Settings → Quick setup** and hit **Apply safe defaults**. Done — that turns on everything that can't break a site.
3. Want more speed? Either flip **Risky mode** in Performance and turn things on one at a time, or hit **Apply aggressive preset** and then test your site, excluding anything that misbehaves.

That's genuinely it. The defaults are safe, so step 2 is enough for most sites.

---

## The CSS engines, explained

Removing unused CSS is the single biggest win for most WordPress sites — and the hardest to do without breaking things, because a lot of CSS is applied by JavaScript *after* the page loads. Here's how to choose:

| Engine | Setup | Accuracy | Best for |
|---|---|---|---|
| **Auto-learn** | None | Improves with traffic | Almost everyone. Safe by design. |
| **Cloudflare** | Free token | High, day one | Low-traffic sites, or instant results |
| **Local** | None | JS-blind | Simple sites, quick tests |

Most people should leave it on **Auto-learn** and forget about it.

---

## Updating

Velox updates itself straight from **GitHub Releases** — it never appears in the public wp.org directory, so it stays private to you and your clients. When you tag a new version, WordPress shows the normal "update available" notice and you update like any other plugin.

Releasing a new version (for maintainers):

```bash
# bump the Version: header in velox.php and add a CHANGELOG.md entry, then:
git add -A && git commit -m "x.y.z — what changed"
git push
git tag vX.Y.Z && git push origin vX.Y.Z   # the tag triggers the build + release
```

The `CHANGELOG.md` is the single source of truth — it shows up both on the GitHub release and in the WordPress "View details → Changelog" tab.

---

## FAQ

**Will it clash with WP Fastest Cache?**
No. Velox never page-caches or combines CSS/JS — that's WPFC's job. They're meant to run together.

**Do I need Cloudflare or an API key?**
No. The default CSS engine needs zero setup. Cloudflare is only an optional alternative.

**Is it safe on Oxygen?**
Yes — it was built on an Oxygen stack. No CSS/JS combining, and it leaves jQuery Migrate alone by default.

**Something broke on one page.**
Use the Velox box in that page's editor to switch off whatever's causing it, just for that page.

**What happens if I uninstall?**
Velox cleans up after itself — settings, learned data and cache folders all go. Your media and WebP files stay.

---

## Requirements

- WordPress 6.0+ (tested up to 7.0)
- PHP 7.4+
- Happiest on: Oxygen Classic, WP Fastest Cache, Cloudflare

---

## License

GPL-2.0-or-later. Built by [Sumasearch](https://www.sumasearch.de/).
