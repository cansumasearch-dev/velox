# Changelog

All notable changes to Velox. This file is the single source of truth — it shows
up both on the GitHub release and in the WordPress "View details" → Changelog tab.
Add a new section at the top for each release.

## 3.77.0 — Templates can now wrap pages you never built in Velox
- **New catch-all option.** Turn it on and your default template also wraps pages that have no Velox layout — their normal WordPress content is placed in the template's Inner Content slot. That is how you get one shared navbar and footer across an existing site without rebuilding every page.
- **Off by default, deliberately.** Switching it on makes Velox render every page on the site, which is not something to do silently — WooCommerce checkout and cart pages included. The switch lives in the Velox Builder box on any page editor.
- Refuses to apply when the template has no Inner Content element, because the page content would have nowhere to go and would vanish. It says so instead.
- Any page can still opt out individually with "No template — this page only".
- **Fixed: the page editor contradicted itself**, showing "Not live with Velox" and a template selection at the same time with no explanation of why the template was doing nothing.

## 3.76.1 — Dropdowns on the dark screens
- **Fixed: the document-type and font dropdowns rendered as white boxes.** WordPress styles every select in the admin with its own light theme, which overrode the dark styling on the Velox Builder screens and stretched the rows.
- Rows on the Pages overview are now a consistent height.

## 3.76.0 — View as, and a Live column on the overview
- **"View as" now lists every page and post on the site**, grouped into Velox pages, WordPress pages and Posts — not just Velox-built pages. A plain WordPress page previews with its own content.
- **A preview bar now says what you are looking at**: "Previewing as [page]", a reminder that the content is not part of the template and is not saved, and a Stop preview button. The dropdown alone was too quiet a signal.
- The View as control is styled properly and grouped, rather than a bare dropdown.
- **Every row on the Pages overview now shows Live or Not live**, and hovering explains why — no layout attached, still a draft, or saved as a Template/Reusable rather than a Page.
- **Fixed: the top bar controls could overlap.** The centre cluster was positioned independently of the rest of the bar, so the right-hand buttons slid underneath it as the bar filled up.

## 3.75.0 — Font loading you control
- **Pick exactly which weights load** for each font family. Velox previously requested 400, 600, 700 and 800 for every Google font whether you used them or not — four files per family, on every page.
- Italics are now opt-in per family rather than never available.
- **font-display is now a choice** (swap, optional, fallback, block, auto) instead of always swap, with each option explained in plain language.
- **Preload** any family to fetch its stylesheet at high priority, with a no-JavaScript fallback.
- Pages using Google Fonts now preconnect to both Google hosts, saving a round trip before text can paint.
- Fonts saved before this release keep working and default to 400 and 700.

## 3.74.0 — Global JavaScript
- **New Global JavaScript manager**, alongside the existing Global CSS files. The code button in the builder now has CSS and JavaScript tabs.
- Each script can be named, switched on or off without deleting it, and set to load **in the head or the footer**, either immediately, after the page loads, or without blocking.
- Scripts run on every Velox-rendered page. They deliberately do not run inside the editor — a global script that rewrites the page would fight the builder — so test them on the published page.
- Only users who are allowed to post unfiltered HTML can save scripts.

## 3.73.0 — Three long-standing annoyances
- **Fixed: style sections closed themselves while you were typing.** The style panel rebuilds on every keystroke, and each rebuild reset which sections were open — so a section could collapse under your cursor mid-edit. Your open and closed sections are now remembered.
- **Fixed: two navbars when a template and a header/footer template were both set.** The older header/footer roles and the newer template system were both wrapping the page. A template now takes precedence; header/footer roles still work on their own when no template applies.
- **Fixed: the Insert Data panel opened in the wrong place**, offset to a position left over from the old sidebar that was removed in 3.67.0.

## 3.72.1 — View page opens the homepage for templates
- A template has no URL of its own, so **"View page" now opens the site homepage** while you are editing one, instead of doing nothing. The same applies to the View and Open frontend entries in the exit menu.

## 3.72.0 — Template previews, Inner Content rules, and a front-end diagnosis
- **The page editor now tells you whether Velox is actually serving the page**, and if not, exactly why: no layout attached, still a draft, or the document is saved as a Template/Reusable rather than a Page.
- **Front-end lookup made more forgiving**: it now also finds the layout through the binding stored on the page itself, resolves revision IDs when previewing, and lets logged-in editors preview an unpublished layout.
- **"View as" for templates**: pick any built page from the top bar and its content appears inside the template's Inner Content slot, so you can see how a real page sits in the frame. Preview only — nothing is saved.
- **Inner Content is now template-only** — it no longer appears in the element list for pages or reusables, it can only sit at the top level (never inside a section or div, including by dragging), and a template can only have one.
- **Open panels now dim the editor behind them** instead of letting it show through. Pinned panels do not dim, since they own their own space. Clicking the dimmed area closes the panel.
- **"Go to backend" returns where you came from**: the WordPress editor if you opened the page there, the Templates or Reusables list for those, and the Velox overview otherwise.

## 3.71.0 — Three fixes, including a white-screen crash
- **Fixed: a page could return a 500 error.** If a document was used as its own template, the Inner Content slot kept inserting the page into itself until PHP ran out of memory. The slot is now filled once and nested repeats are ignored.
- **Fixed: typing a unit into a number field produced values like "24%px".** Half-typed units are ignored until they are complete, so typing "px" over an existing "%" now lands on 24px instead of garbage.
- **Fixed: Backspace sometimes deleted the element you were styling.** Every keystroke rebuilt the style panel, which threw away the field you were typing in — the next Backspace then hit the delete-element shortcut. The panel now keeps your field and cursor position, and Backspace no longer deletes elements at all (Delete still does).
- **Change a document between Page, Template and Reusable straight from the overview**, without opening the editor.
- When no templates exist, the page editor now links straight to creating one and explains that an existing page can simply be converted.

## 3.70.0 — Inspector rework, drag-to-move, and a real page & class manager
**Editor**
- **Padding and margin now sit side by side** in a box layout, each with an **All / Individual** switch — one field for all four sides, or one per side. Your choice is remembered.
- Width, min-width and max-width share a row; height does the same. The style panel is wider.
- **The class area was rebuilt.** The active class used to be printed twice — once in a card, once as a chip — and nothing told you that editing a class changes every element using it. Now it says so, and the state picker is its own panel.
- **Drag elements anywhere in the page structure**: drop above, below, inside a container, or into empty space to pull something back out to page level.
- Elements can no longer be dropped into their own children.
- The "Add element" button is now flat text-and-icon rather than a filled block.

**Fixes**
- **Fixed: dragging in the page structure did nothing.** It was still wired to the old sidebar that was removed in 3.67.0.
- **Fixed: min-width and max-height were silently thrown away** — neither was registered in the style engine, so any value you typed vanished.
- **Fixed: choosing a unit before typing a number** snapped back to px.
- **Fixed: opening the Add panel closed a pinned Structure panel** even though they sit on opposite sides.

**Pages overview**
- Shows **every page on the site**, including ones never touched by Velox, with a Build button to start one.
- Filter by All / Velox pages / Templates / Reusables / Without Velox, plus search.
- **Rename, Duplicate, View, Delete and open in WordPress** straight from each row.
- **Fixed: the counters at the top always read zero.** They were hardcoded.

**Classes**
- Filter between **your own classes and the ones Velox creates** for new elements.
- **Edit** opens the class's full CSS — including :hover states and media queries — as editable text, and you can rename it in the same window.
- Anything unsafe or unrecognised in the CSS is dropped on save rather than stored.

## 3.69.0 — Units you can pick, and two template/reusable fixes
- **Units are now a dropdown.** Click the "px" next to any size field and switch to %, em, rem, vh, vw or auto. Typing a unit straight into the number field works too — enter "100%" and the % moves onto the chip by itself.
- **Ctrl/Cmd + D duplicates** the selected element.
- **Fixed: reusables lost their text.** Saved reusables only stored the structure and styling, so pasting one gave you correctly-styled empty boxes. They now carry their content, and a reusable you just created is insertable immediately instead of after a reload.
- **Fixed: a document could not be turned into a template.** The type was only ever set from the URL, so anything started via "New page" was stuck as a page. There is now a Page / Template / Reusable picker beside the title in the builder.
- Publishing a template or reusable no longer creates a stray WordPress page for it.
- Switching a document to "Template" makes it the site default if no default is set yet.

## 3.68.0 — Templates that actually wrap your pages
- **New "Inner Content" element.** Drop it into a template to mark where each page's own layout belongs. A template can now be a real navbar + content + footer instead of just a header and footer with a gap between them.
- **"Render page using template"** now appears on every page and post editor, under the Velox Builder box. Pick a specific template, opt this page out entirely, or leave it following the site default.
- **The first template you create becomes the site default automatically** — and because the default lives in one setting rather than being copied onto each page, pages you built *before* that template existed pick it up too.
- If a template has no Inner Content element, the page content is appended after the template rather than disappearing.
- The builder's "Add element" button is now a flat icon-and-text button in the top bar instead of a filled blue block.

## 3.67.0 — Builder: one left column, richer right-click menu
- **The left sidebar is gone.** "Add element" is now a button in the top bar, Reusables moved in with the other tool icons, and the duplicate Settings shortcut was dropped (it was already in the logo menu). The editor went from three stacked left columns down to one.
- **The style panel only appears when you select something.** With nothing selected the canvas gets the full width instead of showing an empty column. Add an element and the panel slides straight in for it.
- **Right-click menu rebuilt**: Copy, Paste, Duplicate, Delete, Rename, Export, Wrap with div, Hide, Make re-usable — with keyboard shortcuts shown next to each.
- **Rename** gives an element a friendly name in the page structure without touching its markup or classes.
- **Export** copies an element and all its styles as JSON to your clipboard.
- **Wrap with div** puts a container around an element in place.
- **Hide** keeps an element in the builder — ghosted, still editable — but leaves it off the published page entirely.
- Conditions is listed in the menu but not built yet; it is greyed out and marked SOON rather than quietly missing.

## 3.66.0 — Pin and collapse the builder panels
- Panels can now be **pinned**: a pinned Structure, History, Global CSS or Add-element panel pushes the canvas across instead of covering it, so you can style and see your page at the same time. The pin is shared — set it once and every panel opens the same way.
- New **Hide left panels** and **Hide right panel** buttons in the top bar slide the side panels off screen to give the canvas the full width. Shortcuts: Ctrl/Cmd + \ for the left stack, Ctrl/Cmd + Shift + \ for the right one.
- The right-hand toggle remembers which panel you last had open and brings that one back.
- Your pin and collapse choices are remembered between sessions.
- Fixed the missing WordPress icon, which showed as a blank square in the Add-element list, the page structure and on WordPress data elements in the canvas.

## 3.65.0 — Velox Builder: states, spine cleanup, styled inputs (wave F)
- State selector now includes :active out of the box plus a + button to add any custom pseudo-class (:visited, :nth-child(2), etc.); the generated CSS renders whatever states you use.
- Removed the separate Layers list from the left spine — element editing lives in the inspector and the full tree is in the Structure panel (reachable from the spine or the top bar).
- Styled the settings/inputs properly: focus ring, hover states, custom dropdown chevron, and clearer read-only fields. Inspector is wider for more room.

## 3.64.0 — Velox Builder: exit fix, contrast, duplicate, shortcuts + right-click (wave E)
- Fixed the Exit menu: Open frontend / Go to backend / View page now resolve to real URLs even when you opened the builder by document (they were dead when no post was bound).
- Right-click any element (in the canvas, layer tree or structure) for a context menu: Copy, Paste, Duplicate, Make reusable, Remove.
- Keyboard shortcuts: Ctrl/Cmd+S saves, Ctrl/Cmd+P publishes, Ctrl/Cmd+C copies the selected element, Ctrl/Cmd+V pastes it into the element you right-clicked/selected (inside it if it is a container).
- "Make reusable" saves the selected element (with its classes) as a reusable block.
- Raised the contrast of icons, labels and muted text so they are no longer hard to see against the dark panels.

