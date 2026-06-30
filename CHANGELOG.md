# Changelog

All notable changes to Velox. This file is the single source of truth — it shows
up both on the GitHub release and in the WordPress "View details" → Changelog tab.
Add a new section at the top for each release.

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
