# Changelog

All notable changes to Velox. This file is the single source of truth — it shows
up both on the GitHub release and in the WordPress "View details" → Changelog tab.
Add a new section at the top for each release.

## 3.08.3 — Unused media: correct Used/Unused split
- The Used and Unused tabs are now complementary — every image lands in exactly one of them. Previously, loosely-referenced images showed in neither tab, which looked like used images leaking into the wrong place.
- Used tab now shows everything referenced anywhere; Unused shows only files with no reference at all (still conservative before deletion).

## 3.08.2 — Cookie banner editor: clean split layout
- Settings on the left as standard Velox cards; the live preview is pinned on the right so it stays visible while you scroll and edit.
- Uses the same panels, spacing and components as the rest of the plugin — no bespoke inspector, no tabs.
- Collapses to a single column (preview on top) on narrow screens.

## 3.08.1 — Cookie banner page now matches the rest of Velox
- Rebuilt the page as a single column of standard panels (like Settings and SEO) instead of the bespoke two-pane tabbed inspector that made it look like a different app.
- Live preview sits in a panel at the top; all settings stack below in normal Velox cards; one Save button at the bottom.
- Kept the visual placement picker and every control, all still wired to the live preview and save.

## 3.08.0 — Cookie banner editor redesign
- Rebuilt the editor around three clear tabs — Content, Design, Behaviour — matching the rest of Velox.
- Placement is now a visual picker (mini-diagrams of bar / floating box / modal) instead of a dropdown.
- Removed the Oxygen-style custom-layout controls (display/direction/justify/align/grid) — the footgun behind the banner gap; the banner now uses one clean, robust layout.
- Colours and Shape & size are now separate, focused sections; consent/tracking lives under Behaviour.

## 3.07.6 — Cookie banner: the gap is dead
- Root cause fixed: .vxck-main used flex:1 1 360px, and in a column layout the 360px became a minimum HEIGHT, forcing a huge empty gap between the text and the buttons/categories. Changed to flex:1 1 auto so it sizes to its content.
- Added a clean, gap-proof layout override on top: bars lay out horizontally (content left, buttons right, wrapping cleanly), boxes/modal stack tightly — the banner is always exactly the height of its content.

## 3.07.5 — Cookie banner: spacing, underline, and a display failsafe
- Fixed the big empty gap in the banner: content now packs together instead of spreading into large blank space (happened with vertical/column layouts and any spare height).
- Fixed the "underline links" toggle — turning it on now actually underlines the links (it was doing nothing because the base style was already no-underline).
- Added a first-load display failsafe: when a visitor has not chosen yet, the banner is forced visible even if a theme or optimiser tried to hide it.

## 3.07.4 — Cookie banner entrance animations + reliable first-load display
- Added an "Entrance animation" setting for the cookie banner: slide up from bottom (default), slide down from top, fade, zoom, slide from left/right, or none.
- Hardened first-load display: the banner's resting state is always visible, so the animation is only a nice entrance and never what makes the banner appear. Respects prefers-reduced-motion.
- Note: after enabling the banner, clear any page cache and delete a stale velox_consent cookie so a new visitor sees it.

## 3.07.3 — Live sitemap preview uses your real URLs
- The sitemap preview now shows your actual site — the real home, posts, pages and product URLs it will contain — instead of example.com placeholders, so it matches exactly what visitors see at /sitemap.xml (in both the plain and styled looks).
- The written sitemap and the preview now share one source of truth, so they can never drift. Large sites show the first 150 URLs with a "showing X of N" note.

## 3.07.2 — Sitemap appearance styles
- New "Sitemap appearance" picker: choose how sitemap.xml looks when opened in a browser. Classic (plain XML) stays the default — nothing changes unless you pick a style.
- Ready-made looks (Clean, Dark, Minimal) plus Custom (accent colour, heading text, show-logo). Velox generates the matching XSL stylesheet and the preview reflects the chosen look.
- Search engines still read the plain XML underneath, so styling has no SEO impact.

## 3.07.1 — Wider sitemap preview
- The sitemap live preview is no longer cramped in a narrow column. The options now sit in a compact bar on top and the preview spans the full width below, taller, with XML syntax colouring (tags, values and the declaration are coloured) for a clearer read.

## 3.07.0 — Reply composer
- Replying to a submission now opens a proper composer modal with a rich-text toolbar (bold, italic, underline, text colour, link, image, bullet/numbered lists). Images are picked from the Media Library so they display in inboxes.
- Saved reply templates: pick a template to pre-fill the reply instantly, or "Save as template" to store the current reply as a reusable canned response.
- Choose the sender when replying — your logged-in account address, or a custom address you type; the chosen address is used as the From.

## 3.06.15 — SMTP panel restyle
- Reworked the SMTP connections layout: the cramped, misaligned save/test row is now a clean Save action plus a dedicated "Test your setup" card with evenly-aligned controls (connection picker, Test connection, recipient, Send test).

## 3.06.14 — Form-name label + Media menu link
- The form-name box in the Mail & Forms editor now has a clear "Form name" label above it, so it is obvious what it is, and is properly styled.
- Added an "Optimize Images" entry under the WordPress Media menu (next to Library / Add Media File) that links to the Velox optimizer.

## 3.06.13 — Cloudflare setup guide moved into the plugin
- The Cloudflare cache-clear walkthrough is now an expandable "Setup guide" right in Performance → Clear cache (install the plugin, create an API token, connect it), instead of a separate docs file.

## 3.06.12 — Clearer Cloudflare requirement + setup guide
- The Clear-cache panel now explains that clearing Cloudflare goes through the official Cloudflare plugin, shows whether it is connected, and lists the quick setup steps when it is not (previously it just said "Cloudflare plugin not active").
- Added a full walkthrough at docs/cloudflare-cache-setup.md covering the plugin install, creating a Cloudflare API token, and connecting it.

## 3.06.11 — More accurate unused-media detection
- Fixed images being wrongly counted as "used". The rendered-page scan matched filenames as loose substrings (so "photo.jpg" matched "myphoto.jpg" and "1.jpg" matched "21.jpg"); it now matches whole file tokens only.
- The database scan no longer counts an image as used just because its filename appears in another attachment's own file records (_wp_attached_file / _wp_attachment_metadata etc.). Real references in content, builder meta, galleries and options still count.

## 3.06.10 — Code Snippets inside the plugin shell
- Code Snippets now opens inside the Velox shell (with the sidebar nav) like every other page — Media Editor, Custom Fields, etc. — instead of on its own bare screen. The Code Snippets item in the sidebar highlights while you are on it.

## 3.06.9 — Snippets search styling
- The snippets search box now uses the standard Velox input styling (so it no longer falls back to an unstyled box), and the search icon no longer overlaps the text — the field has proper left padding to clear it.

## 3.06.8 — Toggle redirects on/off
- Each redirect now has an on/off switch right on its row. Turn one off to stop it matching on the front end without deleting it; flip it back on any time. (The engine already only matched active rules — now you can control that per redirect.)

## 3.06.7 — Editable sitemap with live preview
- The XML sitemap is now configurable: choose which content to include (home, posts, pages, products), set change frequency and priority, and see a **live preview** (built from example URLs, not your real site) that updates as you change each setting.
- Those settings now drive the real sitemap — included types, changefreq and priority are written into sitemap.xml on regenerate (homepage always priority 1.0).

## 3.06.6 — Optimize images from the Media Library
- Added an "Optimize images" button to the top of the WordPress Media Library (next to "Add New Media File") that jumps straight to the Velox image optimizer, so anyone managing media can convert them without hunting for the plugin page.

## 3.06.5 — Actually replace old conversions with WebP
- Images converted before replace-mode existed were marked "done" and skipped forever, so they stayed PNG in the media library. The optimizer now treats any image whose main file is still PNG/JPG as not-yet-done, so re-running bulk optimization actually turns them into WebP (media library + front-end).

## 3.06.4 — Performance nav icons
- The Preload & Network and Background sections now have clear icons in the Performance sidebar (a broadcast mark and a cycle mark) instead of a faint/placeholder one.

## 3.06.3 — Even separator spacing
- Settings rows with a toggle were top-aligned, so the divider sat closer to the row below than the one above. Rows are now vertically centred, so every separator is the same distance from the text above and below it across all pages.

## 3.06.3 — UI fixes
- Settings rows: dividers now sit an equal distance above and below their text everywhere, instead of hugging the line beneath a field.
- Performance tab: added icons for the "Preload & Network" and "Background" sections.
- Snippets: the search box is properly styled again and its icon no longer overlaps the placeholder text.

## 3.06.2 — Inbox scrolling fix
- Fixed the inbox list clipping when you had more submissions than fit on screen — the list now scrolls properly so you can reach every message, and the inbox height scales with your window.

## 3.06.1 — Deliverability checker
- New **Check deliverability** button (Mail → Settings) that inspects your domain and tells you exactly why Gmail/Microsoft may be dropping your mail: sender-address alignment, whether SMTP is on, and your live SPF, DMARC and DKIM DNS records — with the exact record to add when one is missing.

## 3.06.0 — Outlook-style inbox
- The submissions inbox now works like a real mail client: sender **avatars**, **unread** markers that clear when you open a message, **pin** important ones to the top, **mark as done**, and **filter** by All / Unread / Pinned / Done.
- **Reply straight from the inbox** — write a reply and it emails the person who submitted (through your SMTP + sender identity), then marks the entry done.
- Form notifications now set **Reply-To** to the submitter automatically, so mail is never spoofed as the visitor (a common reason Gmail/Microsoft silently drop it) and you can just hit Reply.

## 3.05.3 — Mail fixes
- Saving a form no longer bounces you back to the mail dashboard — you stay on the tab you were on (Build, Notifications or Settings).
- New **Sender identity** setting (Mail → Settings): set your own From name and address so mail no longer goes out as "WordPress <wordpress@yourdomain>".
- Inbox: tightened the gap between each field label and its value so submissions are easier to read.
- The form-name box in the builder is now a proper, visible input instead of near-invisible text.

## 3.05.2 — Admin bar on the front end
- Fixed the Velox and Velox Maintenance items disappearing from the admin bar on the front end — they now show on every page, front and back, for admins. The heavy admin-only hooks stay gated so the front end stays light.

## 3.05.1 — WebP on the front end (Oxygen-aware)
- **Front-end images now actually serve WebP.** A new page rewrite swaps every uploads image — WordPress images, Oxygen Image elements, CSS background-images and hard-coded links — to WebP/AVIF when the browser supports it. This is what the old "serve WebP" option missed (it only touched WordPress-rendered images, not Oxygen).
- Front-end WebP serving is now **on by default**.
- In replace mode the **original is kept on disk as a fallback** and only swapped in for capable browsers, so hard-coded links and older browsers never hit a missing file. Attachment deletion cleans up every format sibling.

## 3.05.0 — Real WebP conversion + converted-images view
- **Images now actually become WebP.** With replace mode on (default), converting turns the JPG/PNG into a WebP right in your media library — correct mime type, correct smaller size shown — instead of a hidden front-end-only copy.
- **Fixed the wrong size readout.** The optimizer was adding up the original plus every thumbnail; it now reports the single main image, so the number matches what the media library shows.
- **Resize behaves as expected:** wider images scale down to the resize width with the height following automatically, and images already narrower are left alone (never upscaled). Relabelled the setting to make this clear.
- **New “Converted images” screen** — reach it from the button on the Images page to see every converted image with its before/after size and % saved.
- The WordPress “Add media files” uploader now shows a Velox line about WebP conversion.
- Added a **Replace originals with WebP** toggle (Images → Output formats) if you’d rather keep originals and serve WebP only on the front-end.

## 3.04.3 — PageSpeed report: card layout
- Split the PageSpeed report into clearly separated **cards** — an Overview card with the category gauges, a Metrics card, and one card per category — so it reads as distinct sections instead of one cramped wall.
- Overview card now leads with a plain headline (issues to fix on this device) plus the gauge for each category and the score legend.

## 3.04.2 — PageSpeed report rebuilt to match Google PSI
- Rebuilt the PageSpeed report to mirror Google’s own PageSpeed Insights layout: a row of category gauges, a proper **Metrics** section, then diagnostics grouped by category.
- Every check now uses Lighthouse’s colour-blind-safe **shape indicators** — red triangle (poor), orange square (average), green circle (good) — so problems read at a glance.
- Failures show first with their savings; passed audits sit behind a **“Passed audits (N)”** toggle. Snapped the whole screen to the Velox spacing/radius grid so it feels native, not templated.

## 3.04.1 — PageSpeed report: cleaner, faster to read
- Reworked the PageSpeed report to be easier to scan: it now opens with a **plain-English summary** (your score, how many issues to fix, how many checks pass) instead of four equal cards.
- Categories became a compact **score strip** you can click to jump straight to that section.
- Each category now shows the **problems first**; the passing checks are tucked behind a **“N passing checks”** toggle so the page isn’t a wall of rows. Savings show as tidy pills and the status icons are crisper.