## 3.63.0 — Velox Builder: rich text toolbar + Insert Dynamic Data (wave D, #1)
- Text elements now get a formatting toolbar in the left panel: bold, italic, underline, strikethrough, align left/center/right and link. Formatting is kept when you save.
- New "Insert Data" picker with the full dynamic-data list (Post, Featured Image, Author, Current User, Blog Info, Archive, Advanced). Inserting a field drops a token into the text that renders the real WordPress value on the front end (post title/content/excerpt/date, author, site info, custom fields, etc.).
- For safety, the "PHP Function Return value" token is inserted as a placeholder and is not executed on the front end.

## 3.62.0 — Velox Builder: real element settings (wave D, #5)
- Elements with their own settings now surface them properly. Selecting a Google Reviews element jumps straight to its Settings, showing a prominent Connection + Design preset picker (with a hint to set up a source under Velox → Utilities → Google Reviews if none exist).
- WordPress-data elements get a "Show which field" picker (post title / content / featured image / menu). Links/buttons get a URL field plus an Open-in-same/new-tab choice that is honoured on the front end.
- Element settings are grouped into clear sections instead of being mixed in with style controls.

## 3.61.0 — Velox Builder: element-specific inspector (wave C)
- The inspector now adapts to the selected element: each element type shows its style groups in an order that fits it, and picks sensible Essentials.
- Text and headings lead with Typography; sections/divs/columns lead with Layout; buttons foreground Typography + Background; images and video lead with Size. No more Display-first on a text element.

## 3.60.0 — Velox Builder: layout + chrome rework (wave B)
- Moved all working panels to the left: the Add element / Layers spine and the element inspector now sit side by side on the left, with the canvas filling the rest.
- The Structure panel now opens on the right.
- Brightened the whole editor a step and unified every button to one consistent style (no more mixed colours/gradients).
- Reworked the top-bar right side: tool icons, then Publish and View page, then an Exit icon on the far right. The Exit icon opens a menu: Open frontend, Go to backend (WordPress editor), or View page in a new tab.

## 3.59.0 — Velox Builder: undo/redo + revert fixes, structure panel, delete sync (wave A)
- Fixed the top-bar undo/redo arrows — they now step one change at a time instead of jumping two or doing nothing. Selecting elements no longer pollutes the history.
- Fixed session-history revert: reverting to a point now restores exactly that state instead of wiping the page.
- Added a Structure button in the top bar that opens a collapsible outline of the whole page; click a node to select it, click the caret to collapse/expand.
- Only one panel (add element, CSS, history, structure, page switcher) can be open at a time now — opening one closes the others.
- Overview no longer shows pages whose WordPress post was deleted, and delete now syncs both ways: deleting a page in the Velox overview trashes the bound WordPress page, and trashing/deleting a WordPress page removes its Velox document.

## 3.58.0 — Velox Builder: session history
- The history (clock) button opens a session history panel: it starts empty when you open a page, lists each edit you make (add, delete, style, class, text…) newest-first, and lets you click any point to revert to it.
- History is in-memory only — it clears when you close the editor, so nothing is stored on the server.

## 3.57.0 — Velox Builder: global CSS files editor
- The code (</>) button now opens a right-side Global CSS panel where you create named CSS files and write CSS that applies to every Velox page.
- Changes apply live in the editor canvas and save automatically; the concatenated global CSS is output on every built page. Basic sanitising strips any </style> or <script> breakout attempts.

## 3.56.0 — Velox Builder: entry/exit fixes + Edit-with-Velox meta box
- Opening a page with Velox now inherits that page’s title instead of resetting to “Untitled”, and Exit returns you to that page’s WordPress editor (not the builder home).
- Added an “Edit with Velox” meta box on the page/post editor (like Oxygen’s), showing Build or Edit depending on whether the page already has a Velox layout.
- The page switcher loads the chosen document into the editor and updates the current title.

## 3.55.0 — Velox Builder: topbar full-width + missing icons
- Fixed the top bar collapsing to a centred cluster — it now spans the full width, with the three zones (logo/title, breakpoints/undo-redo, actions) pushed to left/centre/right as designed.
- Added the search, code (page CSS/JS) and history icons to the top bar right zone (they were in the design but missing from the build). Code toggles the live-CSS panel; search opens the page switcher.

## 3.54.0 — Velox Builder: Google Reviews + WordPress elements render live (waves 3–4)
- The Google Reviews element now renders your real reviews on published pages, using the connection and preset you pick in the element Settings (via the [velox_reviews] shortcode). In the editor it shows a labelled placeholder.
- WordPress-data elements now pull live data: Post title, Post content, Featured image and Menu/Nav render from the current post on the front end, with labelled placeholders in the editor.

## 3.53.0 — Velox Builder: inspector tabs (wave)
- The inspector now has Essentials / All styles / Settings tabs: Essentials shows the common controls, All styles shows everything, Settings holds element-level options.
- Class chips can be removed with an × and new classes added with “+ class”.
- Settings tab: pick the heading level (H1–H6), set a link URL for buttons/links, and (for the Google Reviews element) choose a reviews connection and design preset from your Velox Reviews setup.

## 3.52.0 — Velox Builder: page switcher (wave 2)
- Click the caret next to the page title to open a switcher: search across pages, posts and reusables, filter by All / Pages / Posts / Templates / Reusables, and see your Velox documents with status badges.
- The switcher also lists WordPress pages and posts that aren’t built with Velox yet, each with a "Build →" action to open them in the builder.

## 3.51.0 — Velox Builder: new add-element panel (wave 1)
- Replaced the old flat add-element list with a searchable, categorized accordion panel that opens on the left: Containers, Text, Links, Media, WordPress and Velox categories, each collapsible with icon element cards.
- Many more elements: Grid, Spacer, Divider, List, Quote, Text link, Video, Icon, plus WordPress-data placeholders (post title/content/featured image/menu) and a Velox “Google Reviews” element.
- Type-to-filter search across all elements. (Note: Video, Icon, WordPress-data and Google Reviews elements insert now; their live rendering lands in the next waves.)

## 3.50.0 — Velox Builder: editor chrome overhaul (wave 1)
- Rebuilt the editor top bar into three zones: left = Velox logo (with a menu: Builder settings / Back to WordPress) + editable page title; center = breakpoints + undo/redo; right = Exit, View page, and the save/publish actions.
- Save/Publish behaviour: a brand-new page shows a single Publish button; once a page has been published it shows both Save (admin-only draft) and Publish (live for everyone).
- Left panel now has Add element, Reusables and Settings buttons plus a "Find layer" search that filters the layer tree.
- Bigger editor canvas (up to 1200px) with tighter padding.

## 3.49.1 — Velox Builder: editor full-width fix
- Fixed the editor not filling the screen — the canvas column was collapsing to content width, leaving a large empty area on the right. The three panes (layers, canvas, inspector) now span the full viewport.

## 3.49.0 — Velox Builder: name your pages
- Added an editable title field in the editor top bar. Pages, templates and reusables can now be named (and renamed) instead of all saving as "Untitled" — the name is stored with the document and shows in every list.

## 3.48.0 — Velox Builder: reusables & template apply-rules, working
- **Reusables now insert into pages.** The editor Add menu lists your reusable blocks; pick one and it drops in as a live reference — it renders inline (styled) in the canvas and on the front end. Editing the reusable updates every page that uses it.
- **Templates now apply as headers/footers.** On the Templates screen, set any template as the site header or footer. Built pages are wrapped with them automatically, and all their CSS is merged into the page's single stylesheet.
- The renderer compiles one only-used stylesheet covering the page, every reusable it references, and the active header/footer templates — so nothing is missing and nothing is duplicated.

## 3.47.0 — Velox Builder: all admin sections, working
- Restored the full Velox Builder menu — Overview, Templates, Reusables, Classes, Global styles, Fonts & icons, Settings — and built each one for real (no placeholders).
- **Classes:** a real manager listing every class used across your pages with usage counts; rename or delete a class site-wide (rewrites every page that uses it and regenerates their CSS).
- **Templates / Reusables:** create, list, open and delete template and reusable documents (stored by kind); the editor now tracks which kind it is building.
- **Global styles:** define colour tokens and a spacing scale that output as CSS custom properties (:root variables) on every built page.
- **Fonts & icons:** register font families (Google Fonts by name or a self-hosted CSS URL) that load on every built page.
- **Settings:** choose static-file vs inline CSS output, toggle minification, set the default container width — all persisted.

## 3.46.0 — Velox Builder: full-bleed admin, blank canvas, honest menu
- The Velox Builder admin section is now full-bleed: WordPress update nags, other plugins' notices, Velox's own notices and the footer are all hidden on Builder screens, and the panel fills the whole content area.
- New pages open on a blank canvas (no demo content). Empty pages show a clear "Add element" prompt instead of a white void.
- Removed the placeholder Templates / Reusables / Classes / Global styles / Fonts / Settings menu items — these were non-functional stubs and should not have been shown. The menu now surfaces only what works: the Overview (which lists your real built pages) and the editor.
- Overview lists your actual Velox Builder pages with their draft/published status; "New page" and "Edit with Velox" are the ways in.

## 3.45.1 — Velox Builder: admin layout fix + edit entry points
- Fixed the Velox Builder admin section rendering as a blank white area — the panel was absolutely positioned and collapsed out of the wp-admin layout; it now sits correctly in the content area.
- Added "Edit with Velox" entry points: a row action in the Pages and Posts list tables, and an admin-bar link when viewing or editing a page. Opening one binds (or creates) a builder document for that page so publishing updates the real page.

## 3.45.0 — Velox Builder: image element
- The Image element is now real. Double-click it on the canvas (or use "Choose image" in the inspector) to open the WordPress media library, pick an image, and it renders in place — stored as its URL and shipped as a plain, lazy-friendly <img> on the front end.
- The standalone editor now loads the WordPress media library (wp.media) into its own document so the picker works inside the full-screen builder.
- Empty images show a clear placeholder in the editor and simply output nothing on the live page until set.

## 3.44.0 — Velox Builder: hover & focus states
- Every element can now be styled per interaction state. A Normal / :hover / :focus switcher sits under the class chips; pick a state and any property you set applies only in that state, falling back to Normal otherwise — exactly like a hand-written stylesheet.
- States compose with the class cascade and breakpoints, so you can have (for example) a different button hover colour on mobile. The front-end renderer emits the matching :hover / :focus selectors, so states work identically on the live page — verified byte-for-byte against the editor output.
- Backward compatible: existing documents (which only had normal-state rules) load and render unchanged.

## 3.43.0 — Velox Builder: full style controls
- The inspector now covers a real styling surface — six sections: Layout (flex/grid, wrap, justify, align, gap, grid columns), Size (width/height, max-width, min-height), Spacing (all paddings & margins), Typography (size, weight, line-height, letter-spacing, align, transform, decoration, colour), Background & effects (background, opacity, box-shadow), and Border (width, style, colour, radius).
- Sections collapse to keep the panel calm; the first three stay open by default.
- Every property runs through the same cascade resolver (blue/orange/pink source dots) and the same static-CSS output, so all of it is class-first, responsive, and ships only-used CSS. The renderer whitelist and value sanitiser were extended to match — only known properties reach the page and values still cannot break out of a rule.

## 3.42.0 — Velox Builder: canvas text editing & drag-reorder
- **Edit text on the canvas:** double-click any text element (heading, text, button) to edit it inline, right where it sits. Enter commits, Escape cancels — and it flows straight into the document like any other change (undoable, saved, published).
- **Drag to reorder & renest:** drag any layer in the Layers panel to move it — drop above/below a sibling to reorder, or onto a container to nest inside it. Drop indicators show exactly where it will land, and a node moves with its whole subtree.

## 3.41.0 — Velox Builder: publish flow
- **Publish** now takes a page live end to end. Hitting Publish saves the latest edits, binds the document to a WordPress page (creating one on first publish), flips it to published, and writes the static CSS — so the front-end renderer immediately serves it.
- The editor reflects live state: a green "Published" button and a **View page** link straight to the live URL. Reopening a published document restores that state.
- **Unpublish** reverts the page to draft; visitors fall back to the normal theme. The bound page is tagged so Velox-built pages stay identifiable.

## 3.40.0 — Velox Builder: front-end rendering
- Saved Velox Builder pages now render on the live site. When a page is bound to a published builder document, Velox outputs its own standalone document — clean semantic HTML, no theme wrapper — via template_include.
- **Only-used CSS, as a static file:** the page CSS is compiled from the document and written to uploads/velox/builder/doc-{id}.css, content-hashed for cache-busting, and rewritten on every save. If the uploads dir is not writable it falls back to a single inline style block. Either way, only the CSS the page actually uses ships — keeping Core Web Vitals green.
- The PHP renderer produces byte-for-byte the same HTML and CSS (including responsive media queries) that the editor shows, so what you build is exactly what visitors get. Only known CSS properties reach the output and all values are sanitised, so the document model can never inject arbitrary CSS.

## 3.39.0 — Velox Builder: insert & save
- **Add elements:** the Add button (top bar and layers panel) now opens a picker — Section, Div, Heading, Text, Button, Image, Columns — and inserts into the current container or after the selection. New elements render on the canvas immediately and seed a starter class.
- **Duplicate & delete:** every selected element has duplicate and delete actions in the inspector header (Delete/Backspace works too); both are fully undoable.
- **Save & load:** documents persist to the database. Save (button or ⌘S) writes the full document — tree, classes and content — and the editor reopens it on the same route. Save state is shown live in the top bar.

## 3.38.0 — Velox Builder: the editing engine
- The Velox Builder editor is now live and functional. Opening the editor mounts a real three-pane workspace — layers spine, live canvas, and a class-first inspector — driven by a central store.
- **Live styling:** changing a value in the inspector regenerates and injects CSS into the canvas instantly (no reload), the way a modern builder should feel.
- **Class-first cascade:** the inspector computes where each style comes from — set on the active class (blue), inherited from a base/combo/wider breakpoint (orange), or an element-only override (pink) — matching Webflow-style resolution. Switching the active class re-points every indicator live.
- **Responsive:** per-breakpoint editing (Desktop / Tablet ≤991 / Mobile ≤767) writes real media queries, desktop-first.
- Undo/redo across the whole document. Element insertion, persistence (save/load) and front-end rendering are the next passes.

## 3.37.0 — Velox Builder (foundation)
- New opt-in module: **Velox Builder**, a clean class-first visual page builder. This release lays the foundation — enable it under Utilities and a dedicated "Velox Builder" section appears in the menu (Overview, Templates, Reusables, Classes, Global styles, Fonts & icons, Settings), and the full-screen editor opens on its own route with no WordPress chrome.
- The builder renders its own standalone document and is being built to ship only the CSS each page actually uses, keeping Core Web Vitals green. The editing engine and front-end rendering land in the next releases.

## 3.36.0 — Media Editor: choose how many images to show
- The Media Editor (Alt text & titles) now has a "Show" control: pick 20, 50, 100, or All, or type a custom number. "All" loads every image in the library on one page instead of the old fixed 40-per-page cap.
- Fixed: search on the Media Editor page was sending the wrong parameter name and being ignored — it now filters correctly.

## 3.35.0 — Google Reviews: Oxygen builder element
- Added a "Velox Reviews" element to the Oxygen builder. Drop it on a page, pick a Connection and a Preset from two dropdowns, and it renders your reviews live in the builder — the same output as the shortcode, so styling stays driven by the preset (one source of truth).
- The integration is defensive: it only loads inside Oxygen, and if the Oxygen element API differs on your version it degrades gracefully rather than breaking the builder — the [velox_reviews] shortcode always remains as a fallback.

## 3.34.0 — Google Reviews utility
- New utility: Google Reviews. Show your Google reviews on the front end as a styleable slider or static grid.
- Connect via Featurable (free, caches many reviews) or the Google Places API (official, max 5). Every connection must be named before it can be saved.
- Build reusable design presets (slider or static grid) with full control: number of reviews, minimum rating, columns, slides per breakpoint, autoplay + speed, card background/radius/padding/gap/shadow, text/meta/star colours, name/text/avatar sizes, and toggles for avatar, date and stars.
- Embed anywhere with a shortcode: [velox_reviews connection="…" preset="…"]. Reviews are cached for 6 hours so page loads never hit the API.
- Note: an Oxygen editor tab for placing reviews visually is coming in a follow-up; for now use the shortcode.

## 3.33.0 — Bulk SEO actions in the Posts/Pages lists
- Select any number of posts or pages and apply a Velox SEO change to all of them at once from the native Bulk Actions dropdown: set Noindex / Index, set Nofollow / Follow, and exclude from / include in the sitemap. A confirmation notice reports how many items changed, and the sitemap is rebuilt automatically after a bulk sitemap change.

## 3.32.0 — Webmaster verification + settings import/export
- New SEO panel: Webmaster verification. Add site-verification tags for Google Search Console, Bing, Baidu, Yandex, Pinterest and Norton Safe Web. Paste either the bare token or the full <meta> tag the provider gives you — Velox extracts the token and outputs a clean tag in your <head> on every page.
- New SEO panel: Import / Export settings. Export all your Velox settings to a JSON file and import it on another site to replicate a setup in one go. Imports are whitelisted against known settings and type-checked, so a stale or hand-edited file can’t inject junk.

## 3.31.0 — Resize fix, risky-row spacing, builder-aware frontend bar, sitemap toggle, full dashboard catalog
- Resizable SEO sidebar: the content inside now stretches to fill the new width instead of staying pinned at ~280px (no more empty gap).
- Performance risky mode: fields and toggles no longer sit flush on the yellow warning line — added proper padding so nothing overlaps it.
- Frontend admin tools now hide while you are editing a page in ANY builder (Oxygen, Bricks, Elementor, Divi, Beaver, Brizy, WPBakery, Cornerstone/Pro, Fusion/Avada, Thrive, Zion, Breakdance) and in the customizer preview.
- Posts/Pages list: the Index column is now "Index, Links & Sitemap" with a third toggle to include/exclude a page from the sitemap right from the list (blue "Included" / red "Excluded"), saved instantly and the sitemap rebuilt on the spot. The separate Sitemap column was merged into this.
- Dashboard "Everything in Velox" catalog now shows every utility — including Error Logger, Frontend Tools and PageSpeed — and displays switched-off utilities dimmed with an "Off" tag instead of hiding them.

## 3.30.0 — Editable Index & Links column, resizable SEO sidebar, sitemap independent of noindex
- The "Index" column in the Posts/Pages list is now "Index & Links" and is clickable: toggle Index/Noindex and Follow/Nofollow straight from the list, saved instantly, badges update live.
- The Velox SEO panel in the page editor is now horizontally resizable — drag its left edge to make it wider or narrower; the width is remembered per browser.
- Sitemap inclusion is now controlled ONLY by the "Exclude from sitemap" toggle. noindex / nofollow no longer affect whether a page is in the sitemap — a noindex page stays in the sitemap unless explicitly excluded. The sitemap column reflects this (a noindex page shows "In sitemap").

## 3.29.0 — More SEO columns in the Posts/Pages lists
- Added three columns and a length indicator to the Posts/Pages list (all shown only when the SEO module is on, toggled from Screen Options like the others):
  - Focus keyword — shows each page’s focus keyword (or "— none —").
  - Index — an Index / Noindex badge so you can spot pages accidentally hidden from search at a glance.
  - Sitemap — In sitemap / Excluded / Not listed (honest: a noindex page shows "Not listed" since it never reaches the sitemap).
  - Length badges on the SEO Title and Description columns: a colour-coded character count (green = good length, orange = short/long, red = way off) so bad-length meta stands out. Titles target 30–60 chars, descriptions 120–160.
- The three new columns are display-only; the SEO Title and Description remain click-to-edit inline.

## 3.28.1 — Sitemap updates instantly when you exclude/include a page
- Ticking "Exclude from sitemap" on a page now removes it from the sitemap immediately on save, and unticking adds it back — no manual Regenerate click needed. The bug: the sitemap was being rebuilt at the same moment the exclude value was written (same save_post priority), so it used the old value and lagged one save behind. The rebuild now runs late (priority 99), after every panel has saved its meta, so it always reflects the final state.
- Removed a now-redundant early sitemap rebuild that regenerated with stale values.

## 3.28.0 — "Force-include all pages" sitemap repair
- New button in SEO → XML sitemap: "Force-include all pages". If your sitemap only shows the homepage, this clears any leftover sitemap_exclude / noindex flags from every published page and post (including stuck maintenance-mode markers), switches the sitemap post types on, and regenerates. It reports how many hidden flags it cleared and how many URLs ended up in the sitemap.
- Hardened the sitemap query with suppress_filters + ignore_sticky_posts so another plugin's query filters can no longer accidentally empty it.

## 3.27.0 — "Exclude from sitemap" moved to the Velox page panel + maintenance safety
- The "Exclude this page from the sitemap" checkbox now lives in the Velox panel in the page editor (with the per-page optimization overrides), and has been removed from the SEO panel so there is only one control writing the flag — no more conflicting saves. The SEO panel still shows an "In sitemap / Not in sitemap" status indicator.
- Fixed the root cause of "sitemap only contains the homepage": Velox maintenance mode noindexes all pages while the site is under construction, and toggling maintenance off from the frontend quick-panel did not release them, leaving pages stuck noindexed and out of the sitemap. Turning maintenance off from the quick-panel now automatically releases every page it had hidden. (The Maintenance page still offers the deliberate keep/release choice.)

## 3.26.0 — SEO title & description columns in Posts/Pages lists
- The Posts and Pages list tables now show "SEO Title" and "SEO Description" columns (only when the SEO module is on). Toggle them from Screen Options ("Ansicht anpassen") like any column. Empty values show a clear "— add —" placeholder so you can see at a glance what still needs writing.
- Edit them two ways without opening each page: WordPress Quick Edit (the SEO fields are added there and pre-filled), or click a cell to edit it inline and save instantly (Enter to save, Esc to cancel; Ctrl/Cmd+Enter for the description).
- Also folds in the frontend admin bar fixes: the panel now renders reliably regardless of script load order, and the admin-bar toggle reloads so the page correctly reclaims/re-adds the reserved space and re-activates cleanly.

## 3.25.0 — Frontend admin tools (quick panel)
- New utility: a small arrow pinned to the bottom-left of the FRONT END, visible to admins only. Click it to open a quick-action panel and click again (or Esc / click away) to hide it.
- Panel actions: toggle the WP admin bar on/off (remembered per admin), purge the Velox cache, toggle maintenance mode, edit this page, open the Oxygen editor and Oxygen settings (shown only when Oxygen is active), open WordPress settings, and "View as visitor" which opens the current page rendered as a logged-out guest in a new tab (a signed guest render — it never touches your real login session).
- It is its own on/off utility (Utilities → Frontend admin tools), off by default.

## 3.24.4 — Fix "meta value could not be updated" when saving an unchanged SEO flag
- Publishing a page (typically a freshly-duplicated noindex/nofollow one) failed with the _velox_seo_noindex database error on the FIRST save when the flag was left as-is. Cause: when the value the editor sends already equals what is stored, WordPress's update_metadata() returns false (nothing changed), and the REST layer reports that as a database error. Toggling the flag made it a real change, which is why switching to index/follow and back "fixed" it. Two fixes: (1) duplicated pages now store the SEO flags in canonical boolean form, and (2) an update_post_metadata guard treats an unchanged flag save as success so the publish always goes through.