## 3.04.0 — Full PageSpeed report + fixes
- **New PageSpeed screen** in the sidebar, right under Performance. It pulls the complete Google PageSpeed Insights report for your site and shows **every category — Performance, Accessibility, Best Practices and SEO** — each with its own score.
- **Mobile / Desktop** buttons at the top switch the whole report between devices instantly (both are still checked on every run).
- Each category lists its checks as **expandable accordions**, failures first with their estimated savings, then the passing checks — tap any row to read what it means and how to fix it.
- **Fixed: the dashboard “see what’s wrong” list was empty.** It now reads the current Lighthouse format correctly, so real opportunities and passing checks show up again.
- The report and the checks now come back in **English** regardless of your server locale.
- The dashboard PageSpeed widget got a **Full report** link straight to the new screen.

## 3.03.6 — Dashboard PageSpeed: switch device + see what’s wrong & right
- The dashboard PageSpeed widget now checks **both Mobile and Desktop** on every run and gives you a **Mobile / Desktop switch** right on the card — flip between the two instantly, no waiting for a new check. The **Default view** setting (Settings → Live PageSpeed) just picks which one shows first.
- A new **See what’s wrong & right** button appears once a score is in. Tap it to expand a tidy breakdown: the opportunities still **to fix** (red) alongside the checks that are already **passing** (green). Collapsed by default so the dashboard stays clean.

## 3.03.5 — Settings: more migrate sources + keep-data option
- **Migrate from another plugin** now lists the popular tools people switch from — Rank Math, All in One SEO, SEOPress, LiteSpeed, WP Fastest Cache, W3 Total Cache, Autoptimize, Perfmatters, FlyingPress, FluentSMTP, Post SMTP, CookieYes, Complianz, Redirection, WPCode and more. WP Rocket, Yoast and WP Mail SMTP import with one click today; the rest are recognised and marked "Migration coming soon".
- New **Keep my settings if I delete Velox** option (Settings → Housekeeping). Leave it on and deleting the plugin won&rsquo;t wipe your settings, forms, redirects or logs — handy for reinstalls. Off by default.

## 3.03.4 — Mail: Test connection + Reply-To
- New **Test connection** button on the SMTP screen actually opens a live handshake with your mail server (connect → encrypt → sign in) and tells you instantly whether the connection works — no need to send a test email and dig through your inbox. Errors are specific (wrong password, bad port, TLS mismatch, host unreachable).
- Each SMTP connection now has a **Reply-To** field, so replies to your outgoing mail land in the inbox you choose.
- The existing "Send test" (delivers a real test email) is still there alongside it.

## 3.03.3 — Media Editor: bulk download
- New **Download images** button on the Media Editor. Click it to enter select mode, tick the images you want (or hit **Select all**), then **Download selected** to get them as a single zip.
- The zip includes a plain-text file listing each image&rsquo;s alt text and title in the same `Dateiname | Alt-Text | Titel` format as Bulk import — so nothing is lost and you can re-apply it after re-uploading.

## 3.03.2 — Unused Media: accurate "Used" list + image lightbox
- The **Used** tab now lists only images with a confirmed reference (in your content, page-builder data, or the rendered page/CSS) — loosely-matched files no longer leak in and get mislabeled as used.
- The **Unused** tab stays deliberately cautious, so nothing borderline is ever offered up for deletion.
- Click any image in either tab to open it full-size in a lightbox.

## 3.03.1 — SEO screen cleanup
- Fixed a layout bug where the robots.txt and sitemap panels stacked instead of sitting side-by-side (the robots panel was never closed, nesting the sitemap inside it).
- Removed the oversized per-page title &amp; description preview card and slimmed the Social cards (Open Graph) section to a compact toggle.
- Made the .htaccess editor taller so you can see more of the file at once.

## 3.03.0 — Live PageSpeed on the dashboard
- New **PageSpeed** widget pulls a real Google Lighthouse score for your site and shows it on the dashboard, with Core Web Vitals chips and the top things to fix.
- Runs on a schedule (hourly / twice-daily / daily) via WP-Cron and caches the result, plus a **Run a check now** button for an on-demand refresh.
- New **Live PageSpeed** panel in Settings: enable it, add a PageSpeed Insights API key, choose the URL, device (mobile/desktop), refresh interval, and whether to show the metrics and the list of issues.
- Needs outbound access to googleapis.com and WordPress cron; without an API key Google rate-limits requests.

## 3.02.2 — Cookie banner shows again, truer visitor counts, stretchy Visitors graph
- **Cookie banner** now decides whether to show on the visitor&rsquo;s side instead of being baked into the page. Full-page caches (WP Fastest Cache / Cloudflare) could freeze one person&rsquo;s consent for everyone and hide the banner site-wide — fixed.
- **Visitor stats** now ignore every logged-in user (not just admins), so the count reflects real public visitors only.
- On the dashboard, a resized **Visitors** widget now grows its graph to fill the card and keeps it anchored to the bottom.

## 3.02.1 — Sidebar reorder + Settings gear
- Reorganised the sidebar: **Essentials** (Performance, Images, SEO) now sit directly under Dashboard &amp; Utilities, with everything else grouped under **More** below.
- **Settings** moved out of the list into a gear icon in the sidebar footer, next to &ldquo;by Sumasearch&rdquo; — one click from anywhere.

## 3.02.0 — Resizable dashboard widgets + more accurate visitor counts
- Dashboard widgets can now be resized. In Edit mode, hit the resize handle on any widget and set its **grid size** — width (columns) and height (rows) — so you can make Visitors wide, shrink a stat card, and lay the dashboard out how you like. Your sizes are saved per site.
- The dashboard now lays widgets out on a real 12-column grid, so widget sizes are actually respected (previously the size classes did nothing).
- Visitor tracking is more accurate: browser pre-render/prefetch loads no longer inflate the view count, and the bot filter now catches more crawlers, link-preview bots and HTTP libraries.

## 3.01.0 — Font manager: detect every font + block unwanted ones (5c)
- The font detector is now a full font manager. Each detected font shows its source (Google or Local) and can be individually preloaded or blocked.
- Blocking a Google-hosted font stops it loading entirely (the `<link>` is removed on the front end via style_loader_tag), independent of whether local hosting is on — the core OMGF "remove unwanted Google Fonts" behaviour, plus the existing local-hosting swap and preload.
- New `perf_font_block` setting stores blocked families; new Velox_Fonts::block_fonts(), block_list() and families_in_google_url() enforce it. detect() now labels each font's source.
- Note: local theme @font-face fonts are detected and labelled, but blocking individual local faces would require rewriting theme CSS, so blocking targets Google-hosted fonts (where the request can be cleanly removed).


## 3.00.0 — Performance page redesigned (5a)
- Rebuilt the Performance UI (Concept 1): a calm status strip up top (page-cache state, optimizations-on count, cache on disk), a primary "Clear all caches" action and Risky-mode toggle in the header, and an icon sidebar with a live "active" count badge per section (on/off for Cache & CDN, a number for the rest).
- Replaced the noisy yellow risky-mode banner. Panels, tooltips and the cache/font tools are unchanged — every setting is preserved, just presented in a modern, premium shell.


## 2.99.0 — Cookie banner settings: redesigned editor (2b + 2c)
- Rebuilt the cookie editor as a preview-first workspace (Concept A): a large live preview on the left that renders the banner at true proportions with a desktop/mobile toggle, and a compact tabbed inspector on the right (Layout · Content · Style · Setup) that replaces the nine stacked panels.
- The preview is much bigger (min 560px, full width of its column) so full-width bars, floating boxes and centred modals all preview truthfully — fixing the cramped/overflowing 380px preview.
- Inspector is a single sticky card with its own scroll and a sticky Save footer; panels are flattened (no nested cards) and grouped under tabs. Enable/disable moved to the inspector header. Stacks gracefully on narrow screens.


## 2.98.0 — Cookie banner: reliable front-end visibility (2a)
- The banner used to render hidden and rely on inline JS to reveal it — if that script was delayed, deferred or optimised away, the banner never appeared. Visibility is now CSS-driven: the banner shows by default and is hidden only once the visitor has made a choice (data-decided="1"), with the "cookie settings" link re-opening it. It no longer depends on JS timing to become visible.
- Note: on a site with page caching, enable the banner then clear caches so the change reaches already-cached pages; and it correctly stays hidden for visitors who already chose (test in a private window).


## 2.97.0 — Performance: CDN section no longer empty (5b)
- The CDN tab showed only a header because all three CDN fields (enable, URL, exclusions) were flagged "Risky" and hidden behind Risky mode. CDN rewriting is a mainstream, reversible feature, so it's no longer risky — the enable toggle, CDN URL and exclusions now always show.


## 2.96.0 — Backup: history remove/clear + never downgrade the plugin on restore
- Restore history now has a per-row remove (×) and a "Clear history" button (history-only — no backups are touched).
- **Reverting a backup no longer rolls Velox itself back.** File restore now skips the Velox plugin's own folder, so restoring an older backup keeps the plugin on the currently-installed version. New backups also exclude the Velox folder entirely.


## 2.95.0 — Unused media: stop over-reporting images as "used"
- Root cause: the reference check matched a bare filename STEM as a substring (%stem%), so a short name like "photo" or "img1" matched inside other images' variant filenames (photo-2-300x200.jpg, img12-...) sitting in attachment metadata — flagging almost everything as used.
- Now it matches the EXACT generated filenames (original + every real size variant + the -scaled original) against content, other posts' meta, and options. Precise IDs are still matched for ACF/galleries/blocks. Result: only genuinely-referenced images are marked used. (Simulated: a 10-image set with 1 truly used went from 5 false "used" to 1 correct.)


## 2.94.0 — Mail preview fix (root cause) + settings icon
- FIXED the broken Mail preview ("a modal I can't click through, page still visible behind it"). Root cause: the design tokens were scoped to .velox-wrap, but the preview overlay is appended to <body> outside it — so its background colour resolved to nothing, leaving an invisible layer that still blocked clicks. Tokens are now global (:root) while base layout stays scoped, so the preview is a proper opaque full-screen overlay. This also hardens every other body-appended element.
- The form's Name field renders as two styled side-by-side inputs (First/Last) — it was always styled, but the invisible preview hid it.
- Fixed the truncated Settings gear icon in the editor navbar (the path closed early and rendered broken); it's now a complete cog.


## 2.93.0 — Mail editor QA fixes
- Fixed the Build/Style/Preview mode highlight: the builder's switcher was also binding to the style-editor's copy of the navbar (it's a sibling in the DOM), so the active highlight could get cleared. The switcher is now scoped to the builder, and the Build highlight is restored automatically when the Style or Preview overlay closes.
- Hardened the navbar so button labels (e.g. "Save form") never wrap to two lines at narrower admin widths.


## 2.92.0 — Cache: auto-warm after purge (section 9, part 5 / cache parity)
- New "Auto-warm cache after purge" setting (Cache tab, on by default). After a full cache clear — manual, theme switch, menu update or customizer save — Velox schedules a debounced background rebuild of the homepage and recent pages a few seconds later, so the next visitor lands on a warm cache instead of triggering the regeneration themselves. This is the WP-Rocket-style preload behaviour applied to Velox's own cache.
- Rapid successive purges collapse into a single scheduled warm-up.


## 2.91.0 — Performance: font detector with per-font preload (section 9, part 4 / 9b)
- New "Detect fonts" button in the Fonts tab scans your front page and its stylesheets (same-origin + Google Fonts) for every @font-face and lists one row per family / weight / style with the actual file name.
- Each row has a preload switch. Turning it on adds that font file to the preload list, so Velox emits <link rel="preload" as="font"> for it — ideal for the 1–2 above-the-fold fonts. Turning it off removes it. Changes persist immediately.


## 2.90.0 — Performance: info tooltips on every setting (section 9, part 3 / 9d)
- Every Performance setting (all tabs) now has an "i" info icon that reveals its description on hover or keyboard focus, with an aria-label for screen readers.
- The always-visible description lines were removed so rows are compact and scannable — the full text now lives in the tooltip.


## 2.89.0 — Performance: dedicated HTML tab (section 9, part 2 / 9a)
- Added an HTML section to the Performance nav (between General and CSS) and moved the Minify HTML control into it.
- Two new sub-controls under it: "Remove HTML comments" and "Collapse whitespace", each independently toggleable, so you can tune exactly what minification does. Both default on (matching prior behaviour) and only apply when Minify HTML is on. Protected blocks (script/style/pre/textarea/conditional comments) are always preserved.


## 2.88.0 — Cache actions do the work themselves (section 9, part 1)
- "Clear minified CSS/JS" no longer dead-ends with "WP Fastest Cache not active" — Velox clears its own used/minified CSS and page cache first, and only hands WP Fastest Cache a purge if it's actually installed.
- "Regenerate Oxygen CSS" now recognises Oxygen by several signatures (CT_VERSION, plugin dir, class, function). If Oxygen's own regen helper isn't exposed in that version, Velox queues a rebuild itself by clearing the universal-CSS signature options + its own CSS cache, instead of falsely claiming "Oxygen not active".


## 2.87.0 — Admin menu: fix unreadable hover on Velox menu items (8b)
- Some admin colour schemes painted the hovered Velox menu/submenu row a dark fill while leaving the text dark, so labels and the arrow hint disappeared. Velox now keeps the row background unchanged on hover and colours the text (and arrow) with the accent instead — across the top-level row, every submenu item, and the collapsed-menu flyout.


## 2.86.0 — Mail & forms: Preview on the shared navbar (part 4 — form-editor redesign complete)
- The Preview now uses the same shared navbar as Build and Style (Preview active), with a Desktop/Mobile toggle and Close on the right, plus the on/off toggle kept in sync. Build/back returns to the builder; Style jumps to the Style editor.
- Preview stays form-only on a clean backdrop (no fake browser chrome); the submit button keeps its label; a slim "nothing is submitted" note sits under the bar.
- This completes the Mail form-editor redesign: one identical navbar across Build / Style / Preview, restyled palette, and the per-form on/off toggle.


## 2.85.0 — Mail & forms: Style editor on the shared navbar (part 3 of the form-editor redesign)
- The Style editor now uses the exact same top navbar as Build — back, breadcrumb, form name, on/off toggle, and the Build / Style / Preview switcher (Style active). Only the right-side actions differ: device toggles + Reset + Save & close.
- Build (and the back arrow) return you to the builder; Preview jumps straight to preview; the on/off toggle stays in sync with the one in Build.
- Next: the rebuilt Preview (form-only, no browser frame).


## 2.84.0 — Mail & forms: build-mode palette restyle (part 2 of the form-editor redesign)
- Reworked the field palette to the approved design: a single-column grouped list (Basic / Advanced / Layout) with borderless icon-row items and soft icon boxes, replacing the old boxed 2-column grid. Field names no longer truncate. All groups open by default.
- Next: the Style editor and the rebuilt Preview.


## 2.83.0 — Mail & forms: unified editor navbar + per-form on/off (part 1 of the form-editor redesign)
- New shared editor navbar with a Build / Style / Preview mode switcher that stays identical across all three modes; only the right-side actions change. Replaces the old top bar and removes the vanity stat tiles.
- **Per-form on/off toggle** (next to the form name). When a form is switched off, its shortcode renders nothing on the front end (admins see a small "this form is off" hint). Persists immediately.
- Note: this is the first stage of the Mail redesign — the palette/canvas/inspector restyle, the Style editor, and the rebuilt Preview land in the next updates.


## 2.82.0 — SEO: guarantee the meta title
- The custom SEO title now overrides reliably even when Oxygen or another SEO plugin fights for it: the title filter runs at max priority, and a head safety-net corrects the <title> tag if something else prints its own (never duplicating it).
- (Social-cards autosave toggle was already fixed in 2.76.0.)


## 2.81.0 — Custom fields: options-page parent fixes
- **Options pages no longer vanish** when given a non-top-level parent (e.g. "Under Velox"). They now register after the parent menus exist, so the submenu attaches correctly.
- **Edit reopens with the correct parent.** The parent-menu dropdown was showing the wrong value when reopening a saved options page; the custom dropdown now syncs to the saved parent.
- (Active toggles on every card and the un-clipped location-rules dropdown were already in place.)


## 2.81.0 — Custom fields: active toggle on the cards
- Field groups, custom post types, taxonomies and options pages now have an **active/inactive toggle right on their card** in the list — flip it to enable/disable without opening the editor. Saves instantly.


## 2.80.0 — Maintenance: Lottie field only shows when relevant
- The "Lottie animation file" input now appears only when the loading animation is set to "Lottie animation", and hides for the other animation types.


## 2.79.0 — Unused media: fix "everything is referenced"
- Fixed the over-eager scan that flagged every image as used. The by-ID reference check now matches an attachment ID only as a discrete value (exact, in a comma list, or quoted in serialized/JSON data) instead of as a bare substring — which had been matching the ID's digits anywhere inside Oxygen's page-builder JSON and marking everything as in use. Real references (ACF images, galleries, blocks) are still detected.


## 2.78.0 — Settings: drop Modules, add System status
- Removed the **Modules** on/off section — Velox is now all-in-one, every module always on (SEO included).
- Replaced it with a **System status** panel: Velox/WordPress/PHP versions, memory limit, max upload size, and writable checks for the cache dir and .htaccess.


## 2.77.0 — Dashboard: grid fill, smooth drag, Y-axis, edit-mode fixes
- **Widgets now fill the row.** Cards flex to fill the width — one card is full width, two split it in half, three into thirds, with no dead space. Reordering reflows the same way.
- **Smoother drag.** Reworked drag-to-reorder as a pointer-based sortable: the card lifts and follows the cursor, a placeholder shows the drop slot, and the other cards slide into place.
- **Visitors widget Y-axis.** The sparkline now shows visitor-count labels (max / mid / 0) down the left.
- **Edit-mode controls fixed.** The "Done" button and the "widgets selected" bar were showing all the time (a hidden-attribute override bug); they now appear only in Edit mode and disappear on Done.


## 2.76.0 — Fix: Social cards (OG) toggle now saves
- The SEO "Social cards (Open Graph)" switch had no save handler, so it couldn't be turned off — it snapped back on every reload. It now saves on change like the robots/sitemap toggles.


## 2.75.0 — Custom fields: clearer edit screen + Bootstrap icon picker
- **Field groups are now obvious on the edit screen.** The meta box gets a branded "Custom fields" header with an icon, a field count, an accent border and clean separators between fields — no more blending into the page as a plain grey box.
- **Bootstrap-icon picker for options pages.** When creating an options page, click "Choose icon" to search a grid of common Bootstrap icons (e.g. gift, gear, cart) instead of hunting for a dashicons class. The exact icon is used for the admin menu. You can still type a `dashicons-…` class or image URL.


## 2.74.0 — Maintenance: Lottie loading animation
- The maintenance loading animation can now be a **Lottie** animation. Pick "Lottie animation" from the dropdown, then choose a `.json`/`.lottie` from your media library or paste a link (e.g. LottieFiles).
- `.json` and `.lottie` uploads are now allowed in the media library (admins only) so they can be picked.
- The media picker can now browse non-image files where needed.


## 2.73.0 — Unused media: accuracy + used/unused views + sizes
- **Fixed false "unused" flags.** The scanner now reads the generated CSS cache (Oxygen, Elementor, Bricks) where page-builder `background-image` URLs live, crawls many more pages (up to ~60, including products), and recognises images referenced by ID inside builder meta (`ct_builder_shortcodes`, Elementor, etc.). Images used as section backgrounds or on deeper pages are no longer mistaken for unused.
- **Used / Unused toggle.** After a scan, switch between every unused image and every used image.
- **File size on every item** in both views, with a running total.


## 2.72.0 — Mail: notifications autosave in place
- Toggling a notification email on/off (and editing its recipient/subject/advanced fields) now **saves instantly and keeps you on the Notifications tab** — no more clicking Save and getting bounced back to the Mail dashboard. Rapid changes are debounced into one save.
- (Per-email on/off already gated sending on the back end; this makes the setting stick without a full save.)


## 2.71.0 — SEO robots/OG + form-canvas fixes
- **SEO robots**: pages now state their intent explicitly — `index, follow` when allowed (previously emitted nothing), `noindex`/`nofollow` when restricted — driven through WordPress's native `wp_robots` filter so there's exactly one robots tag.
- **SEO Open Graph toggle**: new "Social cards (Open Graph)" switch in SEO settings. Off = no OG/Twitter tags anywhere; on = full social tags as before.
- **Form canvas**: removed the stray field-type label that sat in the bottom-right corner of each field card.
- **Form canvas**: field cards now have up/down arrows in the hover toolbar to move a field one step at a time (alongside drag-to-reorder).
- Footer link now points to https://www.sumasearch.de/.


## 2.70.0 — Design system: single-sourced colors
- Consolidated **78 hardcoded colour values** across the admin CSS into the central design tokens, so the whole UI now resolves from one palette (`--vx-*`). Change a token once and every screen follows — no more parallel greys and one-off blues drifting apart.
- Verified zero visual change: every converted value was an exact or imperceptible match to its token. The neutral ramp and the accent/primary family are now fully tokenised.


## 2.69.0 — Dashboard: drag-to-reorder widgets
- In **Edit** mode you can now drag dashboard widgets to reorder them. The layout reflows automatically and your order is saved, so it sticks across reloads and devices.
- Completes the dashboard customization set alongside add/remove and the new Traffic page.


## 2.68.0 — Dashboard: Traffic page + sparkline labels
- New **Traffic page** (Dashboard → Visitors widget → "View traffic"). Pick a range (7 / 14 / 30 / 90 days) and see total visitors, page views, your peak day, and daily average, plus a clean visitors-per-day bar chart with the peak day highlighted. First-party counts from Velox's own beacon — no third-party analytics.
- The traffic **sparkline** on the dashboard now shows its date axis labels (start · middle · end) under the line.


## 2.67.0 — Performance: Minify HTML
- New **Minify HTML** toggle (Performance → General). Strips comments and collapses inter-tag whitespace in the final page HTML, applied as pages are cached.
- Conservative and fail-open: `<script>`, `<style>`, `<pre>`, `<textarea>` and `<code>` blocks and IE conditional comments are left byte-for-byte intact, attribute values are never touched, a single space is kept between inline elements, and any parsing hiccup returns the original HTML untouched — it can't blank a page.


## 2.66.0 — Mail: Style editor matches the dashboard
The full-screen form Style editor was running its own parallel palette. Rebuilt its chrome on the real Velox design tokens so it's visually one system with the rest of the admin:
- Flat `--vx-bg` canvas instead of the radial gradient.
- Scattered one-off grays replaced with the standard ink/line ramp.
- All radii snapped to the 6 / 10 / 16 scale (inputs, swatches, segmented controls, tree nodes).
- Active tree node now uses the standard primary tint + readable primary-ink text; device/segment toggles match the dashboard.
- Removed a duplicate focus rule. (The form preview itself is untouched — that reflects your actual form's styling.)


## 2.65.0 — Mail: Notifications card is now your inbox
- The **Notifications** stat card on the form builder now shows how many submissions you've **received** (instead of how many notification emails were configured), and the whole card is **clickable** — it opens the entries list of everyone who submitted that form.
- New forms show `0 received` and the card stays non-clickable until the form has entries.


## 2.64.0 — SEO: editable .htaccess
Added an **.htaccess editor** to the SEO page, with guardrails so you can't easily break the site.
### Added
- View your site-root `.htaccess` directly in Velox. The editor is **read-only until you flip "Unlock editing"**, which takes a snapshot of the current file first.
- **Save .htaccess** writes your changes to the live file (it refuses to write an empty file, which would 500 the site).
- **Reset to default** instantly reverts the file to the snapshot taken when you unlocked — your safety net if a rule goes wrong.
- Clear warnings, plus graceful handling when the file isn't writable or doesn't exist yet.


## 2.63.0 — Redirects: full add/edit modal
The cramped inline "add a redirect" row is replaced by a proper **Add redirect** button that opens a modal with the complete rule, and **Edit** reopens the same modal for any rule.
### Added
- **Match types** — beyond exact-URL matching you can now match **URL starts with** (prefix) or a **Regex pattern** (with `$1` back-references supported in the target).
- **Priority** — higher-priority rules are checked first, so you control which rule wins when several could match.
- **Category** and **Description** — organise and annotate rules; both show as badges/notes on the list.
- **Active toggle** — disable a redirect without deleting it (shown as an "Off" badge and dimmed).
- **Per-rule matching options** — Ignore case, Ignore query parameters and Ignore trailing slash, each toggleable per rule.
### Notes
- Redirect rows now show match-type, category and status badges plus the description inline.
- Existing redirects are migrated automatically (treated as exact, active, with all "ignore" options on) — no action needed.


## 2.62.0
### Fixed
- **Cookie banner settings — input styling mismatch.** Text inputs, number inputs and textareas were rendering with WordPress's default chunky border while the dropdowns kept the Velox look — a CSS specificity collision (WP's `input[type=…]` attribute selector out-specifies a bare `.velox-input`, but its weaker `select` rule loses to `.velox-select`). Re-asserted the design-system control styling scoped to `.velox-wrap` with element qualifiers so inputs, textareas and selects now share identical border, radius, padding and height everywhere.
- **Oversized inputs in "Typography & advanced".** WP's default `min-height`/`line-height` were inflating those inputs; now overridden so they match the standard control height across the whole page.
- Consolidated repeated inline `(optional)` hint styles in the cookie view into a `.velox-hint--inline` utility class.


## 2.61.0 — Cookie Banner: fix “not showing” after enabling
Enabling the banner often showed nothing on the front end. Causes fixed:
- Toggling a utility on/off now **purges the page cache**, and the purge reaches common third-party caches (WP Fastest Cache, WP Rocket, W3TC, WP Super Cache, LiteSpeed) — not just Velox’s own — so the banner appears immediately instead of waiting for cached pages to expire.
- The banner now ships with a sensible default **heading and body text**, so it’s usable the moment you enable it without setting anything up.
Note: Cloudflare’s edge cache can’t be purged without API credentials, so you may still need to clear it (or use Development Mode) when testing.

## 2.60.0 — Script Manager: fix Scan Site
Scan Site could come back empty (especially right after “Reset discovered list”), which then left nothing to save. Two causes fixed:
- The loopback request that powers the scan was being served from **page cache**, so WordPress never actually ran and no handles were collected. The scan now appends a unique cache-busting token so the page truly executes.
- Enforcement was running *during* the scan and dequeuing already-disabled handles, so they could never reappear in the results. Enforcement now stands down during a scan, so discovery sees every handle.
With discovery working again, the rows repopulate and Save has real rules to store. (Reset still clears only the discovered list and keeps your saved rules on purpose.)

## 2.59.0 — Custom Fields: return formats
- **Image / File** fields can return the attachment **ID**, the **URL**, or a full **attachment array** from `get_field()`.
- **Date / Datetime / Time** fields can return a formatted string using any PHP date format (e.g. `F j, Y`).
- Added a field-config lookup so `get_field()` knows each field’s type and return format. Fully backward-compatible: values come back exactly as before unless you set a return format.

## 2.58.0 — Custom Fields: prepend / append addons
- Text, number, range, email, URL and password fields can now show a **prepend** and/or **append** addon (e.g. “$” before a price, “px” after a number). They render as joined input addons on the edit screen, and only appear when you set one — fields without addons look exactly as before.

## 2.57.0 — Custom Fields: more per-type settings
First batch of expanded field settings, each wired end-to-end (editor → save → front-end render):
- **Read-only** toggle for text, textarea, number, range, email, URL and password fields.
- **Select:** “Allow null” toggle for the empty choice.
- **Checkbox / Radio:** Layout — vertical or horizontal.
- **Button group:** Layout — horizontal or vertical.
- **WYSIWYG:** Toolbar (Full / Basic), editor rows, and a media-upload button toggle.

## 2.56.0 — Custom Fields: options-page enable/disable
- Options pages now have an Active toggle (with a status badge in the list), matching field groups, post types and taxonomies. Turn one off and it disappears from the admin menu without being deleted — flip it back on and it returns.
- Legacy options pages saved before this update are treated as active by default, so nothing you already built changes.

## 2.55.0 — Custom Fields: per-field enable/disable
- Every field now has its own on/off switch in the editor card. Flip a field off to keep its definition (and any saved values) without showing it on posts or options pages.
- Disabled fields are skipped on render **and** on save, so turning one off never wipes the data already stored against it. The card dims and shows an “off” badge so the state is obvious at a glance.

## 2.54.0 — Custom Fields: location rules UI + live options-page slug
- **Location rules** redesigned: each rule is now a tidy card with the remove (×) button *inside* it, the param on its own row and operator + value below, so nothing spills out of the panel — and the panel itself is wider.
- **Options pages:** the slug now fills in live from the page title as you type (lowercased + hyphenated). Edit the slug yourself and it locks, so your custom slug is never overwritten.

## 2.53.0 — Custom Fields: fix label-typing focus loss
Typing a field label dropped focus after every single character, forcing you to click back in for each letter. The editor was doing a full re-render of the field list on every keystroke, which tore the input out from under you.

- Label / name / required now update the field card’s title, meta and auto-derived name **in place** — focus stays put and you can type normally.
- A full re-render now only happens when you change the field *type* (which genuinely swaps the settings panel).

## 2.52.0 — Offcanvas reorder
- **Overview** now holds Dashboard + Utilities; **System** (Settings, SEO, Backup) moved up right under it.
- Dropped **Duplicate Post** and **SVG Uploads** from the menu (they’re toggle-only, no settings page — still available in Utilities).

## 2.51.0 — Live data: Visitors + Form submissions widgets
Two dashboard widgets now run on real, first-party data.

- **Form submissions** counts genuine submits through Velox&rsquo;s Mail & Forms (spam/honeypot hits excluded) and shows the last 30 days.
- **Visitors** is a privacy-first counter: a tiny front-end beacon pings a Velox REST endpoint on each view (so it still counts behind your page cache), storing only daily aggregates. No cookies, no raw IP &mdash; uniques are de-duped with a salted hash whose salt rotates daily; bots and logged-in admins are skipped. Shows this week, views, the week-over-week trend and a sparkline.
- New **Settings → Dashboard traffic** toggle to turn visitor counting off (default on). Mention the aggregate counting in your privacy policy.

Both ship as normal cockpit widgets, so you can remove or re-add them in Edit mode.

## 2.50.0 — Customizable dashboard widgets
The cockpit is now yours to arrange. Hit **Edit** to enter edit mode, then:

- **Remove** any widget with its × — one at a time.
- **Select several** (click to tick them) and use **Remove selected** in the batch bar to clear them in one go.
- **Add widget** lists everything you’ve removed so you can put it back.
- **Done** leaves edit mode.

Your layout is saved per site (stored in `dash_hidden`) and persists across reloads. Ships with a **Local fonts** widget off by default so the Add-widget picker has something in it. All widgets still run on real Velox data.

## 2.49.1 — Two UI fixes
- **Snippets type filter:** the “All types” dropdown was the one select that never became a custom dropdown, so its funnel icon overlapped the text. It now matches the rest and the icon no longer overlaps.
- **Post-edit “Velox” meta box:** forced a light-grey, legible hover on its header so the title/toggle stay visible. (The brown/black hover comes from the WordPress admin colour scheme, not Velox — it affects every plugin’s meta box, including ACF; switching the scheme in your profile removes it everywhere.)

## 2.49.0 — Redesigned Dashboard: the cockpit
The Dashboard moves to the new design language: an at-a-glance **cockpit** of widgets — Performance (live optimization score), Cache (critical CSS built + purge), Database (junk rows + clean), Images (optimized + engine), and Recommendations — followed by an **Everything in Velox** grid linking to every area and utility.

- All widgets run on real data already computed by Velox (no placeholder numbers).
- The plugin-conflict (“turf war”) panel is preserved.
- Same modules and actions as before, reorganised and reskinned.

## 2.48.0 — Redesigned offcanvas: the full Velox menu
First piece of the new design language lands in the plugin: the sidebar/offcanvas now lists **everything Velox offers** — all areas and utilities — grouped into Overview / Content / Performance / Site Tools / System, instead of only the switched-on ones.

- Cleaner nav: uppercase section labels, lighter rows, a subtle cyan-tint active state (was a solid fill), monospace version pill.
- Footer is a single “by Sumasearch” link to sumasearch.de with the Velox mark — nothing else.
- The WordPress admin bars and the collapse toggle are unchanged.

## 2.47.0 — Custom dropdowns everywhere + pick-from-list location rules
- **Custom dropdown component:** every native browser `<select>` across the Velox admin (`.velox-select`) is now replaced with a clean custom dropdown matching the design system — rounded, accent focus ring, hover states, keyboard support (arrows / enter / esc), click-outside to close. The native select stays underneath as the source of truth, so nothing breaks.
- **Location rules are now pick-from-list:** instead of typing a post type or taxonomy slug, the value is a dropdown of the actual registered post types, taxonomies, user roles, post statuses, options pages and page templates. Changing the rule type swaps the available choices automatically.

## 2.46.0 — Custom fields: tabbed field settings + field widths
The field editor now matches ACF 6’s layout: each field’s settings are split into **General / Presentation / Conditional Logic** tabs instead of one long stack.

- **Presentation** tab adds a **Field width** setting (100 / 75 / 66 / 50 / 33 / 25%) and an optional wrapper CSS class — set two fields to 50% and they sit side by side on the editor.
- **Conditional Logic** moves into its own tab.

## 2.45.0 — Custom fields: five more field types
Added the remaining common field types so the set matches ACF’s basics: **Password** (masked input), **Page Link** (select a post, use its permalink), **Date & Time** and **Time** pickers, and **Message** (a display-only note shown to editors, no stored value). All appear in the Browse Fields picker with icons and descriptions.

## 2.44.0 — Custom fields: per-type field settings
Fields now show settings specific to their type in the field group editor, instead of the same generic set for everything:

- **Number / Range:** Minimum, Maximum, Step (Range no longer abuses the Choices box).
- **Text / Email / URL / Password:** Character limit.
- **Text Area:** Rows + character limit.
- **Select:** “Allow multiple selections” — renders a multi-select and stores an array.

These are honoured on the actual input (min/max/step/maxlength/rows attributes; multi-select).

## 2.43.0 — Custom fields: ACF-style “Browse Fields” type picker
The field-type dropdown in the field group editor is replaced with a proper **Browse Fields** experience, matching ACF’s look and feel:

- Each field now shows its type as a button (icon + name); clicking it opens a modal.
- The modal has a category rail (Basic / Content / Choice / Relational / Pickers / Layout), a searchable, icon-and-description card for every field type, and keyboard/Escape support.
- Makes the 27 field types actually discoverable instead of buried in a flat dropdown.

## 2.42.0 — Custom fields: Group field now works
The **Group** field type was listed but rendered as a plain text box. It now works properly: define sub-fields (same editor as the repeater), they render as one bundled block on the editor, and save as a nested array. Read with `$g = Velox_Fields::get_field('address'); echo $g['street'];`.

## 2.41.0 — Custom fields: more field types
Added the remaining ACF-style field types: **Post object** & **Relationship** (pick posts/pages), **Taxonomy term**, **User**, **Link** (url + text + new-tab), **Button group**, **Range slider**, and **oEmbed** (paste a URL, get an embed). For post object / relationship / taxonomy, put the post type(s) or taxonomy slug in the field’s Choices box; range takes min / max / step (one per line).

## 2.40.0 — Custom fields: conditional logic
**Fields can now show or hide based on another field’s value** — the same conditional logic ACF offers:

- In the field group editor, expand any field and tick “Conditional logic”, then add rules: show this field when <other field> <is / is not / has any / has no value> <value>. Multiple rules are ANDed.
- On the post editor (and on options pages), fields appear and disappear live as you change the controlling field — no reload.
- Also: the post-edit and options-page field rows now share one render path, so both behave identically.

Next (chunk 4b): more field types — post object, relationship, taxonomy, user, link, button group, range, oembed.

## 2.39.0 — Custom fields: Options pages
**Create admin settings pages whose fields save to options instead of post meta** (ACF-style options pages):

- New “Options pages” tab under Custom fields: give it a title, slug, parent menu (top-level, or under Settings / Appearance / Tools / Velox…), icon and position.
- Point a field group at it with a location rule “Options page is <slug>” — those fields then render on the admin page (all field types work, including repeater/flexible/media).
- Read the values anywhere with the ‘option’ target:

```php
echo Velox_Fields::get_field( 'footer_text', 'option' );
while ( Velox_Fields::have_rows( 'social_links', 'option' ) ) { Velox_Fields::the_row(); echo Velox_Fields::get_sub_field( 'url' ); }
```

Next: more field types (post object, relationship, taxonomy, user, link, range…) + conditional logic.

## 2.38.0 — Custom fields: Flexible Content
**New Flexible Content field** — define several named layouts, each with its own sub-fields, then build a page by stacking rows of whichever layout you need (e.g. Hero, Quote, Gallery):

- Define layouts in the field group editor, each with its own set of sub-fields.
- On the post editor: an “Add row” menu lets you pick which layout to add; rows can be removed and dragged to reorder.
- Read it on the front end, branching per layout:

```php
while ( Velox_Fields::have_rows( 'blocks' ) ) {
    Velox_Fields::the_row();
    if ( 'hero' === Velox_Fields::get_row_layout() ) {
        echo Velox_Fields::get_sub_field( 'heading' );
    }
}
```

Next: options pages, then more field types + conditional logic.

## 2.37.0 — Custom fields: working Repeater field
**The Repeater field now actually works** — define a set of sub-fields once, then add as many rows as you like on the post editor:

- Build sub-fields right inside the field group editor (label + name + type: text, text area, number, email, URL, image, file, true/false, colour, date).
- On the post editor: Add row, remove row, and drag rows to reorder. Image/file sub-fields use the media library like any other.
- Read it on the front end with an ACF-style loop:

```php
if ( Velox_Fields::have_rows( 'items' ) ) {
    while ( Velox_Fields::have_rows( 'items' ) ) {
        Velox_Fields::the_row();
        echo Velox_Fields::get_sub_field( 'heading' );
    }
}
```

Or just `Velox_Fields::get_field('items')` to get the array of rows. Next: Flexible Content, then options pages.

## 2.36.0 — Custom fields: real media, WYSIWYG & gallery inputs
**The Image, File and WYSIWYG field types now actually work, and there is a new Gallery field** (part of the ongoing ACF-grade build):

- **Image / File** — open the WordPress media library, pick an item, see a live preview (or filename), and clear it. Stores the attachment ID.
- **Gallery** — new field type: add multiple images from the media library, thumbnails with one-click remove, no duplicates. Stores a list of attachment IDs.
- **WYSIWYG** — a proper visual editor (TinyMCE) with media buttons, instead of a plain text box.

Next chunks: the Repeater + Flexible Content engine, then options pages.

## 2.35.0 — Snippets: choose where each one runs (WPCode-style locations)
**Snippets now have a proper Location picker that changes with the snippet type**, instead of only ever loading in the footer:

- **PHP:** Run everywhere · Frontend only · Admin only · Run once.
- **CSS:** Site &lt;head&gt; · Site footer.
- **JS / HTML:** Site header · After &lt;body&gt; open · Site footer · Before post content · After post content · Before paragraph N · After paragraph N · Shortcode only.

Pick “Before/After paragraph” and a paragraph-number field appears; pick “Shortcode only” and you get a [velox_snippet id=…] to drop anywhere. Existing snippets keep working exactly as before (CSS in the head, JS/HTML in the footer) until you change their location.

## 2.34.0 — Custom fields: create post types & taxonomies (ACF-style)
**Custom fields can now create custom post types and taxonomies, not just field groups.** The Custom Fields screen is split into three tabs: Field groups, Post types, and Taxonomies.

- **Post types** — create a post type (e.g. Movies, Projects) and it appears in the admin sidebar next to Posts and Pages right away. Control the slug, labels, menu icon and position, what it supports (title, editor, featured image, custom fields…), public/REST(Gutenberg)/archive/hierarchical, and which taxonomies attach to it.
- **Taxonomies** — create category-like (hierarchical) or tag-like (flat) taxonomies, choose which post types they attach to, and toggle public/REST/admin-column.
- Everything registers on every load, so your post types and taxonomies survive and behave like native ones — and your Velox field groups can target them.

This is the foundation of the bigger custom-fields work; the field-group editor redesign, repeater/flexible-content fields and options pages come next.

## 2.33.11 — Backup import now restores in one step
**Importing a backup now restores it immediately.** Upload a .sql or .zip backup and Velox applies it to the current site right away — no separate Restore click afterwards. A safety backup of the current site is taken first, so the import can itself be rolled back from the backup list if anything looks wrong.

## 2.33.10 — Forms style editor: tabs + jump between Preview and Edit
**The style editor’s structure panel is now tabbed** — All / Inputs / Text / Button — so you can jump straight to the group you want instead of scrolling one long list. The individual-field list lives under Inputs. You can now also hop straight between the two modes: the Style editor has a **Preview** button in its top bar, and the full-screen Preview has an **Edit styles** button — no more closing one to open the other.

## 2.33.9 — Cookie banner editor redesign + 404 log fix
**The cookie banner editor got a proper UX pass.** Every on/off toggle (Consent Mode, the Analytics/Marketing categories, drop shadow, dim background, full-width mobile buttons) is now a clean, self-contained card, so the switch sits neatly at the edge instead of floating far away from its label. Toggle groups pack together tightly instead of leaving an awkward empty half-row, every number field now fills its space instead of stranding a tiny box with dead space beside it, the colour swatches look more modern, and the spacing throughout is consistent and easier to scan. All the existing power — placement, Consent Mode v2, per-button styling, layout controls, typography and custom CSS — is unchanged, just much nicer to use.

**Redirects & 404s:** turning *Log 404s* off now actually hides the existing log (it's no longer just "stop recording while the old rows stay on screen"). The entries aren't deleted — switch logging back on and they all reappear and resume updating. The empty state explains this when logging is off.

## 2.33.8 — Forms builder: drag & drop, working Preview, per-field styling
**The Mail & forms builder is now fully interactive.** You can drag fields straight from the left palette onto the canvas and drop them exactly where you want — an insertion line shows where they'll land — as well as drag existing fields to reorder them. The **Preview** button now works: it opens a full-screen, true-to-front-end preview of your form that you can actually type into (with a Desktop / Mobile toggle), so you can see exactly what visitors get. The **Style editor** now lists every field individually under "Individual fields" — select any single field to style just its label and its input (colours, font, border, radius) without touching the others, on top of the existing whole-form / labels / inputs / submit controls. The live preview inside the style editor is now typeable too, with real placeholders and every field type shown, so changes are visible as you make them. Also fixed: the search box in the field palette no longer overlaps its own magnifier icon.

## 2.33.7 — Reworked setup wizard
**The setup wizard is now a guided 4-step flow with a much cleaner UI.** Step 1: pick your page builder yourself from a grid (with a one-click "Detect it for me" and the option to request an unlisted builder). Step 2: choose how to set up — let Velox detect and recommend everything, or configure it yourself. Step 3 (recommended path): Velox scans your builder and installed plugins (caches, SEO, form and shop plugins), warns about conflicts, and shows every tuned setting with a plain-English explanation — each one a toggle you can switch off before applying. Step 4: done.
- You stay in control: nothing is changed until you hit Apply, and you choose exactly which recommendations to keep.
- The wizard now also initialises reliably even if its script loads late.

## 2.33.6 — SEO no longer duplicated
- SEO appeared twice in the menu (top-level and again inside Utilities). It now shows only once as a top-level item. Its on/off switch has moved to Settings → Modules, alongside the other top-level modules (Images, Media, Performance, Database).

## 2.33.5 — Cookie banner: full button + CSS control
**Buttons are now fully editable.** Add as many as you like, delete or reorder them, rename them, and for each one choose whether it’s a button or a link, what it does (accept all, reject, open preferences, save choices, or go to a URL), and its preset style (primary, secondary, ghost). Each button also has its own optional styling — background, text, hover colours, border, radius, padding, font size and weight.
- **Expanded banner styling**: heading and body size/weight/colour, legal-link colour and underline, button gap and typography, overlay colour and blur, max height and z-index.
- **Advanced custom CSS box**: write any CSS to target the banner (`.vxck`) or any individual button (`.vxck-b-<id>`), applied on top of every other setting.
- Everything updates live in the preview as you edit.

## 2.33.4 — Unused media: far fewer false positives
- The unused-media scan no longer flags images that are actually in use. It now also checks resized image variants (e.g. -1024x768, -scaled), theme/customizer/widget options, and media referenced by attachment ID in galleries and blocks.
- On top of the database checks, the scan now fetches your live pages and reads the rendered HTML, so images used by page builders, sliders or CSS backgrounds (which the database scan can't see) are correctly recognised as in-use.
- An image is only listed as unused when it appears nowhere in your content, settings, or on any scanned page.

## 2.33.3 — Script Manager scan improvements
- Scanning now crawls several representative pages (home, a recent post and a page) so it discovers all the scripts and styles your site actually loads, not just the ones on the homepage.
- Admin-only handles (admin bar, dashicons, heartbeat, etc.) are filtered out so the list only shows real front-end assets you can manage.
- The Scan button now shows a loading spinner while it works and refreshes the list automatically when it finishes — no more wondering if anything happened.

## 2.33.2 — Clearer backup names
- Backups are now named after your site and the time they were made — e.g. `mysite-2026-06-29-1432` — instead of random words. Much easier to tell backups apart at a glance.

## 2.33.1 — Consistent inputs everywhere
- Unified every text input, search box, hex field and spacing cell across all screens (settings, utilities, the forms builder and the full-screen style editor) to one consistent design — same border, radius, padding, size and focus ring. The plugin now reads as one continuous design instead of each screen styling its own inputs.

## 2.33.0 — Custom fields (ACF-style)
**A brand-new Custom fields module** — add custom fields to posts, pages and any post type, the ACF way.
- Build **field groups** with a clean editor: expandable field cards (drag to reorder, duplicate, delete), 17 field types (text, textarea, number, email, URL, select, checkbox, radio, true/false, image, file, WYSIWYG, date, color, relationship, repeater, group), with label, name, default, choices, placeholder, instructions and required per field. Field names auto-generate from the label and stay unique.
- **Location rules** decide where each group shows: post type, post status, page template, taxonomy or user role, with is / is not. Rules within a box are ANDed; add more boxes to OR them together.
- **Presentation** options: label placement (top / left), meta-box position (normal / side) and ordering.
- Fields render on the matching post-edit screens and save to post meta.
- Read values on the front end with `Velox_Fields::get_field('name')`, or drop `{field:name}` merge tags into content.
- Enable it under Utilities → Custom fields.

## 2.32.0 — Forms builder, reimagined
**A completely rebuilt form builder.**
- Fresh, modern design: clean monoline icons throughout, soft rounded field cards, a soft-card field palette, and a calmer layout. The form canvas now runs full-width by default.
- New editing flow: click a field’s **Edit** to slide in the settings panel from the right (the canvas reflows to make room); close it with the × and the canvas returns to full width.
- Every field now has a hover toolbar with **Edit, Copy, Paste, Duplicate and Delete** — copy a field and paste it into any form.
- The submit button is now a real element on the canvas: it appears automatically once the form has a field, and you click it to edit it. (The old submit-label and accent settings have moved out of the Settings tab.)

**New: full-screen Style editor.**
- A dedicated visual editor (opened from the toolbar) with a live preview of your form. Pick any element from the selector — whole form, header, labels, inputs, or the submit button — and style it completely: colours, hover colour, typography (size & weight), alignment (left / centre / right / full width), padding & margin (with a 2-field or per-side toggle), border, corner radius and box-shadow.
- Desktop / tablet / mobile preview widths, plus Reset.
- Your styles are saved with the form and rendered on the front end, scoped per form so multiple forms never clash.

## 2.31.1 — Snippet menu fix
- Fixed: the ⋯ actions menu on a snippet row was clipped by the list panel and could be invisible on lower rows. It now opens as a viewport-aware popover that flips upward when there isn’t room below, so Edit / Duplicate / Export / Move to trash are always fully visible.

## 2.31.0 — Sidebar hover, unified inputs, stronger notifications
**Sidebar.**
- The Velox item now shows WordPress’s normal submenu; the active-utilities popover appears only when you hover the **Utilities** row specifically (no more whole-Velox flyout). The popover is viewport-aware — it escapes the sidebar overflow, clamps on screen, and flips to the left if there isn’t room on the right.

**Inputs — unified everywhere.**
- Added a safety-net so every text field, select and textarea on any Velox page picks up the same styling and focus ring, so inputs look identical across all screens even where a view omitted the class.
- Cookie banner colour pickers are now bordered swatch tiles (swatch + label) instead of bare squares.

**Mail & forms — notifications reworked.**
- Each notification is now a stronger card: a header bar with the title, description and a live Enabled/Off status toggle, the primary fields (Send to, Subject, Email body) up front, and From / Reply-To / CC / BCC tucked into a collapsible “Advanced” section. Disabled notifications dim. Mirrors the FluentForms notification layout.

**Code Snippets.**
- Fixed the filter dropdown so its icon and the “All types” text no longer overlap.

## 2.30.0 — FluentForms-grade Mail, WPCode-grade Snippets, backup fixes
**Mail & forms — rebuilt to FluentForms quality.**
- Form builder is now a true three-pane editor: a live form canvas on the left with per-field hover toolbar (move, up, down, edit, duplicate, delete), and a right rail with a collapsing field-category palette (General / Advanced / Layout) plus a full field inspector (label, placeholder, label-placement segmented control, required, width).
- Dashboard keeps the submissions inbox, forms table, SMTP, CAPTCHA gate and send log, all on the unified design system.
- Inbox now has a fixed 400px height and scrolls internally; each submission row has its own trash icon that appears on hover, so you can delete a specific entry straight from the list.

**Code Snippets — reworked toward WPCode.**
- Denser, stronger snippet rows: status dot, bold type badge, name with tag chips, description line, and scope/priority/type pills.
- New filter-by-type dropdown and a live search box above the list.
- Editor rebuilt into a two-zone layout: the code editor as the main area with a sticky top bar (back, type badge, Save actions) and a configuration sidebar (name, description, type, location, priority).

**Backup & restore — clearer downloads and two real fixes.**
- One download button per backup, labelled by contents: “Download” (database + files as a single bundle), “DB download”, or “Files download”. Restore and Delete sit beside it.
- Fixed: a downloaded “both” backup now bundles the database and files together, so importing it on another site restores everything (previously the split SQL/ZIP downloads meant an import only brought back half).
- Fixed: restoring an older database no longer rolls Velox backwards. The plugin’s own settings, version markers and active state are preserved across a restore, so Velox always stays at the currently installed version and active.

**Navigation.**
- The left-sidebar Velox flyout now mirrors the full top-bar menu (Dashboard, Images, Media Editor, Performance, Database, SEO, Utilities, Settings), with Utilities opening a nested sub-flyout of your active utilities — matching how Performance & Cache nests in the top bar.

## 2.24.0 – 2.29.0 — The big redesign (summary)

Velox was rebuilt from the inside out across six releases. Every screen now shares
one design language, the navigation was rethought, and the four heaviest tools —
Snippets, Mail & forms, the Cookie banner and Backup — were reworked with the
features that were missing. Highlights:

- **One consistent look, everywhere.** The whole plugin moved onto a single Apple-inspired
  design system: parchment surfaces, near-black ink, a tuned Inter type scale, one
  radius ladder, calm spacing, near-flat elevation and a single accent (#2ab7f1).
  Screens that used to look like separate tools now read as one product.
- **Rethought navigation.** The top admin bar gained a full Velox menu (Dashboard,
  Images, Media Editor, Performance, Database, SEO, Utilities, Settings) with a
  Performance & Cache submenu, plus a separate Velox Maintenance item (Settings +
  Activate/Deactivate). The left sidebar gained a hover-flyout listing your active
  utilities, each linking straight to its settings. Snippets is no longer a
  top-level menu — it lives under Utilities like the other tools.
- **Snippets, cleaner.** Name-led rows with quiet metadata, an inline on/off switch,
  and a single “⋯” menu instead of five competing buttons. Editor and Safe-Mode
  banner adopt the shared styling.
- **Mail & forms, with a real inbox.** A single inbox of every submission across all
  forms — who, when, which form — and a detail view of everything they filled out.
  CAPTCHA is now a master toggle that locks the per-form option (and the builder
  field) when it’s off, enforced on the server too.
- **Cookie banner, real layout control.** A Preset/Custom switch unlocks page-builder-style
  controls — display (flex/grid/block), direction, align, justify, gap, grid columns,
  padding and margin — all driving the real banner CSS, live-previewed identically to
  the front end.
- **Backup, rebuilt.** Clear export controls, progress modals with a time estimate,
  import a backup from another site, a full restore history, unique friendly names,
  a demystified (and optional) safety snapshot, and a fix for the download button that
  used to vanish after a restore.

Per-release detail for each stage follows below.

## 2.29.0 — Redesign stage 7 (Backup & restore: rebuilt)
- **Clear export controls.** Export all / Export DB / Export files as a segmented control, plus Restore and Delete on every row.
- **Progress modals with a time estimate** for both creating and restoring — a real progress bar and a running “about Ns left”, instead of a tiny text line.
- **Import a backup from another site.** Upload a .sql or .zip made elsewhere; it is validated and added to the list, ready to restore or download.
- **Restore history.** Every restore is logged with when it ran, which backup, what was restored, how long it took, and whether it succeeded.
- **Unique, friendly backup names** (e.g. brave-otter-7c2) so snapshots are easy to tell apart.
- **Safety snapshot, demystified.** It is now clearly tagged “safety” in the list, explained in the restore dialog (it saves your current DB so you can undo), and optional via a toggle — no more confusing small unlabelled DB-only entry.
- **Fixed the disappearing download button.** Backups keep their SQL/ZIP buttons after a restore; the backups folder and its manifest are always excluded from archives, so a restore can never clobber them.

## 2.28.0 — Redesign stage 6 (Cookie banner: real layout control)
- **Oxygen-style layout controls.** The cookie banner now has a Layout panel with a Preset/Custom switch. In Custom mode you control the box like in a page builder: display (flex / grid / block), flex direction (row / column), align-items, justify-content, gap, grid columns, vertical & horizontal padding, and outer margin — all driving the real banner CSS, live-previewed and identical on the front end.
- Preset mode is unchanged, so existing banners keep their exact look until you opt into Custom.
- The new structural settings persist correctly and feed both the admin preview and the live banner through the same render path.

## 2.27.0 — Redesign stage 5 (Mail & forms: inbox + CAPTCHA gate)
- **Submissions inbox.** Mail & forms now opens with a single inbox of every submission across all forms — who sent it, when, and through which form — in a master list. Click any entry to read the full submission (every field they filled out) in a detail panel, and delete it from there.
- **CAPTCHA is now a real toggle.** A master CAPTCHA switch under Mail settings gates the whole feature: when it is off, the per-form “Require CAPTCHA” switch is locked and the CAPTCHA field in the builder palette is disabled (with a lock icon). When on (plus keys), forms can use it. The gate is enforced on the server too — a form can never demand a CAPTCHA that isn’t enabled.
- Inbox derives a sensible “who” from common name/email fields (including German vorname/name), with graceful fallbacks.

## 2.26.0 — Redesign stage 4 (Snippets rework)
- **Snippets list redesigned.** Now uses the shared page header so it matches the rest of the plugin. Each row leads with the snippet name and a quiet metadata line (location · priority · description); the type is a clean badge.
- **Cleaner row actions.** Activate/Deactivate is now an inline switch toggle; Edit, Duplicate, Export-as-plugin and Trash are tucked into a single "⋯" menu instead of five competing buttons.
- **Editor redesigned** with the shared header + back link, grouped into panels, with a sticky Save bar. The type-picker modal and Safe Mode banner adopt the unified Apple styling.
- A proper empty state instead of a bare line of text.

## 2.25.0 — Redesign stage 3 (one design system across every screen)
- **Whole-plugin visual system unified onto the Apple language.** Every shared component — page headers, cards, panels, buttons, inputs, selects, ranges, toggles, alerts, sidebar nav, toasts — now resolves through one token set: parchment surfaces, near-black ink, the 5/8/11/18 radius ladder, Apple spacing, weight-600 display type with tight tracking, and a near-flat elevation model. Single accent #2ab7f1.
- Removed the decorative radial-gradient glow from the dashboard hero (Apple uses no decorative gradients — depth comes from surface and type).
- Normalised every screen: ~15 different hard-coded corner radii collapsed onto the ladder, all 800-weights brought to 600, duplicate/!=token toggle colours unified — so pages stop looking like separate designs and read as one product.

## 2.24.0 — Redesign stage 1–2 (foundation + admin bar)
- **Design foundation re-based onto the Apple design language** (per DESIGN-apple.md): parchment canvas, near-black ink, Inter tuned to approximate SF Pro with negative display tracking, the 5/8/11/18 radius ladder, Apple spacing (4/8/12/17/24/32/48/80), and a near-flat elevation system (hierarchy from surface + hairlines, one soft shadow for overlays). Single accent kept at #2ab7f1. All existing token names preserved so every screen inherits the new system.
- **Top admin bar rebuilt.** Velox now opens a dropdown: Dashboard, Images, Media Editor, Performance, Database, SEO, Utilities, Settings, plus a Performance & Cache submenu (Performance settings, Clear all cache, Clear minified CSS/JS, Regenerate Oxygen CSS, Clear Cloudflare cache, Clear Velox cache). A separate Velox Maintenance item sits beside it with Settings + Activate/Deactivate.
- **Left-sidebar Utilities hover-flyout.** The Velox menu item shows an arrow and a hover popover listing every active utility (each links into its settings) when one or more are on; when none are active there is no arrow, and Utilities stays a normal clickable link.
- **Snippets is no longer a standalone top-level menu** — it is reached from the Utilities tab like the other tools; its page stays routable so all existing links keep working.

## 2.23.0
- **Backup & restore (new utility).** Back up your database, your files, or both. The database is dumped in pure PHP (no mysqldump needed) with each table's CREATE plus batched INSERTs; files are archived with ZipArchive, excluding the backups folder itself, caches and VCS/node_modules junk. Download any backup as .sql or .zip, delete old ones, and restore the database and/or files in one click.
- **Safety on restore.** Before overwriting the database, Velox automatically takes a fresh safety snapshot first, so a bad restore can itself be undone. File restores guard against path-traversal in the archive.
- **Scheduled backups.** Off / daily / weekly / monthly via WP-Cron, choosing DB, files or both, with a keep-newest-N retention that prunes older backups automatically.
- Backups live in a password-protected folder under wp-content (Deny-from-all + index.php), with unguessable filenames. Everything is removed on uninstall.

## 2.22.0
- **Export a snippet as a standalone plugin.** Every snippet now has an "Export" action that downloads it as a self-contained WordPress plugin (.zip) — install it on any site, no Velox required. Works for all four types: PHP runs on init with the right admin/front guard, while CSS/JS/HTML are emitted verbatim on the matching hook. The snippet body is wrapped so it is output exactly as written and can never break out of the generated file.

## 2.21.0
- **Migrate from another plugin.** A new panel in Settings imports your existing configuration from WP Rocket (cache lifespan, exclusions, defer/delay JS, lazy-load, font preloads, DNS-prefetch), Yoast SEO (sitemap on/off plus per-page SEO titles, descriptions and noindex), and WP Mail SMTP (host/port/encryption/auth/From, brought in as a Velox mail connection). Velox only reads the other plugin, never changes it, and won't overwrite Velox values you already set. Caching and SMTP are left switched off so you can review the imported exclusions first.
- **Script Manager — target whole post types.** Rules can now match by post type and archive, not just page ID/slug: use tokens like type:product, type:post, type:product:archive, plus blog, archive and shop. So "disable Contact Form 7 except on type:page" or "only on type:product" now works without listing every page.
- Cleaned up Safe Mode options on uninstall.

## 2.20.0
- **Snippets — Safe Mode.** A bad PHP snippet can no longer lock you out. Velox now drops a breadcrumb right before running each PHP snippet, so even a hard crash that takes the whole process down is traced on the next load and that snippet is switched off automatically. If snippets crash twice in a row, Safe Mode kicks in and skips all PHP snippets (CSS/JS/HTML keep working) until you clear it. You can also force Safe Mode with ?velox-safe-mode=1 in the URL or by defining VELOX_SNIPPETS_SAFE_MODE in wp-config.php.
- **Snippets — Safe Mode rescue bar** on the list screen with one-click "switch off all PHP snippets" and "clear Safe Mode" buttons.
- **Snippets — new "Add snippet" type picker.** The old dropdown is now a proper modal with a card per type (PHP / CSS / JS / HTML), each with a short description of what it does and where it runs.
- Cleaner snippet list rows (hover state, tighter alignment).

## 2.19.0
- **Cookie banner — the live preview now IS the banner.** Previously the preview was a separate hand-built mock-up that drifted from the real banner. The banner's CSS and HTML now come from one shared renderer that both the front end and the preview use, so what you see in the editor is byte-identical to what visitors get — including placement, offset, width, shadow and the dimmed-overlay modal.
- **Cookie banner — responsive controls + device preview.** New desktop/mobile preview tabs, a separate mobile placement (e.g. floating box on desktop, bottom bar on phones), and controls for box/modal width, base font size, and full-width buttons on mobile. Two more placements added: top bar and top-left/top-right floating boxes.
- **Fixed: SEO robots.txt + sitemap enable toggles did not persist** (no save handler — they snapped back on reload) and a missing variable that left the Apply / Regenerate buttons unbound. Both fixed.

## 2.18.0
- **Multi-step forms.** Add a "Page break" field to split a form into steps. Visitors get Next / Back buttons and a numbered progress bar; each step is validated before they can advance. Step titles show in the progress bar.
- **Calculation fields.** A read-only field that computes a live result from other fields using a simple formula — e.g. `{quantity} * {price}` — with optional prefix/suffix (€, /mo, etc.). Updates as the visitor types and is recomputed safely on the server (pure arithmetic, never executes code).
- **Entries CSV export.** Each form's entries can be downloaded as a CSV (UTF-8 with BOM so Excel reads Umlauts correctly), columns in form order plus submitted-at and IP.
- This completes the core Mail & Forms rework: SMTP routing + builder with conditional logic, validation, multi-step, calculations, notifications, entries and export.

## 2.17.0
- **Form builder — conditional logic.** Any field can now show or hide itself based on the answers to other fields. Per field you can choose show/hide, match all or any rules, and stack multiple rules with operators (is, is not, contains, greater/less than, is empty, is not empty). Hidden fields are skipped on submit, so a hidden required field never blocks the form. Logic is enforced on the server too, not just in the browser.
- **Form builder — field validation rules.** Text/phone/URL fields gain min/max length and an optional regex pattern (with a custom error message); number and date fields gain min/max value. Enforced both in the browser (native attributes) and server-side.
- These build on the existing 3-pane builder (palette, canvas, inspector), 16 field types, drag-to-reorder, merge tags, and admin + auto-reply notifications.

## 2.16.0
- **Mail rework — multi-connection SMTP with routing + fallback.** You can now add multiple SMTP connections and route mail to them by the From address or name (e.g. send billing@ through a transactional provider and newsletters through another). If a send fails, Velox automatically retries through a designated fallback connection. Existing single-SMTP setups migrate into one connection automatically — nothing to redo.
- **Send log upgraded.** Each logged message now shows which connection sent it, records the From address and any error, and can be re-sent with one click.
- **Fixed the duplicate "Mail & forms" heading** on the mail dashboard.
- Rebuilt the cramped single-column SMTP form into a clean connections editor (named connections, grouped fields, primary/fallback selectors, per-connection test send).

## 2.15.0
- **Rework foundation (Stage 0).** Formalised the Velox design system as one set of tokens: a full neutral grayscale ramp, 4/8 spacing scale, 6/10/16 radii, a proper shadow ramp (+ shadow-as-border), and motion tokens (150/200ms). #2ab7f1 is now strictly an accent (fills, active nav, focus) with a separate accessible token for accent text/links — it is never used as body text on white.
- **Top admin bar slimmed to just Velox + a Maintenance toggle.** All module and utility navigation now lives exclusively in the in-plugin left sidebar (no more duplicated nav and cache submenu cluttering the WordPress top bar). Cache clearing remains on the Performance page.
- Removed a decorative card hover (lift + colour shift) in favour of calm elevation, per the project UI rules.

## 2.14.2
- **Export the whole WordPress media library, not just images found on pages.** Scraping page HTML only ever finds images those pages actually place, missing library items used elsewhere or nowhere. The media export now reads every attachment straight from the WordPress media library (the originals), so your October Media library mirrors your WordPress one.
- Page/CSS references (including resized variants) are still mapped onto the corresponding library file; any image referenced but outside the library (theme/CDN) is resolved and added on top.
- Test connection now reports the full library count and how many are referenced on the homepage.

## 2.14.1
- **Collapse WordPress responsive-image variants.** The raw URL scan was grabbing every size WordPress generates (`-300x200`, `-768x512`, `-scaled`, …), inflating the media count many times over. The converter now folds all size variants of an image down to a single full-size file, while still remapping every variant URL on the page to it — so the count reflects real images, not thumbnails, and nothing breaks.
- Duplicate URL forms (absolute vs. root-relative of the same file) are de-duplicated too.

## 2.14.0
- **Images now target the OctoberCMS Media library, not the theme folder.** Captured images are delivered as a separate **Download media** zip you unzip straight into `storage/app/media/` — they appear in the backend *Medien* manager under a folder named after the project.
- Pages reference them with the `|media` filter (`{{ '<project>/NAME'|media }}`) and CSS backgrounds resolve to `/storage/app/media/<project>/…`, matching how a hand-built October theme uses the Media library.
- Each build now offers two downloads: **Download theme** (into `themes/`) and **Download media** (into `storage/app/media/`). Fonts still ship inside the theme at `assets/fonts/`.
- BUILD-INFO and INSTALL updated to spell out the two-part install; deleting a build also removes its media zip.

## 2.13.9
- **Media diagnostic in Test connection.** It now scans your homepage and reports how many image/font URLs it finds vs. how many resolve to bundleable files, with sample filenames — so an image problem is immediately visible as either a capture issue or a resolution issue.

## 2.13.8
- **Much more reliable image capture.** Instead of parsing known lazy-load attributes one by one, the converter now scans the raw HTML for *every* same-origin image/font URL — in any attribute, inline `style` background, `srcset`, or a slider’s `<script>` JSON config (including JSON-escaped slashes). This is what was missing lazy-loaded product/slider images.
- Root-relative (`/wp-content/...`) and protocol-relative (`//host/...`) asset URLs are now captured too.

## 2.13.7
- **New: rename-map editor for converted themes.** On any build, click **Edit names** to see every class and ID the converter found (with usage counts), give them human names, and download a renamed version.
- Renames are applied to the HTML pages **and** the CSS/SCSS in lockstep, so the design never breaks — `.oxy-foo` and `.oxy-foobar` are told apart (word-boundary matching), and `id` attributes plus `#anchor` references are kept in sync.
- **Live preview** re-renders as you type, so you can see a rename land before committing.
- Each rename export is saved as a new version (your original replica is preserved and still revertable).

## 2.13.6
- **Fixed: the v2.13.5 regression that wiped all styling.** The class/ID "cleanup" was stripping the auto-generated IDs and classes that Oxygen (and most builders) key their CSS to, leaving the page completely unstyled. That destructive pass is reverted — classes and IDs are kept so the design renders; only no-op page-builder data-* attributes are cleaned.
- **Fixed: jQuery leaking as visible text.** Scripts are now stripped from the raw HTML *before* the DOM parser runs (DOMDocument mis-parses Oxygen inline scripts into stray text nodes, so removing script *nodes* did not catch them).
- **Fixed: stylesheets not loading.** The head no longer depends on the `seoTags` component (a plugin that may be absent); if it errored, every `<link>` after it stopped rendering. The head is now self-contained (title + meta + Bootstrap + fonts + converted CSS).
- **Better media capture:** real image URLs hidden in `<noscript>` lazy-load fallbacks are now collected too.

## 2.13.5
- **Fixed: leaking JavaScript text in the export.** All `<script>` / `<noscript>` are now stripped during conversion (the Oxygen jQuery menu code was bleeding in as visible text). A static theme carries none of the original JS.
- **Fixed: 0 media on lazy-loading sites.** Image collection now reads `data-src`, `data-lazy-src`, `data-srcset`, `data-original` and `data-bg*`, so lazy-loaded images (Oxygen / WP Fastest Cache) are captured.
- **Deeper asset capture:** background images referenced in CSS and same-origin **font files** are now downloaded into `assets/images` / `assets/fonts` (fetched over HTTP when not a local uploads file), and CSS url() refs are rewritten accordingly — fixing broken fonts/backgrounds.
- External stylesheets (Google Fonts, etc.) are kept as `<link>`s in the head instead of being dropped.
- **Bootstrap 5** (CSS + JS bundle) is now included in the theme head/scripts.
- **Cleaner markup:** WordPress/page-builder junk classes (menu-item, current-menu, wp-block wrappers, page-id…), generated IDs (pro-menu-269-83 and similar) and builder data-attributes are stripped, while the structural classes the converted CSS relies on are kept so the design still renders.

## 2.13.4
- **Fixed: imported OctoberCMS theme showed no styles.** The CSS was referenced as SCSS compiled on the fly, and any SCSS-incompatible syntax in the site's real CSS meant zero output. The theme now links a plain `assets/css/style.css` that always loads; the `assets/scss/` sources are still included for editing.
- Each build now writes a **BUILD-INFO.txt** manifest (per-page markup size, CSS size, media count + total size) and an **INSTALL.txt** so the zip contents are verifiable and installation is unambiguous.
- Page content has a fallback to the full body when a site has no semantic `<main>` (page-builder/Oxygen layouts), so pages are never exported empty; stray `==` lines can no longer break OctoberCMS file parsing.
- The build result reports media actually packaged and total CSS size.

## 2.13.3
- OctoberCMS builder: the Test connection panel now reports the running Velox version and a published-count breakdown per post type, to pinpoint where site content lives.

## 2.13.2
- **OctoberCMS builder now scans every public post type**, not just Pages and Posts — custom post types (landing pages, portfolio, page-builder content, etc.) are included, so sites with content outside the standard Pages are captured in full. Slugs are de-duplicated across types.

## 2.13.1
- **Fixed: OctoberCMS builder returned an empty theme (0 pages / 0 media).** The crawler now uses a real browser user-agent and, when the public request is blocked or challenged (e.g. behind Cloudflare), automatically falls back to the **origin server** (127.0.0.1 with a Host header).
- The build now **fails loudly with a diagnostic** instead of silently producing an empty zip, and there's a **Test connection** button showing the public/origin response, pages found, and whether PHP DOM/Zip are available.
- CSS is now collected from **every** page (deduped), not just the home page, so per-page styles are included.
- Velox's own maintenance mode no longer hides pages from the builder's crawl.

## 2.13.0
- **New: OctoberCMS theme builder.** Scan the whole site and export it as an importable OctoberCMS theme. Every published page becomes a `pages/*.htm` with proper `url/layout/title` frontmatter, the shared header/footer/head are lifted into `partials/site/*`, and a `layouts/default.htm` ties them together with `onStart`, `{% partial %}` and `{% page %}`.
- WordPress-only markup (admin bar, wp-emoji, wp-json/REST/oEmbed/generator links, etc.) is stripped during conversion.
- **CSS → SCSS:** the site's stylesheets are concatenated and written into the theme's `assets/scss/` structure, with `:root` custom properties pulled out into `variables.scss` and `style.scss` importing the partials.
- **Used media only:** images referenced in the pages/CSS that exist in the media library are bundled into `assets/images/` and the references rewritten — unused files are skipped.
- **Versioned builds:** each scan is a version with start/finish time, duration, page count, media count and file size. Re-scan a project to pick up newly-added pages (it reports what's new), keep older versions as revert points, and download any version individually or all of them at once.

## 2.12.0
- **New: Cookie banner utility.** A fully styleable consent banner — bottom bar, floating box or centred modal — with editable heading, body, button labels, small print, a logo and two legal links. Every colour, border, radius and offset is configurable, with a live preview in the editor.
- Wired to **Google Consent Mode v2**: consent starts denied, GA4/GTM loads only the way Google expects, and the visitor's choice (Accept all / Reject / per-category Preferences) updates the tags and is remembered. Re-open from anywhere with a `#cookie-settings` link.
- **Admin layout:** the Velox panel now runs full-bleed (margin 0) and the content area spans the full page width with padding; the sidebar is unchanged.


## 2.11.0
- Mail & forms dashboard redesigned: stat tiles (forms, total entries, last 7 days), a clean forms table with per-form shortcode and entry counts.
- New per-form **Entries browser** — every submission with date, a one-line preview, and an expandable view of all submitted fields (labelled), plus IP and entry ID. Delete entries individually.
- Builder polish pass: cleaner white field-palette tiles with hover lift, calmer canvas, tighter top bar.

## 2.9.1
- **Fixed: Velox panel sat flush against the WordPress admin menu.** The wrapper had a negative left margin pulling it tight against the menu; it now sits with a clear gap on both sides.
## 2.10.0
- **Custom login now actually hides the site.** Logged-out visitors who hit /wp-admin are sent to the homepage instead of being bounced to your secret login URL — so the custom login path is the only way in and bots can't discover it from wp-admin.
- **Maintenance toggle stays put.** Activating/deactivating maintenance from the admin bar now returns you to the page you were on instead of jumping to the maintenance settings screen.
- **Sidebar spacing.** The Velox panel now sits with a clear gap from the WordPress admin menu (was flush against it).
- **Admin bar: active utilities** now appear as their own group directly in the Velox dropdown.
- **Cleaner inputs everywhere** (SEO, Mail, forms): hairline borders, softer radius, a refined focus ring, muted placeholders and a custom select chevron — following the Apple/Airbnb/Linear references. Field labels tightened to match.
## 2.9.0
- **More per-page SEO fields.** The SEO panel (both the Gutenberg sidebar and the classic meta box) now has Focus keyword (with a live in-title / in-description check), Canonical URL, and Social (Open Graph): social title, description and image. The front-end now outputs a canonical link plus Open Graph and Twitter Card tags, with sensible fallbacks (SEO title → post title, meta description, featured image).
- **Collapsible sidebar + more breathing room.** The Velox sidebar now has a collapse toggle (icons-only, remembered between visits), and the content sits with more padding so it isn't crammed against the admin menu. Content area widened.
- **Mail & forms cards refreshed.** The form list rows are now proper cards with hover lift and a tidier shortcode chip.
## 2.8.0
- **Custom login: fixed the 404.** Login links now carry a trailing slash (/your-slug/), which Nginx/Plesk routes to WordPress — a slashless path like /your-slug was being 404'd by the server before WordPress ever ran. The guaranteed recovery URL wp-login.php?your-slug still works too.
- **Snippets layout fixed.** The Snippets pages were sliding under the admin menu and losing their padding; they now use the same container as the rest of Velox, so the list and editor sit correctly on the normal grey background. The Add-snippet button no longer wraps.
- **Activity log removed.**
- **SEO is now a toggle in Utilities** and is off by default — Velox does no SEO meta, sitemap or robots.txt until you switch it on, so it won't clash with Rank Math/Yoast.
- **Admin bar.** The Velox menu now lists your active utilities, and Maintenance is its own always-present item with a status dot, Settings, and Activate/Deactivate so you can flip it from anywhere.
- **robots.txt: "View live" now opens /robots.txt** in a new tab (and still shows the inline check).
## 2.7.0
- **New: Code Snippets.** A full snippet manager for PHP, CSS, JS and HTML. Switch the utility on and it gets its own **Snippets** menu directly below Velox in the admin sidebar (same icon).
  - Create, edit, clone, activate/deactivate, trash, restore and permanently delete snippets. The list has All / Active / Inactive / Trash tabs with counts, and a Create button where you pick the snippet type (changeable later at any time).
  - Each snippet has a **run location** (Run everywhere / Only in the admin area / Only on the front-end / Only run once) and a **priority**.
  - Two save buttons: **Save snippet only** (saves without changing on/off) and **Save and Activate** — which becomes **Save and Deactivate** when the snippet is already on.
  - Real code editor (WordPress' bundled CodeMirror) with the right syntax mode per type. PHP is **syntax-checked before it's allowed to activate**, and a guarded runner auto-disables any snippet that throws or fatals — so a bad snippet can't white-screen the site. CSS goes in the head, JS/HTML in the footer (HTML is also available via `[velox_snippet id="…"]`).
## 2.6.0
- **Custom login URL — fixed properly + made un-lockable.** The hide redirect now sends no-cache headers, so a CDN/browser can never cache it and lock you out (that was the real cause). Added a guaranteed recovery URL — `wp-login.php?<slug>` — that hits the real login file directly and works even if the pretty URL is blocked by the server. Login submits, logout, and logged-in access are never blocked.
- **Bulk installer — fixed multi-install.** It now clears the leftover upgrader lock and stale maintenance flag before each install (the reason a queue installed the first plugin and errored on the rest), refreshes the plugin cache between installs, and reports the real error if one occurs.
- **Redirects — Edit & Visit.** Every redirect now has an **Edit** button (loads all of it — source, target, type — back into the form to change, including the target) and a **Visit** button that opens the source URL in a new tab so you can confirm the redirect actually fires.
## 2.5.1
- **Toasts everywhere + redesigned.** The toast notification was previously missing its styling; it's now a proper modern bubble with a type icon (check / cross / ! / info) and a clean slide-in, in success / error / warning / info colours. Every save, toggle, revert/reset, and delete now confirms itself — including two delete actions (a form submission, a 404-log entry) that used to vanish silently and now say "Removed."
## 2.5.0
- **Plugin clash detection.** Velox now spots other active plugins that overlap a feature you've switched on — a second caching/optimization plugin (WP Rocket, LiteSpeed, WP Fastest Cache, Autoptimize, Perfmatters…), a second SEO plugin (Yoast, Rank Math, AIOSEO…), another forms plugin (CF7, WPForms, Fluent Forms…), a rival maintenance/coming-soon plugin, or a hide-login plugin. It shows a **"turf war detected"** card on the dashboard and a dismissible admin notice listing exactly what overlaps what.
- It's smart about it: a clash is only flagged when the matching Velox area is actually **on**, so you won't get nagged about an SEO plugin if you're only using Velox for performance. Dismissals last until the set of conflicting plugins actually changes.
## 2.4.0
- **SEO now lives in the editor top bar.** A Velox button sits up by Save/Publish; clicking it opens a **Rank-Math-style sidebar panel** (where Page/Block live) with the Google preview, SEO title, meta description, Index/Noindex, Follow/Nofollow, and Exclude-from-sitemap — all bound to the post and saved when you save the post.
- The SEO meta is now REST-registered, and the old "Velox SEO" meta box is automatically hidden in the block editor (it still appears for the classic editor), so there's one clean SEO surface instead of a box buried under the content.
- The XML sitemap refreshes after editor saves so noindex / sitemap-exclude changes take effect immediately.
## 2.3.0
- **Form builder — big Fluent-style pass.** The field palette is now grouped into **General / Advanced / Layout** categories with a **search box**, and gained new field types: **Name** (first/last), **Multi-select**, **Country** (built-in list), **Website URL**, **Date**, and **Custom HTML**.
- **Column layouts.** Every field has a **width** — full, half (1/2) or third (1/3) — so you can place fields side by side (e.g. Vorname │ Nachname) without wrestling with containers.
- **CAPTCHA is now a field** you drop into the form, and it is **mutually exclusive with the consent box** — a form uses one or the other, never both. The inspector adapts per field type (HTML content editor, name sub-labels, per-field width, etc.).
- Notifications and `{all_fields}` correctly ignore presentational fields (HTML, CAPTCHA, consent); merge tags are built from real inputs only.
## 2.2.1
- **Fixed: custom login URL 404.** The interceptor was registered on a hook that had already fired, so visiting the secret slug 404'd. It now runs on `init` and serves the login page correctly. The feature also respects its Utilities toggle.
- **Maintenance mode upgrades.** Quick **on/off toggle in the admin bar** (under the Velox menu) with a live **green "Velox Maintenance" indicator** that only shows while it's active; **editable footer text** (no more forced site name); **Reset to default** button; **five loading animations** (bar, pulse, dots, spinner, none); and **GIF + Lottie** support for the logo/media.
- **robots.txt — live viewer.** New *View live robots.txt* button fetches what's actually served and tells you when the "content signals" block is coming from **Cloudflare** (not Velox), with the exact toggle to switch off. Velox's own robots.txt is already the clean standard, with the sitemap URL auto-filled.
- **Sidebar** widened again, and the "by Sumasearch" footer now sits in a padded container.
- **Forms now start empty** — no default fields, so you place everything yourself.
## 2.2.0
- **Mail & Forms — rebuilt as a visual form builder.** A three-pane builder: a field palette (single line, email, phone, number, paragraph, dropdown, radio, checkbox, consent) you click to add; a canvas where fields can be selected and dragged to reorder; and an inspector for per-field settings — label, field key, required, placeholder, default value, help text, options, half/full width, and a custom CSS class.
- **Notifications tab** for the admin notification and the customer auto-reply. Each has subject, body, from name/email, reply-to, CC and BCC, plus an *Insert field* menu that drops in merge tags built from the form's own fields — `{inputs.key}`, `{all_fields}`, `{site_name}`, `{date}`. The auto-reply's recipient is picked from a field dropdown.
- Front-end forms now support **radio groups, half-width fields, default values and help text**.
## 2.1.6
- **Maintenance mode is now fully customisable.** Set the heading, message, logo (defaults to the Velox mark, or pick your own from the media library), background / text / accent colours, an optional background image (auto-tinted so text stays readable), and an optional call-to-action button. A live preview updates as you type. Still sends a 503 and lets logged-in admins through to the live site.
## 2.1.5
- **Bulk installer now takes slugs, links *and* ZIP uploads.** Paste any mix of plain wordpress.org slugs, full wordpress.org plugin links, or direct `.zip` download URLs (one per line) — Velox figures out each one. A new upload field installs plugin `.zip` files straight from your computer, several at once. Every item reports its own success/error state in the log.
## 2.1.4
- **Navigation polish.** Wider sidebar (268px) with more generous padding, and nested utilities now have a guide rail so the hierarchy reads clearly. Tool sub-pages show a clickable breadcrumb (Velox / Utilities / Tool) instead of a plain back link — you can always see where you are and click back through any level.
## 2.1.3
- **Utilities rework.** Every utility now has its own on/off switch right on its card. Anything you switch on appears nested under **Utilities** in the sidebar (exactly like Media Editor) and opens from there; switch it off and it leaves the sidebar. Disabled tools show "Switch on to use" instead of an Open button.
- **Fixed:** only *SVG uploads* and *Duplicate* could actually be toggled before — the other nine utilities silently failed with "Unknown tool." All eleven now save correctly.
## 2.1.2
- **SEO editor box — granular robots controls.** The Velox SEO box on each post/page/product now has independent **Index / Noindex** and **Follow / Nofollow** segmented switches (not just a single noindex checkbox), plus "exclude from sitemap" and a live readout of exactly what search engines will be told. The `<meta name="robots">` tag is emitted only when it actually restricts something, and noindexed pages are kept out of the sitemap automatically.
## 2.1.1
- **Fixed: page cache now actually turns on.** It used to rely entirely on the advanced-cache.php drop-in, which needs a writable wp-config.php — not available on many Plesk/locked-down hosts, so the cache never activated. Velox now also serves cached pages through a fallback path that works everywhere. The drop-in is an optional speed bonus now, not a requirement; status reads "Active" the moment you switch it on. Logged-in users, the Oxygen builder, carts and your exclusions are all still bypassed.
- **robots.txt reliability.** Raised the `robots_txt` filter priority so Velox wins over other plugins, added a one-click "Write to physical file" option (more reliable behind Nginx and CDNs), and the editor now keeps the physical file in sync. If you see AI "content signals" text instead of your own robots.txt, that's Cloudflare's managed robots.txt overriding it at the edge — the SEO screen now explains exactly how to turn that off.

## 2.1.0
- **New SEO module (Rank Math-style essentials).** A dedicated SEO area in the sidebar:
  - **robots.txt editor** — served virtually by WordPress, pre-loaded with the recommended template (with your sitemap line), and it warns you if a physical robots.txt is shadowing it.
  - **Per-page SEO** — a "Velox SEO" box on every post, page and product with a live Google snippet preview: custom SEO title, meta description, noindex, and exclude-from-sitemap, with character counts.
  - **XML sitemap** — home page first, then published posts/pages/products A–Z, honouring the per-page exclude switch (and skipping noindex pages). Compatible with the existing `sitemap_exclude` meta, so sites already using that snippet keep working.
  - **One-click "Apply recommended setup"** — sets the robots.txt, enables the sitemap and generates it in a single click.

## 2.0.0 — The redesign
A complete redesign and a major leap in capability.

- **Velox is now its own app.** The seven scattered WordPress submenu pages are gone — Velox opens as a single experience with its own left sidebar: Dashboard, Performance, Images, Utilities, Settings.
- **A genuinely useful dashboard.** Live optimization score, cache status, image savings, active modules, one-click actions and impact-sorted recommendations — no more marketing hero or vanity tiles.
- **Native page cache — Velox is now standalone.** A real disk-based full-page cache served by an `advanced-cache.php` drop-in before WordPress loads, with gzip/Brotli, Oxygen-safe bypasses, exclusions, auto-purge and preload. Velox no longer needs WP Fastest Cache, WP Rocket or LiteSpeed.
- **Images optimization center.** Choose output formats (WebP/AVIF), pick the engine (Auto/Imagick/GD) with live compatibility info, set quality by slider or exact number, lossless mode — plus the existing bulk convert, library browser and before/after comparator.
- **One design system.** A proper colour system (the #2ab7f1 primary plus a harmonious secondary and accent), consistent spacing, badges, buttons, inputs and toggles across every screen. The Settings icon is finally a gear, not a sun.
- **Tidier information architecture.** Database now sits under Performance; Media Editor lives in the Utilities hub. Nothing lost.
- **Safety.** A collision guard prevents a stray second copy of Velox from fataling the site.

## 1.24.0
- **Consistency pass.** Media Editor now lives in the Utilities hub — toggle it on and open it right from there, instead of as a separate top-level item. Every screen now shares the same page headers, badges, buttons, inputs, toggles and panels, so the whole plugin reads as one consistent product.

## 1.23.0
- **Images optimization center.** The Images screen is now a full optimization center:
  - Choose output formats — WebP and/or AVIF — with your original JPG/PNG always kept as a fallback.
  - Pick the conversion engine (Auto / Imagick / GD) with a live compatibility list showing what each supports on your server.
  - Quality is now a slider *and* an editable numeric field, kept in sync — type an exact value or drag.
  - New lossless WebP mode (Imagick) for graphics and screenshots.
  - Max-width resize, metadata stripping, bulk conversion, the library browser and the before/after comparator all carry over unchanged.

## 1.22.0
- **Native page cache (Performance ▸ Cache).** Velox now has its own standalone, disk-based full-page cache — it no longer needs WP Fastest Cache, WP Rocket or LiteSpeed to make a site fast on its own.
  - Cached HTML is served by an `advanced-cache.php` drop-in *before* WordPress and plugins load.
  - Pre-compressed gzip (and Brotli where available) copies are served via content negotiation.
  - Oxygen-safe by design: the builder, logged-in users, query strings, WooCommerce cart/checkout, and your own URL/cookie exclusions all bypass the cache automatically.
  - Configurable cache lifetime, optional separate-mobile and logged-in caching, plus URL and cookie exclusion lists.
  - Auto-purges on content edits; one-click Purge and Preload (warm-up) actions. Purging "all caches" now clears the Velox page cache too.

## 1.21.0
- **Redesign — Dashboard.** Replaced the marketing hero and vanity stat tiles with a genuinely useful dashboard: a live optimization score (weighted across your highest-impact tweaks), one-click quick actions (purge caches, optimize images, clean database, tune performance), live image-optimization stats, and impact-sorted recommendations that link straight to the setting that needs turning on.

## 1.20.0
- **Redesign — foundation.** Velox is now a single in-app experience with its own left sidebar instead of seven separate WordPress submenu pages. Five areas: Dashboard, Performance, Images, Utilities, Settings — with Database nested under Performance and Media Editor under Utilities. Nothing lost, just reorganised.
- New **design-system tokens**: primary #2ab7f1 kept as the foundation, plus a harmonious secondary (indigo) and accent (amber), full semantic colours, an 8px spacing scale, and consistent radii.
- **Fixed the Settings icon** — it was drawing a sun; it's now a proper gear.
- Screen-by-screen redesigns (dashboard, the unified Performance area, native page cache, and the image optimisation center) follow in the next updates.

## 1.19.0
- **Mail & forms** (Utilities): a full form builder with live preview — text/email/phone/textarea/dropdown/checkbox/consent fields, drag-free reordering, and per-form accent styling.
- Per-form **notification emails**: an admin email (to you, with every field via `{all_fields}`/`{field}` placeholders) and a customer auto-reply (to the submitter's email).
- **SMTP** delivery with a send log and a one-click test email.
- Optional **CAPTCHA** (Cloudflare Turnstile or Google reCAPTCHA) — gated on your keys, plus a honeypot for spam.
- **Submissions inbox** in wp-admin, and a `[velox_form id="N"]` shortcode that works anywhere, including Oxygen.

## 1.18.0
- **Script Manager** (Utilities): disable specific CSS/JS handles globally, everywhere-except chosen pages, or only on chosen pages — matched by ID, slug or `front`. Discovers which handles actually load as the site is visited (plus a one-click front-page scan), so there's no guessing handle names.

## 1.17.0
- **Activity log** (Utilities): opt-in audit trail of logins (and failed logins), content publish/update/trash, plugin and theme changes, user changes and updates — with action filters. Self-prunes to a sane size.

## 1.16.0
- **Redirect manager** (Utilities): add 301/302/307/410 redirects by path, with hit counters. Matching is a fast in-memory lookup — no database query per request.
- **404 logger**: aggregates missing-URL hits by path (so the log stays small), with one-click "turn this 404 into a redirect" and a logging on/off switch.

## 1.15.0
- **AVIF support** (Images): optionally generate an AVIF twin next to each WebP. Modern browsers are served AVIF (typically 15–30% smaller again), with automatic fallback to WebP and then the original. Auto-detects whether the server can encode AVIF.

## 1.14.0
- **Bulk plugin installer** (Utilities): install a list of WordPress.org plugins by slug in one click, one at a time with live progress, optionally auto-activating each.
- **Blueprints**: save a slug list as a named blueprint and re-apply your whole agency stack on the next site.

## 1.13.0
- **Unused-media finder** (Utilities): scans for images nothing references and lets you delete them with a reclaimable-space estimate. Cautious by design — errs toward keeping anything that looks in use.

## 1.12.0
- **Maintenance mode** (Utilities): branded 503 holding page for visitors while admins keep seeing the live site.
- **Custom login URL** (Utilities): move wp-login to a secret slug to cut brute-force bot traffic.
- Utilities tools now open their own settings pages from the hub.

## 1.11.0
- **Use system fonts** (Performance → Fonts): skip web fonts entirely and fall back to the visitor's system stack for zero font requests.
- **CDN rewrite** (Performance → CDN): serve CSS, JS, images and fonts from a CDN host, with per-path exclusions.

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