## 3.24.3 — Fix Duplicate not copying page-builder content
- Duplicating a page built with Elementor, Oxygen, Bricks, WPBakery or similar produced a blank copy. The meta-copy step ran maybe_unserialize() on each value before add_post_meta(), which stripped the escaped slashes out of builder JSON blobs (e.g. Elementor's _elementor_data) and corrupted them. Meta values are now copied raw with correct slashing, so PHP-serialized arrays AND builder JSON round-trip byte-for-byte. Gutenberg/classic pages were unaffected (their content lives in post_content, which was always copied correctly).

## 3.24.2 — Properly fix SEO flag save error (boolean meta)
- The 3.24.1 string-coercion approach did not fully fix the "Der Metawert von _velox_seo_noindex konnte in der Datenbank nicht aktualisiert werden" publish error. Root cause: the noindex / nofollow / sitemap-exclude toggles were registered as string meta, but the block editor and duplicate plugins send real booleans, and WordPress's REST round-trip comparison mismatched string vs boolean. These three flags are now registered as proper boolean meta (the standard Gutenberg toggle pattern) and the editor sends/reads real booleans. Existing '1'/'0' values still read correctly, so no data migration is needed.

## 3.24.1 — Fix "meta value could not be updated" on SEO flags
- Publishing a page (often a duplicated one) could fail with "Der Metawert von _velox_seo_noindex konnte in der Datenbank nicht aktualisiert werden." The noindex / nofollow / sitemap-exclude flags are registered as string meta, but a duplicate plugin or REST client can send a real boolean, which the strict schema rejected. Their sanitize now coerces any value (boolean, int, '1'/'0', 'true'/'false', 'on'/'yes') to a clean '1'/'0', so the save always succeeds. No data migration needed — existing values keep working.

## 3.24.0 — Import All-in-One WP Migration (.wpress) backups
- Backup & Restore now accepts All-in-One WP Migration .wpress files alongside Velox’s own .sql/.zip. Velox unpacks the .wpress container, extracts the database and wp-content files, and — the important part — rewrites the old site’s URL to this domain in a serialization-safe way (PHP-serialized option/widget values keep their correct byte lengths, so nothing corrupts). The result registers as a normal Velox backup and restores through the same path, with a safety backup taken first.
- Large files are streamed in chunks during unpack, so memory stays flat.

## 3.23.0 — AI crawler control & llms.txt (SEO)
- New AI crawlers panel in SEO: allow or block AI bots by what they do — training crawlers (GPTBot, ClaudeBot, CCBot, Google-Extended…), AI-search crawlers (OAI-SearchBot, PerplexityBot…) and on-demand fetchers (ChatGPT-User, Perplexity-User…). Your choices are added to robots.txt automatically, per group, with no hand-editing. Nothing is added when everything is allowed, so robots.txt stays clean.
- New llms.txt: an optional virtual /llms.txt, auto-generated from your pages (title, tagline, key pages and posts as Markdown links) and fully editable. It is served like the virtual robots.txt. Note: Google has said it ignores llms.txt, so this is for the AI systems that do read it, not a Google-ranking boost.

## 3.22.0 — Email log now captures every email, plus failure alerts
- The Mail & forms send log now records EVERY email your site sends — password resets, new-user notices, WooCommerce receipts and anything other plugins send — not just Velox contact-form and test emails. If an email goes missing, you can finally see whether it was even attempted and whether it failed.
- New "Alert me when a send fails" switch: emails the site admin when an outgoing email fails (rate-limited to once per hour so a burst can’t flood your inbox). The alert itself is never logged or looped.

## 3.21.1 — Error Logger in the sidebar & off-canvas
- The Error Logger now shows in the Velox sidebar and off-canvas menu under System once switched on, matching every other utility — it was reachable only from the Utilities hub before.

## 3.21.0 — Error Logger, and SEO moves into Utilities
- New Error Logger utility: catches every PHP error, fatal and failed API/HTTP request as it happens, grouped into clean accordions by type (fatal / PHP warnings / API & HTTP). Each error shows where it happened, how often, and — in plain English — what it means and how to fix it, from a built-in knowledge base of common WordPress issues. Switch it on under Utilities; clear all or dismiss errors one at a time. Appears in the Velox sidebar and off-canvas menu under System when switched on.
- PageSpeed is now a switch under Utilities like every other tool — turn it on or off whenever you want, and it appears in the sidebar under Essentials only when enabled. Its page is unchanged.

## 3.20.0 — Full German coverage across the entire plugin (PHP + JavaScript)
- Completed German translation of everything the user sees. Every PHP admin string (1035) and every JavaScript-rendered string (723) now has a German entry — 1727 total.
- Wrapped all the JavaScript UI that a PHP filter can never reach: toasts, the Custom Fields editor and field types, the Mail/SMTP setup, the block-editor SEO panel, the media picker, the snippets manager, and the dashboard. Each admin script now shares one translation dictionary through a small helper.
- Added bin/check-i18n.php, a regression guard that scans all PHP and JS for any translatable string missing a German entry, so future features can be checked before shipping.
- Fixed two translation-wrapping bugs found during the sweep: a local variable that shadowed the translate helper, and a value used in a comparison that must not be translated.

## 3.19.4 — Translate the "Everything in Velox" cards
- The catalog grid tiles on the dashboard (Custom Fields / Field groups, Media Editor / Alt text & files, etc.) were rendered from a hardcoded array with plain escaping, so both the names and descriptions stayed English. Both are now wrapped and translated.

## 3.19.3 — Translate the sidebar, dashboard stats and action messages
- The sidebar navigation labels and section headings (Utilities, Media Editor, Essentials, etc.) were rendered from hardcoded arrays and never translated — now wrapped and translated.
- Dashboard stat lines (grade, "N views", "vs last week", "updated X ago", "N of M optimizations on", "/ N pages", the intro subtitle) were bare English literals — now wrapped and translated.
- Added a JavaScript translation bridge (VELOX.i18n + a t() helper) and routed the action/confirmation toast messages through it, so those now appear in German too.

## 3.19.2 — Language actually applies everywhere + clearer switcher
- Fixed the translation only partially applying (most of the UI stayed English). The gettext filter was caching an early "no translation" decision made before the admin language setting was readable, which locked the whole page into English. It now resolves the chosen language only once it is actually available, so the entire admin UI switches to German.
- Removed the confusing "Follow WordPress" option — the switcher is now simply English / Deutsch.

## 3.19.1 — Restore clean pill look on the switcher
- Removed the visible chevron from the language switcher button so it keeps the clean globe + label pill look. The menu still opens on click, with the checkmark on the active language.

## 3.19.0 — Working English/German switcher, full German translation
- Rebuilt language switching from scratch. Instead of WordPress .mo files + locale (which never reliably applied because of just-in-time loading and caching), Velox now swaps strings directly through the gettext filter using a shipped PHP dictionary. This actually works, is scoped to Velox only (never touches the rest of wp-admin), and applies instantly on reload.
- Added German back with a complete dictionary covering every translatable string in the plugin admin UI — 1031 entries, nothing left out.
- New custom dropdown switcher (globe + current language + chevron, with a checkmark menu) that replaces the native select, so WordPress admin styling can no longer break its appearance.

## 3.18.3 — Language indicator is now a clean static pill
- Removed the dropdown entirely (it only had English anyway, and WordPress kept rendering its own select box + arrow inside the pill). The top-right indicator is now a simple static pill: globe icon + "English". The picker returns properly when more languages are re-added.

## 3.18.2 — Fix double-chevron on language switcher
- The switcher was showing two dropdown arrows (the custom chevron plus WordPress’s native select arrow) and splitting into two chunks. Forced appearance:none and removed the native background arrow so it renders as one clean pill.

## 3.18.1 — Reset to English + modern language switcher
- Removed all translation files and trimmed the switcher to English only, as a clean baseline while the language-switching mechanism is reworked.
- Redesigned the language switcher as a modern pill button that matches the Velox theme: white surface, hairline border, accent globe icon, custom chevron, and a subtle hover lift.

## 3.18.0 — Fixed 100+ untranslated strings across the whole admin
- The original translation pass missed ~103 hardcoded English strings (buttons, card titles, panel headings, tooltips, the cookie-banner builder, PageSpeed labels) — so things like "Purge caches", "Edit", "Recommendations", "Mobile/Desktop" stayed English in every language. Every one of those is now wrapped and translated.
- Regenerated the translation template with xgettext (canonical extraction) and rebuilt all 8 completed languages (German, Spanish, French, Italian, Portuguese, Dutch, Polish, Japanese) to 100% coverage of the human-readable admin UI.
- Turkish remains an English-fallback scaffold and will be completed next.

## 3.17.3 — Language switcher actually applies now
- Fixed the chosen language never taking effect (UI stayed English). WordPress 6.5+ loads translations just-in-time using the site locale, which bypassed the plugin_locale filter. Velox now explicitly unloads and force-loads the selected .mo up front in wp-admin, so the picked language is what renders.

## 3.17.2 — Language switcher background fix
- Removed the full-width white bar behind the language switcher. It was caused by reusing an existing .velox-topbar class that had its own background; renamed to .velox-langbar so the switcher now sits transparently in the top-right with no bar behind it.

## 3.17.1 — Language switcher scope + style fix
- Fixed the switcher changing ALL of WordPress admin instead of just Velox. Removed the determine_locale filter (which has no text domain and affected the whole admin); the language is now applied only through the domain-scoped plugin_locale filter, so it changes Velox screens and nothing else.
- Made the top-bar language switcher smaller and removed its background and border.

## 3.17.0 — Language switcher fix + moved to top bar
- Fixed the admin language never changing: translations now load on the init hook and the chosen language is applied via the determine_locale filter (which WordPress 6.x consults first in wp-admin), so picking a language now actually switches the UI instead of staying English.
- Moved the language switcher out of Settings. It now lives in a compact top bar, pinned to the top-right of every Velox page next to the header, with a globe icon. Changing it saves and reloads immediately.

## 3.16.9 — Japanese complete
- Japanese (ja) now covers 100% of the human-readable admin UI — every label, button, message, hint and long description, including all reconstructed sentences with placeholders. Uses WordPress-JP terminology (ダッシュボード, 投稿, 外観, 固定ページ) and consistent polite keigo (です/ます). Only literal code tokens stay untranslated.

## 3.16.8 — Polish complete
- Polish (pl_PL) now covers 100% of the human-readable admin UI — every label, button, message, hint and long description, including all reconstructed sentences with placeholders. Uses WordPress-PL terminology (Kokpit, Wpisy, Wygląd) and the correct 3-form plural rule. Only literal code tokens stay untranslated.

## 3.16.7 — Dutch complete
- Dutch (nl_NL, formal "u") now covers 100% of the human-readable admin UI — every label, button, message, hint and long description, including all reconstructed sentences with placeholders. Only literal code tokens stay untranslated.

## 3.16.6 — Brazilian Portuguese complete
- Brazilian Portuguese (pt_BR) now covers 100% of the human-readable admin UI — every label, button, message, hint and long description, including all reconstructed sentences with placeholders. Only literal code tokens stay untranslated.

## 3.16.5 — Italian complete
- Italian (formal "Lei") now covers 100% of the human-readable admin UI — every label, button, message, hint and long description, including all reconstructed sentences with placeholders. Only literal code tokens stay untranslated.

## 3.16.4 — French complete
- French (formal "vous") now covers 100% of the human-readable admin UI — every label, button, message, hint and long description, including all reconstructed sentences with placeholders. Only literal code tokens stay untranslated.

## 3.16.3 — Spanish complete
- Spanish (formal "usted") now covers 100% of the human-readable admin UI — every label, button, message, hint and long description, including all reconstructed sentences with placeholders. Only literal code tokens (robots.txt, type:post, etc.) stay untranslated.

## 3.16.2 — German 100% + sentence reconstruction
- Reconstructed 70 split sentence-fragments across all admin views into whole translatable strings (printf with placeholders for inline code/links), so long help text no longer mixes languages.
- German (formal "Sie") now covers 100% of the human-readable admin UI — every label, button, message, hint and long description. Only literal code tokens (dashicons, type:post, .htaccess, etc.) remain untranslated, as they must.

## 3.16.1 — German translation expanded
- German (formal "Sie") now covers 96% of the admin UI, including all the longer descriptions and hints. The remaining few are short sentence-fragments that will be completed in a later pass.

## 3.16.0 — Velox speaks your language
- New Language switcher in Settings: choose which language Velox's own admin screens are shown in, independent of your WordPress site language. WordPress and your other plugins are unaffected.
- English and German (formal "Sie") ship complete. Spanish, French, Italian, Portuguese (BR), Dutch, Polish, Japanese and Turkish are scaffolded and fall back to English until their translations are filled in.
- The entire admin UI — every screen, panel, label, button and hint — is now translatable, wired through the standard WordPress translation system.

## 3.15.2 — Snippets never silently deactivate themselves
- Velox no longer switches a PHP snippet off on its own. Previously a snippet could quietly flip to inactive after a runtime error, a leftover "execution breadcrumb" from an earlier request, or a shutdown-time fatal — often with nothing written to the error log, so it looked like the snippet "deactivated itself" for no reason. All three of those paths now log a clear `[Velox]` line (naming the snippet, the message, and the file/line) and leave the snippet active. You stay in control of what's on.
- Fixed the crash guard referencing an undefined `E_COMPILE` constant, which made the guard itself fatal instead of doing its job.
- Snippet syntax-checking now uses a proper tokenizer pass instead of an `if (false) { … }` eval wrapper, so validating a snippet can never collide with or redeclare the live copy.

## 3.15.1 — Maintenance now really does hide everything
- 3.15.0 put the hide-from-search behaviour behind its own switch that defaulted to off, so turning maintenance on did nothing. The switch is gone: maintenance mode hides everything from search, full stop.
- Pages now report noindex, nofollow the moment maintenance goes on, rather than only once the background pass had finished writing to every post — and it no longer depends on the SEO module being switched on.
- The "what should happen now?" question is worked out from the pages themselves instead of from a flag set at the moment you flipped the switch. If it was ever missed there was no way back to it; now it keeps asking until you answer, and closing it snoozes it only until the next time maintenance is touched.

## 3.15.0 — Maintenance mode can hide everything from search
- New switch on the Maintenance page: **Hide all content from search engines**. Turn it on with maintenance and every page, post and product is set to noindex, nofollow.
- Anything you create or duplicate while the window is open starts hidden too, so work done during a rebuild cannot go live in search before you have looked at it.
- Pages that were already set to noindex before you switched this on are left completely alone. Velox only records the ones it hid itself, so nobody's existing setting gets trampled and there is nothing to restore afterwards.
- Switching maintenance back off asks what should happen: make everything visible again, keep it all hidden, or pick page by page — anything left unticked stays hidden. The question appears wherever you switched maintenance off, including the admin-bar shortcut.
- Both jobs run in batches, so a site with thousands of posts will not time out.

## 3.14.1 — HTML lang switcher now appears in the navigation
- The new HTML lang switcher was reachable only from the Utilities hub. It now sits in the Velox side navigation under "Site & visitors" and in the dashboard shortcut grid, like every other tool.
- File Manager had the same problem and was missing from both lists since it shipped. It is now in the navigation under "System" and in the shortcut grid.

## 3.14.0 — HTML lang switcher
- New utility: **HTML lang switcher**. Sets the `lang` attribute on the `<html>` tag for sites where a theme or builder hard-codes it and the WordPress site language never reaches the page. Screen readers and search engines both read that attribute to decide what language a page is in, so a German site serving `lang="en-US"` is worth fixing.
- The page reads your live front page in the browser and shows the attribute that is actually being served, rather than assuming the setting worked — which is the whole reason the tool exists. A coloured dot flags whether it matches what WordPress thinks it should be.
- Pick a language from the dropdown, or type your own tag. A before/after line shows exactly what the `<html>` tag will look like once you save.
- Switches on and off from Utilities like every other tool, and does nothing at all while it is off.
- Fixed: `--vx-mono` was used in eight places across the admin stylesheet but never defined, so code chips, file paths and URL rows had been falling back to the normal interface font instead of monospace.

## 3.13.5 — SEO sidebar rebuilt to the approved design
- The score ring and the long checklist are gone. Status now reads as a row of pills — Indexed, In sitemap, No description — so the state of the page is one glance instead of seven lines.
- A Desktop / Mobile switch sits at the top and the search preview follows it, including the shorter description Google shows on phones.
- Sections are titled and separated properly, the field labels read as sentences with the character count on the same line, and the inputs got a proper reset so WordPress admin styling stops overriding them.
- Social preview and Advanced are collapsed rows with a summary of what is inside, and the focus keyword moved to Advanced next to the canonical URL.
- The sitemap control is now "Include in sitemap" and reads on when the page is included, instead of an inverted "exclude" toggle.

## 3.13.4 — The sidebar content actually widens now
- Dragging the sidebar moved its edge but the fields stayed narrow. WordPress re-applies its own width to one of the wrappers after Velox sets it, so the value Velox wrote was overwritten on the next render and the content snapped back to 280px.
- The width is now applied from a stylesheet rather than written onto the elements, which WordPress cannot overwrite. The preview and the fields fill whatever width you drag out, and stay there while you type.

## 3.13.3 — Dragging the sidebar wider now widens the content too
- Resizing the sidebar moved its edge but left the fields at their old width. WordPress puts wrappers between the sidebar and the panel that carry their own fixed width, and only one of them was being cleared. All of them are now, so the preview and the fields fill the space you drag out.
- The widths are re-applied if the editor re-renders and swaps those wrappers out, which previously snapped the content back to its original width mid-edit.

## 3.13.2 — Resizable SEO sidebar, and the counter bar stops crossing out the count
- The editor sidebar can now be dragged wider. WordPress fixes it at 280px with no way to change that, so Velox adds a handle on its left edge — drag to resize, double-click to go back to normal. The width is remembered, and only applies while the Velox SEO panel is open.
- A long search title used to push the whole panel sideways and leave a horizontal scrollbar under it. Long words now wrap instead.
- The character-count bar sat on top of the count itself and struck it through. The count has moved up next to the field label, where it reads as "48 / 60", and the bar sits under the input.
- The search preview shows the domain and a breadcrumb on separate lines, the way Google renders it, instead of one long URL broken mid-word.
- Search title and meta description gained placeholders showing what happens if you leave them empty.

## 3.13.1 — Field group header no longer goes dark on hover
- Hovering a Velox field group in the post editor turned its header near-black. Oxygen's admin stylesheet paints every meta box header with its dark colour on hover, and its selector outranked ours, so our styling never applied.
- The field group header now shows a light grey hover instead, with the title and the collapse arrow kept legible.
- The per-page "Velox" optimizations box was one selector away from the same problem and got the same guard.

## 3.13.0 — Velox SEO panel rebuilt
- The SEO sidebar now tells you how the page is doing instead of only holding fields. A score and a checklist sit at the top, in the same spirit as the SEO health screen but for the page you are editing.
- Each check names the consequence rather than the rule — "No meta description, Google writes its own" — and covers the search title, description, focus keyword, indexing, sitemap, whether the page has an H1, and whether its images have alt text.
- Missing title and missing description are now reported separately. Previously only the description was flagged, so a page with neither showed a single vague warning.
- Focus keyword moved up next to the fields it judges, and now reports whether it actually appears in the title and description.
- Character counts gained a bar under each field that turns amber near the limit and red past it, and the preview shows a proper empty state instead of instructions.
- Search engines, Social and Advanced are collapsed by default, so the checklist and preview are what you see first.
- Checks that need the page content (H1, image alt text) are skipped rather than failed on builder-made pages, which have no blocks to read.

## 3.12.4 — Actually beat Oxygen’s meta box styling
- The previous fix did not work. Oxygen ships "#editor .postbox > .postbox-header:hover { background: var(--oxy-dark) !important }", and since both rules were marked important the more specific selector won — which was Oxygen’s. Velox now matches that structure with two ids so its own box keeps normal colours on hover. Verified in a browser against the real rule; other meta boxes on the screen are left exactly as they were.

## 3.12.3 — Fix the Velox meta box turning dark
- The Velox box in the page editor changed colour on hover, with the header going black and the title becoming unreadable. That comes from other editor styling on that screen — Oxygen renders its own meta boxes dark, and it bleeds into every box on the page. Velox now pins its own box to normal WordPress colours so it stays readable whatever else is loaded, scoped to that box alone so nothing else on the screen is touched.

## 3.12.2 — Edit SEO opens the panel for you
- Clicking Edit SEO in the health list now opens that page in the editor with the Velox SEO sidebar already open, and puts the cursor in the first field that is still empty — so you land on the thing you came to fix instead of hunting for the panel.
- Works with both the current and older editor sidebar APIs, and retries briefly while the editor mounts rather than firing once and missing.

## 3.12.1 — SEO health: pages with no SEO at all
- Added two checks that were missing: pages with no search title, and pages with no SEO set at all — neither a title nor a description, where Google is guessing both. The summary now counts pages with SEO set against pages with none.
- Made the drill-down visible. Expanding an issue was a faint grey chevron that did not look clickable; it is now a proper "View pages" button that flips to "Hide" when open.
- Expanding is handled by one delegated listener on the list rather than re-binding every row on each render, so it cannot silently stop working.

## 3.12.0 — SEO health
- Added an SEO health panel to the SEO screen. It checks every published page and shows the counts at a glance: pages checked, indexable, set to noindex, with a description, and missing one.
- Below that, the issues worth fixing — pages with no meta description, images with no alt text, titles over 60 characters, duplicate titles competing for the same search, and anything set to noindex. Checks that come back clean are shown as Clear rather than hidden, so you can see they were actually run.
- Click any issue to see exactly which pages are affected, each with a link to edit its SEO and to open the page itself. Alt text links through to the Media Editor, which is where that gets fixed.
- The scan reads the database directly, so it is immediate and needs no crawling.

## 3.11.2 — Fix: resize controls missing from the media modal
- The resize controls added in 3.11.1 never appeared in WordPress’s Attachment details panel. The media modal builds that panel over AJAX, and the filter that adds the controls was deliberately skipped on AJAX requests — so the field was never generated. It is now registered for those requests too.

## 3.11.1 — Resize from the WordPress media library too
- The resize controls now also appear in WordPress’s own Attachment details panel, so you can resize straight from the media library or the picker in any editor, not only from Velox’s Media Editor.
- Same behaviour there: type a width and the height follows the original proportions, the link button unlocks it, quick presets from 50% to 200%, and the thumbnail refreshes in place once it is done.
- Only shown for images (never SVGs) and only to users who can manage options, since that is what the resize endpoint requires.

## 3.11.0 — Resize images from the Media editor
- Click any image in the Media editor to resize it. Enter a width and the height follows automatically from the original proportions (and the other way round); the link button turns that off if you want to stretch it deliberately.
- Quick presets for 50%, 75%, original, 150% and 200%, and hovering a thumbnail now shows its current dimensions.
- Images can be scaled up as well as down. WordPress refuses to enlarge through its normal resize call, so this scales through a full-frame crop instead.
- The file is replaced in place and its thumbnails and srcset are rebuilt, so the filename and every existing link to the image keep working. SVGs are refused, since vectors scale on their own.

## 3.10.6 — Two groups, working sizes, readable provenance
- Dropped the Possibly used group. Anything merely mentioned in the database but never rendered on a page now sits in Not in use, where it belongs — with a note saying where it was mentioned, so you can still see why it turned up.
- Renamed Not found to Not in use.
- Fixed the size total always reading 0 KB. The rewritten scanner returned a pre-formatted size string while the view still expected raw bytes, so every total added up to nothing. It sends real byte counts again.
- Fixed the "Seen on" line being cut off. It was locked to a single line with an ellipsis, so anything longer than the card was unreadable; it now wraps to two lines.

## 3.10.5 — Possibly-used cleaned up, archives crawled
- Builder templates are now excluded from the database passes as well, not just the crawl. Their stored layout data was still producing a database "mention", which pushed template demo images into Possibly used even though they appear nowhere on the site.
- The crawl now also reads archive pages: custom post type archives, the blog page, and category and tag listings. Images that only ever appear on a listing page — never on an individual permalink — were being missed and reported as unused.

## 3.10.4 — Stop crawling builder templates
- The crawl was walking page-builder template posts. Oxygen (and Elementor, Bricks, Divi, Beaver, WPBakery and block themes) register their templates and design libraries as public post types, so the crawler was opening each template on its own and marking whatever demo content it contained as "in use" — which is why artwork that appears nowhere on the site kept showing up as used, credited to a /?ct_template= URL.
- Builder template and library post types are now excluded, and any permalink that is a builder preview URL is skipped. Templates that are genuinely in use are still covered, because they render as part of the pages that use them.

## 3.10.3 — "In use" now means proven, not guessed
- Reworked how a verdict is reached. A string found somewhere in the database can no longer mark an image as in use — only real evidence can: the crawl actually rendering it on a page, or WordPress structurally pointing at it (featured image, product gallery, site logo or icon). Everything else that is merely mentioned somewhere is now "Possibly used".
- This is why wrong files kept appearing under Used: a mention in a draft, an old theme setting, or a stale builder CSS cache file was enough to promote an image to "in use".
- Stopped scanning page-builder CSS caches. Builders keep generated stylesheets for pages that no longer exist, so images from deleted pages looked used forever. The crawl reads the stylesheets pages genuinely load instead, which is both accurate and current.
- The media view now has three real groups — Not found, Possibly used, In use — and only Not found can be selected for deletion.

## 3.10.2 — The media scan now reads your actual pages
- Added a real crawl. The scan now reads every published page, post and custom post type, plus the homepage, and records which images are genuinely rendered — rather than inferring usage from database strings. Because it reads finished markup, it works the same for Oxygen, Elementor, Bricks, Divi, Beaver and anything else: by the time a page is output, it is all just img tags and url().
- The crawl runs from your browser rather than the server, so it is unaffected by hosts that block loopback requests — which is why the old front-end pass silently did nothing on some setups.
- Linked stylesheets are fetched and read too, so CSS background images are caught. That was the single biggest blind spot: a background defined in a stylesheet is mentioned nowhere in the database.
- Anything the crawl actually rendered is marked used outright and says which page it appeared on, overriding weaker database guesses.
- The summary now reports crawl coverage (for example "read 47 of 52 pages"), so "no reference found" is a statement about pages actually checked instead of an assumption.

## 3.10.1 — Media scan: stop images vouching for themselves
- Fixed the big one: every attachment stores its own file path in its own postmeta (_wp_attached_file, and every generated size in _wp_attachment_metadata). The scan read all postmeta, so every image referenced itself and the entire library came back "used". Attachment-owned meta is now excluded, along with revisions and trashed posts.
- A bare number in a custom field is no longer treated as an image reference. Prices, counts, order numbers and timestamps all look like attachment ids; it now only counts when ACF has registered a matching image field alongside it.
- An "id" in builder or block JSON only counts when it sits next to an uploads URL, so navigation items, form fields and settings no longer mark random images as used.
- The scanner no longer reads its own saved results, blueprints or transients back as evidence of usage.

## 3.10.0 — Unused media rebuilt
- Replaced the media scanner. It used to ask "for each image, does it appear anywhere?", which meant building huge content blobs, capping the work at 400 images and 20 pages, and guessing. It now walks every place a reference can live once, indexes what it finds, and checks attachments against that index — in resumable batches with a progress bar, so a large library cannot time out.
- Removed the dependency on loopback HTTP requests. The old front-end pass fetched pages over HTTP and silently skipped everything when the host blocked loopback (common on IONOS and similar), so on those sites it contributed nothing at all.
- Much wider coverage: posts and pages, custom fields and page-builder data, the options table (theme mods, widgets, customizer, Oxygen settings), category and user meta, theme and child-theme files, builder CSS caches, and Additional CSS. Featured images, product galleries and the site logo and icon are recognised as hard references.
- Fixed images being wrongly reported as unused. Resized, scaled and WebP-converted variants now normalise to the same key as the original, so an optimiser rewriting hero.jpg to hero.webp no longer makes the original look unused. JSON-escaped URLs and relative ../uploads/ paths in stylesheets are matched too.
- Fixed images being wrongly reported as used. Post revisions are no longer scanned, so an image removed from a page is no longer kept alive forever by an old revision.
- Results now say where a file was found, and separate "possibly used" (a weak match, such as a bare ID in a custom field) from "no reference found", so only genuinely unreferenced files are offered for deletion.

## 3.09.79 — Style panel controls stop fighting WordPress
- Fixed the number fields and dropdowns in the Style editor rendering wrong. WordPress’s own admin stylesheet puts a border, padding, line-height and a 30px minimum height on every input and select, and its selectors were outranking ours — so the input drew its own box inside the field and pushed the unit outside it, and dropdowns came out taller than everything else.
- All controls in the panel now carry a scoped reset that WordPress cannot override, so a control is always exactly one 32px box: value, stepper and unit inside a single border, and dropdowns matching the number fields. The folder manager’s name field uses the same reset.

## 3.09.78 — Gradient fixes and quieter number fields
- The gradient preview bar is now live. It was only redrawn when the whole panel re-rendered, so it kept showing the default blue-to-purple while your actual colours were something else entirely. It now follows the From, To, Type and Angle controls as you change them.
- Choosing Gradient seeds the From and To colours instead of leaving them on Inherit, so the bar and the form always agree.
- Fixed the Angle field showing px. A number field now defaults to the first unit it actually allows, so Angle shows deg and line height shows no unit at all.
- Fixed a stored angle like "135deg" being written into the preview as "135degdeg", which silently broke the gradient in the live preview.
- Toned down the unit chip in number fields. It had a grey pill background that made every field look like two separate boxes; it is now quiet text that only lifts on hover, so the value, stepper and unit read as one control. Non-px units still tint so they stand out.

## 3.09.77 — Folders: default names and a cleaner manager
- Folders left without a name are no longer silently discarded when you save. They get a sensible default (Folder 1, Folder 2 and so on) instead of vanishing.
- Rebuilt the folder manager to match the style editor: a proper header with a subtitle, colour swatches that show the chosen colour directly rather than a raw colour input, slim 32px name fields, an icon delete button that turns red on hover, a dashed Add folder button, an empty state, and a footer with Cancel next to Save.

## 3.09.76 — Deleted view fixed, and counts stop lying
- Fixed the Deleted button in the new rail doing nothing. The rail buttons were missing the class the filter handler looks for, and the handler was bound to the top bar only, so clicks in the rail never reached it. Inbox and Deleted in the rail both work now.
- Entry counts no longer include deleted submissions. A form showing "4 entries" with an empty list was counting four entries sitting in Deleted; totals, the per-form count and the last-7-days figure now count only live entries.
- Added a separate Deleted box beside the other stats, and the per-form entries page shows how many of that form’s entries are in Deleted.

## 3.09.75 — Inbox folder rail
- Folders and Deleted moved out of the top filter bar into a left rail, so they read as places you go rather than chips squeezed in beside the filters.
- The rail lists Inbox with a total count, then your folders as full rows with their colour dot, name and a count of what is in them, then New folder, with Deleted pinned to the bottom behind a divider. Deleted turns red when active.
- The top bar now carries only the status filters — All, Unread, Pinned and Done — each with an icon and count.
- On screens under 1180px the rail collapses to icons only so the reading pane keeps its room.

## 3.09.74 — Style fields rebuilt, icon actions, copyable shortcode
- Fixed the number fields in the Style editor. The stepper buttons had their own grey background, so every field read as three separate boxes instead of one control. The stepper is now transparent and only fades in on hover or focus, and the value, stepper and unit all sit inside a single bordered field.
- Dropdowns (Weight, Type, Position and the rest) now render without native browser chrome, so they are exactly the same 32px height as every other control. Verified across all nine controls in the panel.
- Deleted submissions use icon buttons with tooltips instead of full-width Restore and Delete forever buttons, and the "No submissions yet" panel no longer shows while viewing Deleted.
- Form rows use icon buttons for entries, edit and delete instead of three text buttons.
- The shortcode in the forms list is now click-to-copy, the same as in the editor, and flashes green when copied.

## 3.09.73 — Style editor: full control set, and custom styles win again
- FIXED: custom form styles were being overridden by the plugin’s own base stylesheet. The base input rule used a chain of :not() selectors, and each one adds specificity, so it outscored the per-form rules and silently killed them. The exclusions now sit inside :where(), which adds none, and per-form styles are printed after the base sheet instead of before. Anything you set in the Style editor now actually applies.
- Every target now has the full set of controls: background (solid colour, linear or radial gradient with angle and both stops, or an image with size, position and repeat), size (width, height, min height, max width), text (size, weight, line height, letter spacing, colour), shape (corner, border, border colour), spacing (padding and margin on all four sides) and shadow.
- Shadow keeps the None / Soft / Med / Strong presets and adds Custom, with X, Y, blur, spread, colour and inset, plus a live preview of the result.
- Colour rows are now laid out with the label on the left and the swatch, hex and reset pinned right, so the value no longer sits underneath the label text.
- Every control in the panel is the same slim 32px height — number fields, dropdowns and colour inputs all match, and one spacing scale is used throughout.
- Number fields gained stepper arrows and keyboard support: click the arrows or use up and down (hold shift for steps of ten).

## 3.09.72 — Mail & forms: live updates, inbox actions, delete fix
- Fixed entries not deleting. Deleting an entry soft-deletes it, but the entries list never filtered deleted rows out, so everything reappeared on reload. It now excludes them, and the confirmation says the entry moves to Deleted (restorable from the inbox) rather than claiming to delete permanently.
- The inbox and the per-form entries list now update on their own — new submissions appear and removed ones disappear without reloading the page.
- The inbox always shows its full interface. An empty inbox now shows a small message inside the list instead of replacing the filters, folders and reading pane with a paragraph.
- Inbox rows gained hover actions: pin, mark done, mark read/unread and delete. Folder assignment stays inside the opened message.
- The Deleted tab is now a proper outlined tab with a trash icon, and turns red when active, instead of blending into the filter bar.
- Fixed row hover using the same colour as the page background, which made rows look like holes in the panel. Added a dedicated hover token used by the forms table and entries list.

## 3.09.71 — Style editor rebuilt
- Replaced the tall list of style targets with a compact icon strip (Form, Title, Labels, Inputs, Button). The header is now about a quarter of its old height, leaving the panel to the controls.
- Added an "Applies to" dropdown for Labels and Inputs: style all of them, or pick one specific field. "All" stays selected by default so it is always clear whether an edit is global or per-field.
- The strip and the "Applies to" row are sticky, so you can switch target or field at any scroll position. They pick up a soft shadow once the controls scroll underneath.
- Controls are now collapsible groups (Colour, Text, Shape open; Spacing and Shadow folded) with paired values side by side instead of one long stack.
- Number fields have a clickable unit: switch any size, corner, border or spacing value between px, rem, em, % (and auto where it applies). Non-px units are highlighted so they stand out at a glance.
- Inherited values now read as inherited — a dashed empty swatch and grey "Inherit" text instead of a blue swatch labelled INHERIT. Anything you have overridden gets a revert button to put it back.

## 3.09.70 — Front end now matches the style preview
- The form’s front-end base styling now mirrors the Style editor preview exactly, so anything left on "inherit" looks the same in both places. Previously the preview had its own card look (white background, 18px corners, 40/56/44 padding, soft shadow, 48px inputs with 11px corners, stacked radios, centred pill button) while the front end fell back to a plain, much flatter default — which is why the two never matched and the default styling appeared to do nothing.
- Aligned: form card background, corner radius, padding and shadow; field and label spacing; label size/weight; input and textarea height, padding, border width, radius and font size; radio and checkbox layout (now stacked) and control size; help text; submit button size, radius, shadow and centring; and the form title.
- Submit alignment is now applied to the button itself (align-self) instead of the whole form, so Left/Center/Right/Full behave the same as in the preview.
- Added a "Show form title" toggle under Settings. The preview and the front end both respect it, so the heading no longer appears in one and not the other. It is off by default, so existing forms do not suddenly gain a heading.

## 3.09.69 — FIX: form styles never saved (empty-array bug)
- Fixed the real cause of form styles not persisting. An unstyled form was loaded with its style as a JSON array ([]) rather than an object. The Style editor set properties on that array, which worked for the live preview but were silently dropped by JSON.stringify on save (it ignores non-index array properties), so the database always received an empty style. The style is now coerced to a plain object on load, so edits actually save and reach the front end. Removed the 3.09.68 diagnostics.

## 3.09.68 — Diagnostic build for the style-persistence issue
- Temporary logging: the Style editor logs the style it sends on save, and the server returns the style it actually persisted, so we can pin down whether the style is lost in the browser payload or in storage. No functional change; to be removed once diagnosed.

## 3.09.67 — Fix: form styles stripped by Oxygen
- Form styles now print in the page footer instead of inline inside the shortcode output. Oxygen (and some other builders) strip inline <style> tags from shortcode/element content, which is why custom form styling never appeared on the front end even though it saved and previewed correctly. The styles are now emitted via wp_footer, which builders do not sanitize, so they reliably reach the page. Works together with the 3.09.66 cache purge.

## 3.09.66 — Fix: form styles not showing on a cached front end
- Saving a form now purges the page cache. Form styling is rendered inline into the page HTML, so a stale full-page cache (WP Fastest Cache, etc.) kept serving the old, unstyled version even though the styles were saved correctly. Velox_Cache::purge_all() now runs on every form save so the front end picks up style changes immediately.

## 3.09.65 — Settings panel grouped
- Organised the form Settings panel into clear labelled sections — After submitting, Spam protection, Embed — instead of one flat card, matching the grouped inspector and settings elsewhere. Added a hint under the shortcode.

## 3.09.64 — File upload field
- Added a File upload field. In the inspector you can set the allowed types (Images, PDF, Documents, or a mix) and a max size in MB. Uploads are validated server-side: size cap, an extension + real-content MIME whitelist (blocks disguised executables via wp_check_filetype_and_ext), is_uploaded_file check, and storage through WordPress’s own wp_handle_upload. The submission stores the file URL, which shows in notifications and the entries table. Front-end also checks size before sending. Every PHP file was verified with a real php -l linter before shipping.

## 3.09.63 — HOTFIX: fatal error from the new fields
- Fixed a fatal PHP error introduced in 3.09.61. Adding the Address field accidentally removed the "if multiselect" line in the submission handler, which closed the method early and crashed the plugin site-wide. Restored it and verified every PHP file with a real linter. Sorry again.

## 3.09.62 — Polish the new field styles
- Cleaned up the front-end styling of the new fields: bulletproof star-button reset (no more browser button chrome on the rating stars), tighter heading hierarchy, tidier address grid, and a clearer slider value badge. They inherit the form’s normal field spacing and whatever you set in the Style editor.

## 3.09.61 — Five new field types
- Added five genuinely useful field types (the ones listed earlier but never built): Time (time picker), Slider (range with min/max/step and a live value readout), Star rating (click-to-rate, configurable number of stars via the max setting), Address (street, city, ZIP, country in one field, combined on submission), and Section heading (a titled divider with optional description to organise long forms — the useful version of a page break, not raw HTML).
- Each is fully wired: palette, canvas preview, front-end render, required validation, and entry storage. File upload is intentionally not included yet — it needs its own pass for upload handling and security.

## 3.09.60 — Fix the form toolbar
- Removed the "FORM NAME" label above the name field; it is now a normal-sized input with a "Form name" placeholder inside, matching every other input in the plugin.
- Fixed the toolbar layout: no more giant gap before Build/Style/Preview and no more Notifications button wrapping onto a second row. One clean line — form context on the left, mode switcher next to it, actions on the right.

## 3.09.59 — Build density: crush the gaps, grid the palette
- Fixed the huge gaps in the field inspector. Every field had a 20px bottom margin stacking on top of the panel’s 14px flex gap (~34px between fields); removed the margin so fields sit ~11px apart. Tightened labels, hints, checkboxes and section headers to match.
- Turned the field palette from a stranded single-column list into a 2-column card grid, so you see far more field types at once and it fills the column instead of floating at the top with dead space below.

## 3.09.58 — Build inspector (2/5): grouped sections
- Grouped the field inspector (right panel in Build) into clear sections — Basics, Validation, Advanced, Conditional logic — instead of one flat list, matching the cleaner style-editor layout. Width and CSS class moved under Advanced so the common fields stay up top.

## 3.09.57 — Style editor: per-field styling back (cleanly)
- Brought back styling a single specific field, which the last version removed. The five clear global targets stay at the top; individual fields now live in a separate "Style one specific field" section below them, each labelled with the field name and type. You get per-field control without the overwhelming wall of items.

## 3.09.56 — Style editor: clear targets, less clutter
- Replaced the confusing element list (which showed every single field: Single line, Email, Phone...) with five plain-language targets, each with a description of what it edits: Whole form (background, padding & corners), Title (the heading), Labels (text above each field), Input boxes (every box people type into), Button (the submit button).
- Removed the All/Inputs/Text/Button tab bar — pointless now that there are only five clear targets. Bigger rows with real breathing room so it is not cramped or overwhelming.

## 3.09.55 — Style editor rebuild (1/5): single pick-and-style column
- Rebuilt the style-editor layout. The element picker (Elements list + All/Inputs/Text/Button tabs) moved from the far right into the left column, sitting directly above the controls for the selected element. You now pick and style in one place instead of bouncing between the right edge and left edge of the screen; the live preview fills the rest. First of a full Mail & Forms editor rebuild (build, preview, notifications, settings to follow).

## 3.09.54 — Form fixes: required validation, removed 3 fields, tighter canvas
- Removed Custom HTML, Page break and Calculation from the field palette.
- Required fields now actually block submission. Two fixes: (1) inspector changes (including the Required toggle) now auto-save, so "required" persists instead of only sticking if you manually hit Save; (2) the front-end form now validates before sending and shows "Please fill in the required fields before sending." with the empty fields highlighted, instead of submitting anyway.
- Tightened the vertical gap between field cards in the builder canvas (16px to 8px).

## 3.09.53 — Shortcode shown in the editor toolbar
- Dropped the separate embed bar. The form shortcode now shows as a small chip in the toolbar itself (in the empty space next to On/Off), and appears in all three modes — Build, Style and Preview. Click it to copy.

## 3.09.52 — Fix cramped form toolbar + roomy embed bar
- Fixed the cramped builder toolbar: the shortcode chip I had jammed into the top row was overflowing it. Moved the shortcode to its own full-width "Embed this form" bar directly under the toolbar (label + shortcode + Copy button + hint), with real breathing room. The toolbar now also wraps gracefully instead of overlapping on narrower screens.

## 3.09.51 — Form editor: shortcode chip + save-stays-in-style
- Added a click-to-copy shortcode chip ([velox_form id="X"]) right in the builder and style toolbars, so you can grab the embed code without leaving the editor.
- In Style mode, the button is now "Save styles" and it saves without kicking you back to the forms list — you stay in the style editor. (Styles also still auto-save on every change.)

## 3.09.50 — Fix: form styles not applying on the front end
- Fixed form Style-editor changes not showing on the front end. The style controls updated the live preview but never saved, so the front end kept rendering the unstyled version. Style edits now auto-save (debounced) the moment you make them, exactly like the Notifications toggles — so what you see in the editor is what ships. (Re-open a form and nudge any style control once to persist styles set before this fix.)

## 3.09.49 — HOTFIX: fatal error
- Fixed a fatal PHP error introduced in 3.09.46 (File Manager). The File Manager catalog description had a double-escaped apostrophe that closed the PHP string early, crashing the whole plugin (and the site). Removed the apostrophe. Sorry about that.

## 3.09.48 — File Manager matches the plugin
- Rebuilt the File Manager to match the rest of Velox: one flush 18px-radius panel with an internal divider (same construction as the mail inbox two-pane) instead of two small boxes, bigger 14px rows with 18px icons, real design tokens throughout, taller editor. No more mismatched look.

## 3.09.47 — File Manager redesign
- Reworked the File Manager UI to the Velox design system: replaced the emoji folder/file icons with real Lucide icons, muted neutral palette, tighter 8px spacing, cleaner rows with hover, breadcrumb navigation with chevron separators, and a proper monospace editor with a focus accent and an icon empty state.

## 3.09.46 — File Manager (dangerous tool)
- New File Manager utility: browse and edit your site files from the dashboard like SFTP/Plesk (admin-only, every path clamped inside the site root, 2 MB text-file limit, binary files blocked).
- Because it is risky, its card has a red background + warning text, and the first time you enable it you must confirm a modal explaining the risks (edit the wrong file and the site can go down, no undo). Cancel leaves it off; confirm enables it. After that first confirmation the modal never shows again — only the red card + warning remain.
- Did NOT build the "staging clone" request: a true safe replica is a whole separate product (separate DB + files + URL) and a half-built one could let edits hit the live site. Plesk WordPress Toolkit (Clone/Staging) or WP Staging does this safely at the host level.

## 3.09.45 — SMTP setup guide
- Added a "Setup guide" button next to "+ Add connection". It opens step-by-step instructions for getting the username/password/From right for whichever provider you use (Gmail app password, GMX/web.de IMAP toggle, IONOS, SendGrid, Mailgun, SES, Brevo, Postmark, Zoho, or a custom host). It opens on the provider of your first connection and has a dropdown to view any other.

## 3.09.44 — SMTP presets: GMX + web.de
- Added GMX (mail.gmx.net) and web.de (smtp.web.de) SMTP presets with hints (both need POP3/IMAP enabled in the webmail settings first, then your normal address + password). Note: personal Outlook.com no longer supports plain SMTP (Microsoft retired basic auth), and Gmail needs an App Password.

## 3.09.43 — Fix: SMTP toggle now saves + credential hints
- Fixed the "Send through SMTP" toggle (and the sender-identity / CAPTCHA settings on the Mail page) not persisting — they now auto-save on change like everything else. This regressed when settings auto-save was added, because those toggles live on the Mail page, not Settings.
- Each SMTP connection now shows a provider-specific credential hint (e.g. for IONOS: username = your full mailbox address, password = its password; SendGrid: username = "apikey"), so it is clear you still enter a username, password and From address after picking a provider.

## 3.09.42 — Mail inbox: colored folders
- Added folders to the inbox. Click "+ Folders" to create, rename, recolor or remove folders (each with its own color). Open a submission and use the folder dropdown to file it; folder chips appear in the filter bar (with color dots) to show just that folder. Completes the inbox overhaul (soft-delete + Deleted tab, bulk actions, pinned-to-top, and now folders).

## 3.09.41 — Mail inbox: bulk actions
- Each submission now has a selection checkbox. Select any (or use select-all) and a bulk bar appears to Mark read, Mark done, or Delete them all at once. Wired to the bulk backend from the previous version.

## 3.09.40 — Mail inbox: soft-delete + Deleted tab + restore
- Deleting a submission now moves it to a new Deleted tab instead of erasing it, and the inbox list count drops immediately. The Deleted tab lists everything you removed with Restore (send it back to the inbox) and Delete forever (permanent). Backs a new soft-delete column with a DB migration. (Bulk actions and colored folders are the next steps.)

## 3.09.39 — SMTP provider presets
- Added provider presets to each SMTP connection (IONOS, Gmail/Workspace, Outlook/Office 365, SendGrid, Mailgun, Amazon SES, Brevo, Postmark, Zoho, or Custom). Pick one and the host, port and encryption fill in automatically — you just add your username, password and From address. The connection already existing is detected and shown. This is on top of the existing multi-connection routing, fallback, real-handshake "Test connection" and "Send test" tools under Mail & Forms → Settings → SMTP connections.

## 3.09.38 — Real icons + Find & replace clarity
- Replaced all of the hand-drawn admin icons with the real Lucide icon set (MIT-licensed) — consistent, professional icons across the sidebar, cards and headers. Names stayed the same so nothing else changed.
- Renamed the confusing "Bulk rename" button on Images to "Find & replace names" and gave it an active state, so it is clear it opens the find/replace bar (which fills the rename boxes to review before applying).

## 3.09.37 — Auto-save settings + robots.txt quick-add
- Settings now save automatically the moment you change a toggle or field — the "Save settings" button is gone, replaced by a subtle "Saved" flash so you can see it took. (Deliberate actions like renaming images or writing robots.txt still have their own buttons.)
- The robots.txt editor has quick-add chips (Sitemap line, Protect wp-admin, Block AI crawlers, Allow everything) that append ready-made blocks — fills the empty space with something actually useful.

## 3.09.36 — Oxygen: unused-media false positives + Google Font detection
- Unused Media now reads your builder’s CSS cache (Oxygen/Bricks/Elementor, searched recursively) so images used only as section/hero backgrounds are correctly counted as USED instead of showing up as unused. This was flagging template/background images across every page.
- Font detection now pulls font-family names out of your builder CSS and fetches each from Google directly, so Google Fonts used in Oxygen are detected even when the host blocks loopback and they load via a front-end link. Custom @font-face detection is also more robust (recursive cache search).

## 3.09.35 — Module toggles moved to Utilities; risky alignment; convert reload
- Moved the Performance/SEO/Images module toggles out of Settings and into the Utilities tab as a "Core areas" section (with the same card + toggle style as the utilities).
- Fixed risky-mode fields: the divider lines now align with the rest of the panel content instead of bleeding into the left padding; the risky content keeps its indent + accent bar.
- The single-image Convert button now reloads the library grid so the image immediately shows as WebP — no manual refresh.

## 3.09.34 — Font-display strategy dropdown
- Replaced the single "font-display: swap" on/off switch with a proper strategy dropdown: Off, Swap (recommended), Fallback, Optional, and Block. The chosen mode is applied to your Google Fonts (via display=) and to locally-hosted fonts, so you can tune exactly how text behaves while fonts load.

## 3.09.33 — Clearer Font manager
- The detected-fonts list now has a legend explaining Preload vs Block, and every preload toggle has a visible "Preload" label instead of an unlabelled switch. Rewrote the Font manager description so it is obvious what each control does (and that local/builder fonts can be preloaded but not blocked).

## 3.09.32 — font-display: swap now actually applies
- The font-display swap toggle previously only did anything when hosting fonts locally. Now, when it is on, Velox appends display=swap to your enqueued Google Fonts (both the <link> tag and the registered src) so they render with font-display:swap and stop tripping the PageSpeed warning. Note: fonts loaded via a hard-coded <link> (not enqueued) or your own local @font-face files are not rewritten this way.

## 3.09.31 — Fonts: detect Oxygen fonts + fix left-wall text
- Font detection now reads your page builder’s compiled CSS cache from disk (Oxygen/Bricks/Elementor) and fetches any referenced Google Fonts, so it works even when the host blocks loopback (IONOS/Plesk). Previously it just said "could not read the front page" and found nothing.
- Fixed the detect-list message sitting flush against the left border (it now has proper padding), and updated its wording to reflect builder-CSS scanning.

## 3.09.30 — Utility cards redesigned
- Reworked the utility cards into a cleaner vertical layout: icon and toggle on a top row, name and description below, and an animated "Open →" link. Consistent card heights, a subtle hover lift, and clearer on/off/planned states.

## 3.09.29 — Performance / SEO / Images are now toggleable modules
- Added a Modules panel in Settings with on/off switches for Performance, SEO and Images (like the utilities). Switching one off hides it (and PageSpeed, under Performance) from the sidebar and stops it running; the toggles live in Settings — always reachable via the gear — so you can never lock yourself out. Empty sidebar groups are hidden automatically.

## 3.09.28 — Single-image convert in the WP Media Library
- The attachment details panel (Media Library grid and the editor media modal) now shows a "Convert to WebP" button for jpg/png images, or a "✓ Optimized with Velox" note if already done. It uses the same convert endpoint and your saved quality, and reports the KB saved. Only loads on media/editor screens.

## 3.09.27 — Single-image convert button (Velox Images grid)
- Each non-WebP image in the Images grid now has a "Convert to WebP" button that converts just that one image using your current WebP quality (same engine as bulk). The card updates to the WebP badge, shows the bytes saved, and the library stats refresh live. (Media Library tab button is next.)

## 3.09.26 — Remove the double separator on Performance panels
- Fixed the back-to-back double line in the Performance panels: a settings field already draws a bottom border, and the tool block right below it drew a top border too. The tool block no longer draws its own top border when it directly follows a field, so it is a single divider now.

## 3.09.25 — Image stats no longer reset to 0/0
- The optimizer library stats (optimized / pending / saved / %) now read the persistent optimization meta instead of only counting jpg/png. Previously, replace-mode turned images into .webp, which dropped them out of the "convertible" query, so after re-login everything showed 0/0 optimized and 0 B saved. The real numbers now persist.

## 3.09.24 — Undo the oversized header gap
- Reverted the global header-to-content gap from 48px back to 28px — it was far too large and applied between the header and everything on every page. The intended small gap for action buttons under the header is handled by the header row layout instead.

## 3.09.23 — Header spacing + unused-media box height
- PageSpeed header buttons now sit beside the title instead of crammed under the description (missing --row layout class); header-to-content gap increased to 48px across all pages.
- Unused Media panel is now compact — the empty box dropped from ~100px to ~74px, no more dead white space before scanning.

## 3.09.22 — Cookie preview width + sidebar deactivation (real fixes)
- Cookie banner live preview is now a 620px column (was 400) so it is actually wide — previously only its height had been changed.
- Switched-off utilities now really disappear from the custom sidebar (the sidebar loop never checked enabled state before); groups that become empty are hidden too.

## 3.09.21 — Images: lightbox click, single WebP badge; settings spacing
- The image zoom-click now actually opens the lightbox (it was opening but staying invisible due to a CSS conflict — it now toggles the is-open class the stylesheet expects).
- Removed the duplicate badge on WebP images — you no longer get a dark "WEBP" type badge plus a blue "WebP" badge; one blue badge for WebP, the format badge for others.
- Added a gap between the Import/Export buttons and the JSON box.

## 3.09.20 — Cookie-consent migrations (CookieYes, Complianz, Borlabs)
- Added importers for CookieYes, Complianz and Borlabs Cookie that bring over the banner heading, body text and button labels where they are stored plainly. Cookie categories, script-blocking and appearance use a different model and are not carried over — the importer says so. Every recognised plugin in the Migrate list now has a real importer.

## 3.09.19 — Redirection, Code Snippets & WPCode migrations
- Redirection importer: brings your URL redirects (source, target, 301/302/307/410, regex/exact, enabled state) into Velox Redirects & 404s; non-URL rules are skipped and counted.
- Code Snippets and WPCode importers: bring PHP/CSS/JS/HTML snippets into Velox Code Snippets, mapped to the right type. Everything is imported INACTIVE so no third-party code runs until you review and activate it.

## 3.09.18 — W3 Total Cache + WP Super Cache (last migrations)
- Added honest importers for W3 Total Cache and WP Super Cache: each brings over its cache lifespan (the only setting that maps to Velox) and clearly states that its page-cache and server/.htaccess rules are not part of Velox. The Performance-category migrations are now all real. Still on the way: Redirection, Code Snippets/WPCode, and the cookie-consent plugins (CookieYes, Complianz, Borlabs).

## 3.09.17 — Perfmatters + FlyingPress migrations
- Perfmatters importer: defer/delay JS, iframe lazy-load, JS exclusions, font preloads and DNS-prefetch. The per-page script manager stays with Perfmatters.
- FlyingPress importer: defer/delay JS, lazy-load, JS exclusions and font preloads.

## 3.09.16 — LiteSpeed + Autoptimize migrations
- LiteSpeed Cache importer: public cache lifespan, separate mobile cache, and defer/delay JS (reads both the v4 per-option format and the older serialized config). Server-level cache rules stay with LiteSpeed.
- Autoptimize importer: defer JS and image lazy-load. Its CSS/JS aggregation & minification have no Velox equivalent and are honestly not carried over.

## 3.09.15 — SMTP migrations (FluentSMTP, Post SMTP, Easy WP SMTP)
- Added one-click importers for FluentSMTP, Post SMTP and Easy WP SMTP: each brings its SMTP host, port, encryption, auth and From into a new Velox mail connection (appended, never replacing existing ones). SMTP is not switched on automatically — send a test first. Passwords that are encrypted/stored in constants may need re-entering.

## 3.09.14 — SEOPress migration
- Added a one-click importer for SEOPress: per-page titles, meta descriptions and noindex flags, plus sitemap on/off. Existing Velox values are never overwritten.

## 3.09.13 — All in One SEO migration
- Added a one-click importer for All in One SEO: per-page titles, meta descriptions and noindex flags (reading AIOSEO v4’s custom table and falling back to v3 postmeta), plus sitemap on/off. Smart tags are resolved to plain text; existing Velox values are never overwritten.

## 3.09.12 — WP Fastest Cache migration
- Added a one-click importer for WP Fastest Cache into Performance: mobile/logged-in caching, render-blocking (defer) and lazy-load toggles, plus page cache-exclusion URLs. Caching is not switched on automatically — review first. Detection requires the plugin to be installed/active.

## 3.09.11 — Rank Math SEO migration
- Added a real one-click importer for Rank Math SEO: brings over per-page SEO titles, meta descriptions and noindex flags (with Rank Math’s %variables% resolved), plus sitemap on/off. Existing Velox per-page values are never overwritten. Detection now requires the plugin to actually be installed/active.

## 3.09.10 — More space between sidebar sections
- Increased the gap between sidebar groups so the sections read as clearly separate.

## 3.09.9 — Sidebar navigation regrouped so things are findable
- Broke the 14-item "More" catch-all in the sidebar into clear sections — Overview, Essentials, Content & media, Site & visitors, System — so related tools sit together and nothing gets lost in one long undifferentiated list.

## 3.09.8 — Clearer sidebar utilities flyout
- The active-utilities flyout now shows each tool’s own icon in a tile (instead of a generic dot), with a divider under the heading, row highlighting and a chevron affordance on hover — much easier to scan and see what’s what.

## 3.09.7 — Utilities: cleaner cards + switched-off tools hidden
- Redesigned the utility cards: accent-tinted icons, tighter type and spacing, clearer on/off/planned states, subtle hover.
- Switched-off utilities now disappear from the dashboard catalog (and already do from the sidebar flyout), reappearing when re-enabled. Always-on tools stay.
- Removed the dead whitespace under the Unused Media panel before a scan.

## 3.09.6 — Settings tidy-up
- System status now shows the memory limit in the same units as upload size (e.g. 256 MB, not 256M) so they read consistently.
- PageSpeed API key and URL fields (and all inline fields) now share a fixed label column, so their inputs are the same width and line up.
- Reordered the Settings page: setup and feature config on top, diagnostics and Updates at the bottom.

## 3.09.5 — Import/export works again + migration false-detection fixed
- Fixed Import/Export doing nothing: a missing variable declaration threw a JS error and killed both buttons. Export/import of settings JSON now works.
- Migration no longer detects uninstalled plugins. It was treating leftover database options (e.g. Yoast’s, which survive uninstall) as "plugin present". Detection is now based on the plugin actually being installed/active on disk.

## 3.09.4 — Unused media detection (real fix) + spacing
- Unused media now scans the database directly (post content + Oxygen/Elementor/Bricks/etc. stored markup) instead of relying on the server fetching its own pages. On hosts that block loopback (common on IONOS/Plesk) that self-fetch returned nothing, so everything looked unused — now used images are correctly detected. Self-fetch is still used as a bonus but fails fast so it can’t hang the scan.
- Bigger cookie banner live preview.
- More breathing room under page headers across all pages, and a small gap between page titles and their subtitles.

## 3.09.3 — Sitemap logo: preview now shows it
- "Show logo" puts your WordPress Customizer site logo at the top of the sitemap, falling back to your site name if no logo is set. The live preview now actually draws it, and the toggle is relabelled "Show logo / name" to reflect the fallback.

## 3.09.2 — Fix Custom sitemap style controls
- The Custom style controls (background, text, layout, spacing) were not wired up, so changing them did nothing — no live preview update, and nothing saved so regenerating kept the defaults. They now save, update the preview live, and regenerate the sitemap.

## 3.09.1 — Sitemap styles: stop the browser caching the old look
- The sitemap now references its stylesheet with a cache-busting version (velox-sitemap.xsl?v=…). Switching styles was being hidden because the browser kept serving the previously-cached stylesheet, making every style look identical in the live sitemap.

## 3.09.0 — Sitemap appearance: genuinely distinct styles + real Custom
- The sitemap styles are now different layouts, not recoloured copies of one table: Clean (table), Cards (card grid), Dark (mono console), Minimal (bare whitespace list).
- Added a real Custom style with background, text colour, accent, layout (table/list/cards) and spacing (compact/normal/spacious) — dark backgrounds auto-adjust borders and muted text.
- Live preview in the SEO editor now renders each layout for real so you can see the difference before applying.

## 3.08.4 — Image optimizer: surface conversion failures
- The bulk converter no longer silently swallows failed images. It now counts failures, shows them in the progress and summary, and — if nothing converts — tells you your server likely can’t encode WebP (GD/Imagick), which is the usual reason images stay jpg/png.

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
