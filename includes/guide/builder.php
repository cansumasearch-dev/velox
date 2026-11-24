<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/** Velox guide — Velox Builder. Read by Velox_Help::guide( 'builder' ). */
return array(

	/* ------------------------------------------------------------ 1 */
	array(
		'id'       => 'start',
		'title'    => __( 'Getting started', 'velox' ),
		'intro'    => __( 'What Velox Builder is, how to switch it on and how to build your first page in a few minutes.', 'velox' ),
		'sections' => array(
			array(
				'title'  => __( 'What Velox Builder is', 'velox' ),
				'blocks' => array(
					array( 'p', __( 'Velox Builder is a visual editor for your pages, templates and reusable blocks. You click elements together on a live canvas, style them with classes, and publish when you are happy.', 'velox' ) ),
					array( 'p', __( 'Each page only loads the CSS it really uses, so built pages stay fast. Styles you put on a class apply everywhere that class is used, which keeps a whole site consistent.', 'velox' ) ),
				),
			),
			array(
				'title'  => __( 'Switch it on', 'velox' ),
				'blocks' => array(
					array( 'path', __( 'Velox → Utilities → Velox Builder', 'velox' ) ),
					array( 'steps', array(
						__( 'Open **Utilities** in the Velox menu.', 'velox' ),
						__( 'Switch on **Velox Builder**.', 'velox' ),
						__( 'A new **Velox Builder** section appears in the WordPress sidebar.', 'velox' ),
					) ),
					array( 'p', __( 'The next admin page you open starts a short tour of the main places: pages, templates, reusables, your design system, Claude and the editor. It takes about a minute.', 'velox' ) ),
					array( 'tip', __( 'Skipped the tour? **Velox Builder → Help** has **Start the tour** and searchable answers to common questions.', 'velox' ) ),
				),
			),
			array(
				'title'  => __( 'Build your first page', 'velox' ),
				'blocks' => array(
					array( 'path', __( 'Velox Builder → Overview → New page', 'velox' ) ),
					array( 'steps', array(
						__( 'Click **New page**. The editor opens with an empty page.', 'velox' ),
						__( 'Type a title in the field at the top left.', 'velox' ),
						__( 'Click **Add a section**, or **+ Add** in the top bar, and pick elements.', 'velox' ),
						__( 'Click an element to style it on the right; double-click text to change it.', 'velox' ),
						__( 'Click **Save** to keep a draft, then **Publish** to put the page live.', 'velox' ),
					) ),
					array( 'tip', __( 'Want a quick start? Open the **Blocks** group in the Add panel: hero, pricing, testimonials, steps, header, footer and more, all built from normal elements you can change.', 'velox' ) ),
				),
			),
			array(
				'title'  => __( 'Open a page you already have', 'velox' ),
				'blocks' => array(
					array( 'list', array(
						__( '**Pages list:** hover a page and click **Edit with Velox**.', 'velox' ),
						__( '**WordPress page editor:** the **Velox Builder** box has **Build with Velox** or **Edit with Velox**, and says whether visitors see the Velox layout.', 'velox' ),
						__( '**On the site:** the admin bar has **Edit with Velox** for the page you are looking at.', 'velox' ),
						__( '**Overview:** every page of the site is listed, with **Build this page →** for pages not built with Velox yet.', 'velox' ),
					) ),
					array( 'note', __( 'Opening a page in Velox Builder does not change it. The page keeps its old content until you publish a Velox layout.', 'velox' ) ),
				),
			),
			array(
				'title'  => __( 'The editor at a glance', 'velox' ),
				'blocks' => array(
					array( 'list', array(
						__( '**Top bar:** add elements, the page title and switcher, undo and redo, screen sizes, panels, Save, Publish, View and Exit.', 'velox' ),
						__( '**Add panel (left):** every element, grouped, with a search field.', 'velox' ),
						__( '**Canvas (middle):** your page, drawn as visitors will see it. Click to select, double-click to edit text.', 'velox' ),
						__( '**Inspector:** the selected element’s settings in two tabs, **Essentials** and **Advanced**.', 'velox' ),
						__( '**Panels (right):** **Structure**, **History**, **Page check**, **Settings**, the code panel and Claude open here.', 'velox' ),
					) ),
					array( 'tip', __( 'Press Ctrl+K (Cmd+K on a Mac) for the command search: every action, panel and element by name.', 'velox' ) ),
				),
			),
		),
	),

	/* ------------------------------------------------------------ 2 */
	array(
		'id'       => 'admin',
		'title'    => __( 'The admin screens', 'velox' ),
		'intro'    => __( 'The Velox Builder menu in WordPress: where your pages, templates, design system and tools live.', 'velox' ),
		'sections' => array(
			array(
				'title'  => __( 'Overview', 'velox' ),
				'blocks' => array(
					array( 'path', __( 'Velox Builder → Overview', 'velox' ) ),
					array( 'p', __( 'Every page on the site, built with Velox or not. Filter by **Velox pages**, **Templates**, **Reusables** or **Without Velox**, and search by name.', 'velox' ) ),
					array( 'list', array(
						__( '**Edit** or **Build:** opens the page in the editor.', 'velox' ),
						__( '**Rename**, **Duplicate**, **Delete:** right in the list, without opening the editor.', 'velox' ),
						__( '**Publish** and **Unpublish:** put a page live or take it off the site. The layout is kept.', 'velox' ),
						__( '**View** and **WP:** the page on the site, or its WordPress editor.', 'velox' ),
					) ),
					array( 'p', __( 'A badge says where each page stands: published, draft, or **Unpublished changes** when you saved changes visitors do not see yet.', 'velox' ) ),
				),
			),
			array(
				'title'  => __( 'Change what a document is', 'velox' ),
				'blocks' => array(
					array( 'p', __( 'Every document is a page, a template or a reusable. Change it with **Change type** in the Overview, or with the **Document type** menu next to the title in the editor.', 'velox' ) ),
					array( 'note', __( 'Changing the type of something that is published takes it off the site until you publish it again. A page also loses its link to its WordPress page. Velox asks before it does this.', 'velox' ) ),
				),
			),
			array(
				'title'  => __( 'The other screens', 'velox' ),
				'blocks' => array(
					array( 'list', array(
						__( '**Templates:** the frames around your pages (navbar, footer), with their purpose, “where to use” rules, the default template and an address check.', 'velox' ),
						__( '**Reusables:** blocks you build once and place anywhere, with where each one is used.', 'velox' ),
						__( '**Popups:** every popup and slide-in panel on the site, when it appears, how often, and where it lives. **Preview** opens the page with the popup open.', 'velox' ),
						__( '**Classes**, **Global styles** and **Fonts:** your design system.', 'velox' ),
						__( '**Import & export:** move pages, templates, components and your design system to another Velox site as one file.', 'velox' ),
						__( '**AI agents:** connect Claude with your own Claude account, or add an API key for the chat inside the editor.', 'velox' ),
						__( '**Keyboard shortcuts** and **Help:** your own keys, the tour and answers to common questions.', 'velox' ),
					) ),
					array( 'p', __( 'Each of these has its own chapter in this guide.', 'velox' ) ),
				),
			),
			array(
				'title'  => __( 'Settings', 'velox' ),
				'blocks' => array(
					array( 'path', __( 'Velox Builder → Settings', 'velox' ) ),
					array( 'list', array(
						__( '**Tour and help:** start the tour again or open the help.', 'velox' ),
						__( '**CSS output:** **Static file** (best for caching) or **Inline** in the page head.', 'velox' ),
						__( '**Minify CSS:** strips whitespace from the generated CSS. Off writes it readable.', 'velox' ),
						__( '**Use templates on pages not built with Velox:** pages made in the WordPress editor get your template, with their content in its Inner Content. Off: they keep the theme.', 'velox' ),
						__( '**Page width:** a shortcut to the page width in Global styles.', 'velox' ),
						__( '**Who can use the builder:** access for other roles, see the chapter on access.', 'velox' ),
					) ),
				),
			),
		),
	),

	/* ------------------------------------------------------------ 3 */
	array(
		'id'       => 'top-bar',
		'title'    => __( 'The top bar and editor settings', 'velox' ),
		'intro'    => __( 'Everything along the top of the editor, plus the settings for the page and for the editor itself.', 'velox' ),
		'sections' => array(
			array(
				'title'  => __( 'Left: menu, Add, title and type', 'velox' ),
				'blocks' => array(
					array( 'list', array(
						__( '**Velox menu** (the logo): **Velox Builder settings**, **Back to WordPress**, **Import from Oxygen…** and, when there is one, **Discard unpublished changes**.', 'velox' ),
						__( '**+ Add:** opens the Add panel with every element.', 'velox' ),
						__( '**Title:** click and type to rename the page.', 'velox' ),
						__( '**Status:** a small badge says **Unsaved**, **Draft**, **Unpublished changes** or **Live**.', 'velox' ),
						__( '**Document type:** page, template or reusable.', 'velox' ),
					) ),
					array( 'p', __( 'The arrow next to the title, or the magnifier further right, opens a list of pages, posts, templates and reusables to switch to. Search by name and filter with **All**, **Pages**, **Posts**, **Templates** and **Reusables**. Pages not built with Velox are listed too.', 'velox' ) ),
				),
			),
			array(
				'title'  => __( 'Middle: undo, redo and screen sizes', 'velox' ),
				'blocks' => array(
					array( 'p', __( 'The arrows next to the document type undo and redo every change, one step at a time. Every step of this session is also listed under **History**.', 'velox' ) ),
					array( 'p', __( 'The switch in the middle sets the canvas to **Desktop**, **Laptop**, **Tablet** or **Mobile**. Hover a button to see the widths it stands for. The screen size also decides where your style changes go, as the chapter on styling explains.', 'velox' ) ),
				),
			),
			array(
				'title'  => __( 'Right: panels and tools', 'velox' ),
				'blocks' => array(
					array( 'list', array(
						__( '**Claude:** build and change the page with words.', 'velox' ),
						__( '**Structure:** the page as a tree of elements.', 'velox' ),
						__( '**Reusables:** opens the Reusables screen in a new tab.', 'velox' ),
						__( '**Global CSS / JS (every page):** CSS files and scripts that load on every Velox page.', 'velox' ),
						__( '**History:** this session’s steps and the saved versions.', 'velox' ),
						__( '**Page check:** accessibility and SEO problems on this page.', 'velox' ),
						__( '**Settings:** page settings, editor settings and global styles.', 'velox' ),
					) ),
					array( 'tip', __( 'Pin a panel and it pushes the canvas aside instead of covering it. The right panels can be dragged wider or narrower from their left edge; double-click the edge to reset the width.', 'velox' ) ),
				),
			),
			array(
				'title'  => __( 'Save, Publish, View and Exit', 'velox' ),
				'blocks' => array(
					array( 'list', array(
						__( '**Save:** keeps a draft only you see. A dot shows while there are unsaved changes.', 'velox' ),
						__( '**Publish** or **Publish changes:** puts the saved version on the site. It reads **Published** when visitors see exactly what is in the editor.', 'velox' ),
						__( '**View:** **Live version** (what visitors see right now) or **Saved changes** (only you, until you publish).', 'velox' ),
						__( '**Exit:** **Open frontend**, **Go to backend** or **View page (new tab)**.', 'velox' ),
					) ),
					array( 'note', __( 'On a narrow window the bar gets more compact step by step: labels turn into icons, and less used buttons step aside. They stay in the command search (Ctrl+K).', 'velox' ) ),
				),
			),
			array(
				'title'  => __( 'Page settings', 'velox' ),
				'blocks' => array(
					array( 'path', __( 'Editor → Settings → Page settings', 'velox' ) ),
					array( 'list', array(
						__( '**Page width:** the content width for this page only. Empty uses the site default.', 'velox' ),
						__( '**Overlay header:** lets the header sit on top of the first section instead of pushing it down: never, always, tablet and up, or desktop only.', 'velox' ),
						__( '**Page background:** a background colour for this page.', 'velox' ),
						__( '**Animate On Scroll:** a scroll animation default for every element on this page.', 'velox' ),
						__( '**Scripts:** **CSS for this page only** and **JavaScript for this page only**. JavaScript runs on the published page, not in the editor.', 'velox' ),
					) ),
				),
			),
			array(
				'title'  => __( 'Editor settings', 'velox' ),
				'blocks' => array(
					array( 'path', __( 'Editor → Settings → Editor settings', 'velox' ) ),
					array( 'list', array(
						__( '**Indicate parent elements on hover:** outlines the containers around the element under the mouse.', 'velox' ),
						__( '**Spacing handles on the canvas:** the drag handles for padding and margin.', 'velox' ),
						__( '**Canvas info bar:** below the page, above the page or hidden. It shows the screen size, canvas width, zoom and the path to the selected element.', 'velox' ),
					) ),
					array( 'note', __( 'These are your own preferences. They are stored in this browser and do not change the site.', 'velox' ) ),
				),
			),
		),
	),

	/* ------------------------------------------------------------ 4 */
	array(
		'id'       => 'adding',
		'title'    => __( 'Adding and arranging elements', 'velox' ),
		'intro'    => __( 'How to put elements on the page, where they land, and how to move, copy and paste them.', 'velox' ),
		'sections' => array(
			array(
				'title'  => __( 'The Add panel', 'velox' ),
				'blocks' => array(
					array( 'p', __( 'Click **+ Add** in the top bar. The elements are grouped: Containers, Text, Links, Media, WordPress, Velox, Layout, Typography, Content, Utility, Interactive, Blocks, and Shop when Velox Shop is on. Your published reusables have their own group at the end.', 'velox' ) ),
					array( 'p', __( 'New elements arrive with sample text, placeholder images and a finished look, so a card looks like a card straight away. Every starting value is a normal class value in the style panel, so you can change it right away.', 'velox' ) ),
					array( 'tip', __( 'Start typing in **Type to filter elements…** to find an element by name in every group at once.', 'velox' ) ),
				),
			),
			array(
				'title'  => __( 'Where a new element goes', 'velox' ),
				'blocks' => array(
					array( 'list', array(
						__( 'A container is selected: the new element goes inside it, at the end.', 'velox' ),
						__( 'Another element is selected: the new element goes right after it.', 'velox' ),
						__( 'Nothing is selected: it goes at the end of the page.', 'velox' ),
					) ),
					array( 'tip', __( 'You can also drag a tile from the Add panel straight onto the canvas, to exactly the spot you want.', 'velox' ) ),
				),
			),
			array(
				'title'  => __( 'Drag and drop', 'velox' ),
				'blocks' => array(
					array( 'p', __( 'Drag an element on the canvas, by the selection badge, or by its row in Structure. While you drag, the container that will receive it gets a dashed outline with its name, and a label says **Inside**, **Above**, **Below**, **Left of** or **Right of**. Esc cancels the drag.', 'velox' ) ),
					array( 'list', array(
						__( 'Alt+↑ and Alt+↓ move the selected element up or down among its neighbours.', 'velox' ),
						__( 'Alt+← moves it out of its container; Alt+→ moves it into the element above.', 'velox' ),
						__( 'Ctrl+D duplicates it, Delete removes it.', 'velox' ),
					) ),
				),
			),
			array(
				'title'  => __( 'Where sections may go', 'velox' ),
				'blocks' => array(
					array( 'p', __( 'A section sits on the page or directly inside one other section, never inside a div, card or columns. When a spot is not allowed, the element goes right after the nearest container where it is, and a note tells you.', 'velox' ) ),
					array( 'note', __( 'Inner Content stays at page level, and a template can have only one.', 'velox' ) ),
				),
			),
			array(
				'title'  => __( 'Copy and paste, even between sites', 'velox' ),
				'blocks' => array(
					array( 'p', __( 'Ctrl+C copies the selected element, Ctrl+V pastes it after the selected element. The copy works across Velox sites: paste it into a page on another site and its classes come along.', 'velox' ) ),
					array( 'p', __( 'Pasting text or HTML from another page or a document turns it into elements: headings, paragraphs, lists, images and links.', 'velox' ) ),
					array( 'note', __( 'After pasting from another site, check images and links: they may still point to the old site.', 'velox' ) ),
				),
			),
		),
	),

	/* ------------------------------------------------------------ 5 */
	array(
		'id'       => 'canvas',
		'title'    => __( 'The canvas and the Structure panel', 'velox' ),
		'intro'    => __( 'Selecting, editing and organising elements on the canvas and in the element tree.', 'velox' ),
		'sections' => array(
			array(
				'title'  => __( 'Selecting elements', 'velox' ),
				'blocks' => array(
					array( 'list', array(
						__( 'Click an element to select it. The inspector on the right shows its settings.', 'velox' ),
						__( 'Esc selects the parent of the selected element.', 'velox' ),
						__( 'Ctrl+click or Shift+click selects several elements. Delete, duplicate, paste style and the move keys then act on all of them.', 'velox' ),
						__( 'Ctrl+Shift+arrow keys select the parent, the first child, or the previous or next element.', 'velox' ),
					) ),
					array( 'p', __( 'The selected element gets a small badge with its name. Use it to drag the element, select its parent, duplicate it or delete it.', 'velox' ) ),
				),
			),
			array(
				'title'  => __( 'The right-click menu', 'velox' ),
				'blocks' => array(
					array( 'p', __( 'Right-click an element on the canvas, or use the menu button of its row in Structure:', 'velox' ) ),
					array( 'list', array(
						__( '**Copy**, **Paste**, **Duplicate**, **Copy style**, **Paste style**, **Move up**, **Move down**, **Settings**, **Delete**.', 'velox' ),
						__( '**Rename:** a name of your own, shown in Structure and on the canvas.', 'velox' ),
						__( '**Export:** copies the element so you can paste it on another site.', 'velox' ),
						__( '**Wrap with div:** puts the element inside a new div block.', 'velox' ),
						__( '**Hide:** the element stays in the page but is not shown. Hidden elements stay marked in Structure.', 'velox' ),
						__( '**Lock:** the element cannot be changed, moved or deleted by accident until you choose **Unlock**.', 'velox' ),
						__( '**Make re-usable:** turns the element into a reusable block.', 'velox' ),
					) ),
				),
			),
			array(
				'title'  => __( 'Spacing handles', 'velox' ),
				'blocks' => array(
					array( 'p', __( 'Point at the selected element: its padding shows as blue bands, its margin as amber bands, each side with a handle. Drag a handle to change that side; a label shows the value.', 'velox' ) ),
					array( 'list', array(
						__( 'Shift changes all four sides, Alt the opposite side as well.', 'velox' ),
						__( 'Esc cancels the drag.', 'velox' ),
						__( 'The change goes to what the inspector is styling: the class or the element, at the current screen size and state.', 'velox' ),
					) ),
				),
			),
			array(
				'title'  => __( 'Zoom and the info bar', 'velox' ),
				'blocks' => array(
					array( 'p', __( 'Shift+1 zooms the canvas to fit the window, Shift+0 back to 100%. The info bar below the canvas shows the screen size, the canvas width, the zoom and the path to the selected element.', 'velox' ) ),
				),
			),
			array(
				'title'  => __( 'The Structure panel', 'velox' ),
				'blocks' => array(
					array( 'p', __( 'Click **Structure** in the top bar to see the page as a tree. Each row shows the element’s name and its class, with small marks for conditions, interactions, query loops, header and footer boxes, locked elements and editable component parts.', 'velox' ) ),
					array( 'list', array(
						__( 'Click a row to select the element; it scrolls into view on the canvas.', 'velox' ),
						__( 'Drag rows to rearrange. A row turns red where a section cannot go.', 'velox' ),
						__( 'Hover a row for copy, duplicate, delete, show or hide and the full menu.', 'velox' ),
						__( '**Expand all** and **Collapse all** open or fold the whole tree.', 'velox' ),
					) ),
				),
			),
			array(
				'title'  => __( 'Searching and using the keyboard in Structure', 'velox' ),
				'blocks' => array(
					array( 'p', __( 'The search field finds elements by name. Type tag:h2 for a tag, .card for a class or #id for one element.', 'velox' ) ),
					array( 'list', array(
						__( '↑ ↓ Home End move through the rows.', 'velox' ),
						__( '← → fold and unfold, or go to the parent and child.', 'velox' ),
						__( 'F2 renames the row, Enter opens its styles.', 'velox' ),
					) ),
				),
			),
		),
	),

	/* ------------------------------------------------------------ 6 */
	array(
		'id'       => 'inspector',
		'title'    => __( 'The inspector', 'velox' ),
		'intro'    => __( 'The panel on the right that shows what the selected element is, what you are styling and every setting it has.', 'velox' ),
		'sections' => array(
			array(
				'title'  => __( 'The header, heading level and tag', 'velox' ),
				'blocks' => array(
					array( 'p', __( 'At the top you see the element’s name and its HTML tag, with buttons to select its parent, duplicate and delete it, and a pin. Unpinned, the element settings float over the canvas while something is selected.', 'velox' ) ),
					array( 'p', __( 'A heading shows **Level** buttons H1 to H6 at the end of **Essentials**; use one H1 per page. Texts, sections, div blocks and cards have a **Tag** menu there that says what each tag is for; the small tag under the name opens it too. A div block set to the tag a becomes one big link.', 'velox' ) ),
					array( 'tip', __( 'With nothing selected, the inspector offers **Add element** and **Structure**, counts the elements on the page and lists every keyboard shortcut.', 'velox' ) ),
				),
			),
			array(
				'title'  => __( 'Class, ID and state', 'velox' ),
				'blocks' => array(
					array( 'p', __( 'Below the name, two buttons say what the styles below change. The first picks the target: one of the element’s classes, or its ID, which means only this element. The second picks the state: **Normal**, hover, focus, active or one you add.', 'velox' ) ),
					array( 'p', __( 'Hover the class button to read what it changes, for example “Styling the class .card: every element with it changes.” At a smaller screen size or in a state like hover, a line underneath says so. The + button adds a class.', 'velox' ) ),
				),
			),
			array(
				'title'  => __( 'Two tabs: Essentials and Advanced', 'velox' ),
				'blocks' => array(
					array( 'list', array(
						__( '**Essentials:** what this element is. Its own settings first, then only the styles that belong to it, in plain words.', 'velox' ),
						__( '**Advanced:** every style property, grouped into Layout, Spacing, Size, Position, Typography, Background & effects, Border and Effects, one row each that opens in place.', 'velox' ),
						__( 'Below them, under **Animation & behaviour**: scroll animation, motion, interactions, query loop, conditions, attributes, custom CSS and the element ID. The list is the same for every element.', 'velox' ),
					) ),
					array( 'p', __( 'Picking another element opens it on **Essentials** again.', 'velox' ) ),
				),
			),
			array(
				'title'  => __( 'What Essentials shows', 'velox' ),
				'blocks' => array(
					array( 'list', array(
						__( '**Headings and text:** font, size, weight, line height, letter spacing, alignment and colour, plus spacing.', 'velox' ),
						__( '**Buttons:** font size, weight, colour, text transform, background, spacing and corners.', 'velox' ),
						__( '**Images:** the image itself, alt text and title, size, aspect ratio, fit and corners.', 'velox' ),
						__( '**Sections and containers:** **Children** as tiles (below each other, side by side or grid), **horizontal** and **vertical alignment** as tiles, columns for a grid, the gap, then spacing, size, background and border.', 'velox' ),
						__( '**Carousels, tabs, accordions, counters, maps, shop parts and similar:** their own settings only. A code block shows only its HTML, CSS and JavaScript fields.', 'velox' ),
					) ),
					array( 'tip', __( 'Everything else is one click away: the **Advanced** button at the bottom of Essentials opens the Advanced tab.', 'velox' ) ),
				),
			),
			array(
				'title'  => __( 'Finding what you need', 'velox' ),
				'blocks' => array(
					array( 'p', __( 'Under **Advanced**, a control only shows where it does something: flex options for a flex box, grid options for a grid, offsets once the element is positioned. A control that already holds a value always stays. Option buttons show words (Left, Centre, Cover …); the CSS value is in the tooltip.', 'velox' ) ),
					array( 'list', array(
						__( '**Find a property:** at the top of Advanced, searches every style group at once and also shows options that are normally hidden. Ctrl+Shift+F jumps into the field from anywhere.', 'velox' ),
						__( '**Set only:** hides everything that is not set at the current screen size and state.', 'velox' ),
						__( 'A small number on a group heading counts what is set there; its reset arrow clears that group.', 'velox' ),
					) ),
				),
			),
		),
	),

	/* ------------------------------------------------------------ 7 */
	array(
		'id'       => 'styling',
		'title'    => __( 'Styling', 'velox' ),
		'intro'    => __( 'Classes and element styles, screen sizes, states, units and design tokens: how Velox decides what a page looks like.', 'velox' ),
		'sections' => array(
			array(
				'title'  => __( 'Class styles or element styles', 'velox' ),
				'blocks' => array(
					array( 'p', __( 'Styles on a class apply to every element with that class, on every page. Use them for anything that repeats: cards, buttons, section headings.', 'velox' ) ),
					array( 'p', __( 'Element styles (the ID, or **This element**) change only the one element. Use them for one-offs.', 'velox' ) ),
					array( 'note', __( 'Editing a class changes it everywhere it is used. When you publish, Velox lists the shared classes the publish updates and asks first.', 'velox' ) ),
				),
			),
			array(
				'title'  => __( 'Adding classes and combo classes', 'velox' ),
				'blocks' => array(
					array( 'steps', array(
						__( 'Click the class button or **+** in the inspector.', 'velox' ),
						__( 'Pick one of your classes, or type a new name and press Enter.', 'velox' ),
						__( 'The class becomes the active one: styles you set now go to it.', 'velox' ),
					) ),
					array( 'p', __( 'The first class is the base. Further classes are combo classes, for variants such as .card plus .card-dark. Switch between them in the class menu to style each one.', 'velox' ) ),
				),
			),
			array(
				'title'  => __( 'Locked classes and element overrides', 'velox' ),
				'blocks' => array(
					array( 'p', __( 'In the class menu, the lock next to a class protects it from changes by accident until you unlock it.', 'velox' ) ),
					array( 'p', __( 'Element styles win over the class. When you style a class and the element has its own values for the same thing at this screen size, a note says so, with **Move to class** (make them the class’s values) and **Remove** (drop them).', 'velox' ) ),
				),
			),
			array(
				'title'  => __( 'Screen sizes', 'velox' ),
				'blocks' => array(
					array( 'p', __( 'Styles you set on **Desktop** apply everywhere. Switch to **Laptop**, **Tablet** or **Mobile** in the top bar and change a value: it applies from that size down, while larger screens keep the desktop value.', 'velox' ) ),
					array( 'p', __( 'The inspector shows which size you are styling. Many element settings, such as columns or slides shown, also have their own value per screen size.', 'velox' ) ),
					array( 'tip', __( 'The widths of the screen sizes are set under Global styles → Width & breakpoints. Changing them updates the editor and the site together.', 'velox' ) ),
				),
			),
			array(
				'title'  => __( 'States', 'velox' ),
				'blocks' => array(
					array( 'list', array(
						__( '**Normal:** the element as it usually looks.', 'velox' ),
						__( '**hover:** while the pointer is over it.', 'velox' ),
						__( '**focus:** when it is selected by keyboard.', 'velox' ),
						__( '**active:** while it is pressed.', 'velox' ),
						__( '**Add a state…:** any other CSS state, such as focus-within, first-child or nth-child(odd).', 'velox' ),
					) ),
					array( 'p', __( 'Pick a state, then set the styles that should change. Go back to **Normal** when you are done.', 'velox' ) ),
				),
			),
			array(
				'title'  => __( 'Reading the fields', 'velox' ),
				'blocks' => array(
					array( 'p', __( 'An empty field shows the value in effect, greyed out: a Global styles default, another class or a larger screen size. Type over it to give this element or class its own value.', 'velox' ) ),
					array( 'p', __( 'A coloured mark next to a label says where a value comes from. Hover it for details, and click the reset mark to remove the value.', 'velox' ) ),
					array( 'p', __( 'Ctrl+Shift+C copies the styles of the selected element, Ctrl+Shift+V pastes them onto another one, or onto several selected elements.', 'velox' ) ),
				),
			),
			array(
				'title'  => __( 'Units, tokens, maths and colours', 'velox' ),
				'blocks' => array(
					array( 'list', array(
						__( 'The unit menu in a number field offers px, %, em, rem, vh, vw and auto.', 'velox' ),
						__( 'The same menu lists the design tokens that fit the field, such as spacing steps for padding or radius tokens for corners.', 'velox' ),
						__( 'You can type sums: 24*2 becomes 48. Mixed units such as 100% - 40px become a calc() value.', 'velox' ),
						__( 'Every colour field has a swatch to pick from, a text field for any CSS colour, and your site’s colour tokens to click.', 'velox' ),
					) ),
					array( 'p', __( 'A token keeps the link: change it in Global styles and every place that uses it follows.', 'velox' ) ),
					array( 'tip', __( 'Drag a number field’s label left or right to change the value. In the field, ↑ ↓ add or take 1, with Shift 10, with Alt 0.1.', 'velox' ) ),
				),
			),
		),
	),

	/* ------------------------------------------------------------ 8 */
	array(
		'id'       => 'content',
		'title'    => __( 'Text, images and links', 'velox' ),
		'intro'    => __( 'Editing text on the canvas, formatting a few words, and setting images, videos, icons and links.', 'velox' ),
		'sections' => array(
			array(
				'title'  => __( 'Editing text', 'velox' ),
				'blocks' => array(
					array( 'steps', array(
						__( 'Double-click a heading, paragraph, button or any element with its own text.', 'velox' ),
						__( 'Type. The text tools appear in the inspector.', 'velox' ),
						__( 'Press Enter to finish, or click elsewhere. Esc throws the edit away.', 'velox' ),
					) ),
					array( 'tip', __( 'Shift+Enter makes a new line. In a list, Enter starts the next item and Ctrl+Enter finishes.', 'velox' ) ),
				),
			),
			array(
				'title'  => __( 'The text tools', 'velox' ),
				'blocks' => array(
					array( 'list', array(
						__( '**Bold**, **Italic**, **Underline**, **Strikethrough**, **Superscript** and **Subscript**.', 'velox' ),
						__( '**Link** and **Remove link** for the selected words.', 'velox' ),
						__( '**Clear formatting** removes all of it again.', 'velox' ),
						__( '**Align left**, **Align center** and **Align right**.', 'velox' ),
						__( '**Style selection** and **Insert Data** (see below).', 'velox' ),
					) ),
				),
			),
			array(
				'title'  => __( 'Style a few words', 'velox' ),
				'blocks' => array(
					array( 'steps', array(
						__( 'Select the words while editing the text.', 'velox' ),
						__( 'Click **Style selection**.', 'velox' ),
						__( 'Give the class a name, then pick a **Text colour**, **Highlight**, **Weight**, size or italic.', 'velox' ),
						__( 'Click **Apply**.', 'velox' ),
					) ),
					array( 'tip', __( 'Use the same class name in another text to style those words the same way.', 'velox' ) ),
				),
			),
			array(
				'title'  => __( 'Images', 'velox' ),
				'blocks' => array(
					array( 'p', __( 'Double-click an image, or click **Choose image** or **Replace** in Essentials, to pick one from the media library.', 'velox' ) ),
					array( 'list', array(
						__( '**Alt text:** describe what the image shows. Leave it empty only for pure decoration.', 'velox' ),
						__( '**Title:** an optional tooltip.', 'velox' ),
						__( '**File size to load**, **Loading** and **Caption:** which size of the file the page loads, whether it loads right away or when scrolled near, and a caption.', 'velox' ),
						__( '**Fill the box (crop to its shape):** the image fills its box and is cropped to fit.', 'velox' ),
					) ),
					array( 'p', __( 'For a background image, use **Advanced → Background & effects → Image or gradient** (sections show it under Essentials too): pick from the media library or type a CSS gradient. Size, position and repeat appear once there is an image.', 'velox' ) ),
				),
			),
			array(
				'title'  => __( 'Videos and icons', 'velox' ),
				'blocks' => array(
					array( 'list', array(
						__( '**Video:** paste a **Video link** (YouTube, Vimeo or an .mp4 file), give it a **Title (for screen readers)**, and optionally **Play muted on a loop, like a background**.', 'velox' ),
						__( '**Icon:** pick from more than 2,000 icons. Set a **Meaning (leave empty if decorative)** when the icon says something on its own.', 'velox' ),
					) ),
				),
			),
			array(
				'title'  => __( 'Links', 'velox' ),
				'blocks' => array(
					array( 'p', __( 'Buttons, text links, images, icons and boxes set to the tag a have a **Link** group in Essentials.', 'velox' ) ),
					array( 'list', array(
						__( '**Link URL:** any address, an email (mailto:) or a phone number (tel:).', 'velox' ),
						__( '**Or link to:** this post, this term, a user’s posts, the post author’s posts or the home page. Useful in templates and loops.', 'velox' ),
						__( '**Open in:** **Same tab** or **New tab**.', 'velox' ),
					) ),
					array( 'tip', __( 'To jump to a part of the page, give that part an ID or use the Anchor target element, and link to #its-id.', 'velox' ) ),
				),
			),
		),
	),

	/* ------------------------------------------------------------ 9 */
	array(
		'id'       => 'dynamic',
		'title'    => __( 'Dynamic data and query loops', 'velox' ),
		'intro'    => __( 'Show live values from WordPress, and repeat a design for every post, term or user.', 'velox' ),
		'sections' => array(
			array(
				'title'  => __( 'Insert Data', 'velox' ),
				'blocks' => array(
					array( 'steps', array(
						__( 'Double-click a text to edit it and put the cursor where the value should go.', 'velox' ),
						__( 'Click **Insert Data** in the text tools.', 'velox' ),
						__( 'Pick a value. It is drawn live on the published page.', 'velox' ),
					) ),
					array( 'note', __( 'Values marked • are inserted as a placeholder for now.', 'velox' ) ),
				),
			),
			array(
				'title'  => __( 'What you can insert', 'velox' ),
				'blocks' => array(
					array( 'list', array(
						__( '**Post:** title, content, excerpt, date, categories and tags, a custom field, the number of comments.', 'velox' ),
						__( '**Product (Velox Shop):** price, add to cart button, badge, availability, unit price, VAT note, delivery time, article number, was price.', 'velox' ),
						__( '**Featured image:** its title, caption and alt text.', 'velox' ),
						__( '**Author** and **Current user:** name, bio and custom fields.', 'velox' ),
						__( '**Loop item**, **Term** and **User:** values for the item a query loop is showing.', 'velox' ),
						__( '**Blog info**, **Archive** and **Advanced:** site title and tagline, the current year, archive title and description, post ID, post type and taxonomy terms.', 'velox' ),
					) ),
				),
			),
			array(
				'title'  => __( 'The WordPress elements', 'velox' ),
				'blocks' => array(
					array( 'p', __( '**Post title**, **Post content**, **Featured image** and **Menu / Nav** pull live from the page or post being shown. In a template the editor previews them with the newest post. Title and featured image can **Link to the post**.', 'velox' ) ),
				),
			),
			array(
				'title'  => __( 'Turn a box into a loop', 'velox' ),
				'blocks' => array(
					array( 'steps', array(
						__( 'Select a section, div block, columns or grid.', 'velox' ),
						__( 'Open **Advanced → Query loop** and tick **Repeat the content for each item**.', 'velox' ),
						__( 'Choose what to list and how many.', 'velox' ),
						__( 'Design the first item inside the box. It repeats once per item.', 'velox' ),
					) ),
					array( 'tip', __( 'Inside a loop, use Insert Data for titles and fields, the WordPress elements for the featured image, and **Or link to** → this post for links.', 'velox' ) ),
				),
			),
			array(
				'title'  => __( 'Loop options', 'velox' ),
				'blocks' => array(
					array( 'list', array(
						__( '**Posts:** any post type, filtered by taxonomy terms (or “current” for related posts) and author, sorted by date, title, menu order, comments, random or a custom field.', 'velox' ),
						__( '**This page’s list (archive, search):** whatever the page is listing, such as the posts of a category or the search results. Use it in archive and search templates.', 'velox' ),
						__( '**Categories, tags or terms:** the terms of a taxonomy, with or without empty ones, top level only if you like.', 'velox' ),
						__( '**Users:** by role, only people who have published posts if you like.', 'velox' ),
						__( '**How many**, **Skip first**, **Pagination** (page numbers or previous / next) and **When nothing is found**.', 'velox' ),
					) ),
					array( 'p', __( 'The editor shows a live preview with the number of items found. Archive and search lists preview the latest posts.', 'velox' ) ),
				),
			),
		),
	),

	/* ------------------------------------------------------------ 10 */
	array(
		'id'       => 'advanced',
		'title'    => __( 'The Advanced tab', 'velox' ),
		'intro'    => __( 'Who sees an element, how the page reacts, scroll animations and motion, and your own attributes and CSS.', 'velox' ),
		'sections' => array(
			array(
				'title'  => __( 'Conditions: show to some visitors only', 'velox' ),
				'blocks' => array(
					array( 'path', __( 'Inspector → Advanced → Conditions', 'velox' ) ),
					array( 'p', __( 'Show an element only to some visitors, on some pages or at some times. Without conditions it always shows.', 'velox' ) ),
					array( 'steps', array(
						__( 'Click **Add condition**.', 'velox' ),
						__( 'Pick what to check, how to compare and the value.', 'velox' ),
						__( 'Add more rules with **And**, or a second group with **Add “or” group**.', 'velox' ),
					) ),
				),
			),
			array(
				'title'  => __( 'What a condition can check', 'velox' ),
				'blocks' => array(
					array( 'list', array(
						__( '**Visitor:** logged in or out, user role, a user field.', 'velox' ),
						__( '**Content:** the type of page (front page, blog, single post, page, archive, search, 404), the post type, specific pages, a custom field.', 'velox' ),
						__( '**Date and time:** from or until a date, days of the week, a time of day. These use the site time zone.', 'velox' ),
						__( '**Request:** a URL parameter, a cookie, or the site the visitor came from.', 'velox' ),
					) ),
					array( 'p', __( 'Conditions are checked on the server. When the rules do not match, the element is not sent to the browser at all. In the editor it always shows.', 'velox' ) ),
					array( 'note', __( 'Pages with date, time, URL or cookie rules are not stored in the page cache, so every visitor gets the right version.', 'velox' ) ),
				),
			),
			array(
				'title'  => __( 'Interactions: make the page react', 'velox' ),
				'blocks' => array(
					array( 'path', __( 'Inspector → Advanced → Interactions', 'velox' ) ),
					array( 'steps', array(
						__( 'Select the element visitors will use, for example a button.', 'velox' ),
						__( 'Click **Add interaction**.', 'velox' ),
						__( 'Under **When it**, choose: is clicked, is hovered, scrolls into view, page loads, or page is scrolled past.', 'velox' ),
						__( 'Under **Then**, choose what happens and to which element.', 'velox' ),
					) ),
				),
			),
			array(
				'title'  => __( 'What an interaction can do', 'velox' ),
				'blocks' => array(
					array( 'list', array(
						__( 'Show, hide, or show or hide in turn.', 'velox' ),
						__( 'Add, remove or toggle a class.', 'velox' ),
						__( 'Scroll to an element, open or close a popup, panel or dropdown, or put the cursor in a field.', 'velox' ),
					) ),
					array( 'p', __( 'The target is this element, another element, or every element with a class. Set a **Delay**, limit it to some **Screens**, or run it **Only the first time**. Tick **Hidden until an interaction shows it** on an element that should start hidden.', 'velox' ) ),
					array( 'note', __( 'Interactions run on the published page, not in the editor. Hover, view and scroll undo themselves when they end.', 'velox' ) ),
				),
			),
			array(
				'title'  => __( 'Animate on scroll', 'velox' ),
				'blocks' => array(
					array( 'p', __( 'Under **Animate on scroll**, pick an **Animation** for the moment the element scrolls into view, with its **Duration** and **Delay** in milliseconds. **Use site default** follows Global styles, **No animation** switches it off for this element.', 'velox' ) ),
					array( 'list', array(
						__( '**Global styles → Animate on scroll:** the default for the whole site: animation, duration, easing, trigger offset, delay, once or every time, and screens where it is off.', 'velox' ),
						__( '**Settings → Page settings → Animate On Scroll:** a default for this page only.', 'velox' ),
					) ),
				),
			),
			array(
				'title'  => __( 'Motion: text and children', 'velox' ),
				'blocks' => array(
					array( 'list', array(
						__( '**Text appears:** for headings and text, word by word, letter by letter, or sliding up out of a mask, when it scrolls into view or right away on the first screen.', 'velox' ),
						__( '**Children appear:** for a container, the elements inside come in one after another: rising, fading, from the left or right, growing, or out of a blur.', 'velox' ),
					) ),
					array( 'p', __( 'Both have their own step and duration in milliseconds.', 'velox' ) ),
				),
			),
			array(
				'title'  => __( 'Motion: scrolling and mouse effects', 'velox' ),
				'blocks' => array(
					array( 'list', array(
						__( '**Parallax speed:** the element moves slower or faster than the page while scrolling, on desktop only if you like.', 'velox' ),
						__( '**Tilts in 3D under the mouse**, **A soft light follows the mouse** and **Pulled towards the mouse (magnetic)**.', 'velox' ),
						__( '**Children stack like cards while scrolling:** each child sticks and the next slides over it.', 'velox' ),
						__( '**Pinned: children move sideways while scrolling:** the box stays put and its row slides sideways; on phones it can be a swipe row.', 'velox' ),
						__( '**Floating particles in the background:** for a section or container, with colour and amount. The canvas shows them too.', 'velox' ),
					) ),
					array( 'note', __( 'Scroll animations and most motion play on the published page, not in the editor; check them with **View → Saved changes**. Visitors who ask their system for less motion see everything without it.', 'velox' ) ),
				),
			),
			array(
				'title'  => __( 'Attributes and custom CSS', 'velox' ),
				'blocks' => array(
					array( 'p', __( 'Under **Attributes**, add aria-label, data-*, rel, title or any other HTML attribute with **Add attribute**. Event handlers (on…), id and class cannot be set here.', 'velox' ) ),
					array( 'p', __( 'Administrators also find **Custom CSS**. Write %root% for this element, for example a hover lift on %root%:hover. Plain declarations such as color: red; apply to the element directly.', 'velox' ) ),
					array( 'tip', __( 'The expand button next to Custom CSS opens a bigger editor. Esc closes it.', 'velox' ) ),
				),
			),
		),
	),

	/* ------------------------------------------------------------ 11 */
	array(
		'id'       => 'interactive',
		'title'    => __( 'Interactive elements, popups and headers', 'velox' ),
		'intro'    => __( 'Accordions, tabs and sliders on the canvas, popups and panels, menus, and a header that reacts to scrolling.', 'velox' ),
		'sections' => array(
			array(
				'title'  => __( 'Accordions, FAQ, tabs and sliders on the canvas', 'velox' ),
				'blocks' => array(
					array( 'p', __( 'They work on the canvas like on the page: click a header to open it, a tab to switch, the arrows or dots to move the slider. What you open stays open while you edit; it is not saved.', 'velox' ) ),
					array( 'p', __( 'Double-click a title or text to edit it right there. Enter keeps it, Esc throws it away.', 'velox' ) ),
					array( 'p', __( 'The selected element shows **+ Add** for the next section, question, tab or slide. The new item opens and the cursor goes into its title. Every item has its own drop area for any elements.', 'velox' ) ),
				),
			),
			array(
				'title'  => __( 'Useful settings', 'velox' ),
				'blocks' => array(
					array( 'list', array(
						__( '**Accordion and FAQ:** one or several open sections, the first one open, icon side, heading level, and FAQ markup for search engines.', 'velox' ),
						__( '**Tabs:** the **Look** (tabs, pills or switch), where the tabs sit, **Turn into an accordion on mobile**, and opening the tab named in the web address.', 'velox' ),
						__( '**Slider:** slides shown per screen size, arrows, dots, autoplay, looping, card width and a sliver of the next card.', 'velox' ),
						__( '**Content switcher:** two options with a switch between them, for example monthly and yearly prices.', 'velox' ),
						__( '**Read more:** shows the first part of its content with a fade and a button, only when the content is actually longer.', 'velox' ),
					) ),
				),
			),
			array(
				'title'  => __( 'Popups', 'velox' ),
				'blocks' => array(
					array( 'path', __( '+ Add → Interactive → Popup / modal', 'velox' ) ),
					array( 'p', __( 'Add **Popup / modal**, or a ready-made one: **Popup: announcement**, **Popup: newsletter** or **Popup: exit offer**. Put content inside like in any container.', 'velox' ) ),
					array( 'list', array(
						__( '**Show when:** a visitor clicks, the page loads, after a delay, after scrolling, when an element scrolls into view, after inactivity, or when the visitor moves to leave.', 'velox' ),
						__( '**Show it:** every time, once per visit, once a day, once every few days, or once ever.', 'velox' ),
						__( 'Also: only after a number of pages, desktop only or tablet and phone only, close by itself, and never again after a click inside.', 'velox' ),
					) ),
					array( 'tip', __( 'Put a popup in a template to show it across the site. **Velox Builder → Popups** lists every popup with its rules.', 'velox' ) ),
				),
			),
			array(
				'title'  => __( 'Open a popup from a button', 'velox' ),
				'blocks' => array(
					array( 'steps', array(
						__( 'Set the popup to **Show when** → a visitor clicks.', 'velox' ),
						__( 'Select the button and add an interaction: **When it** is clicked, **Then** open the popup.', 'velox' ),
					) ),
					array( 'p', __( 'The Floating button element can also open a popup or panel by its ID.', 'velox' ) ),
				),
			),
			array(
				'title'  => __( 'Menus and panels', 'velox' ),
				'blocks' => array(
					array( 'list', array(
						__( '**Navigation:** links from a WordPress menu or from a list you type. It can **Collapse to a burger** on small screens, and the mobile menu can open as a side panel with its own width, colours, backdrop, a button and a small text at the bottom.', 'velox' ),
						__( 'Submenus open on hover or click. The bar can stick to the top, shrink once scrolled or hide when scrolling down.', 'velox' ),
						__( '**Offcanvas panel:** slides in from a side, with width, a dimmed backdrop, close button, Esc and speed. It opens from an interaction or from the CSS selector you name under **Opened by (CSS selector)**.', 'velox' ),
						__( '**Dropdown:** a button that opens its content to a side, on click or hover.', 'velox' ),
					) ),
				),
			),
			array(
				'title'  => __( 'A header that reacts to scrolling', 'velox' ),
				'blocks' => array(
					array( 'path', __( 'Inspector → Advanced → Site header / footer', 'velox' ) ),
					array( 'steps', array(
						__( 'In a template, select the box at the top that holds your logo and menu.', 'velox' ),
						__( 'Set **This box is** to **The site header**.', 'velox' ),
						__( 'Tick **Sticky header** and choose whether it stays from the start or appears after scrolling.', 'velox' ),
						__( 'Under **Once scrolled**, give it another background, glass blur, text colour, bottom line, shadow or padding.', 'velox' ),
					) ),
					array( 'p', __( 'It can also hide when scrolling down and show when scrolling up, show a scroll progress line, and be transparent over the first section.', 'velox' ) ),
				),
			),
			array(
				'title'  => __( 'Elements in the header, and the footer', 'velox' ),
				'blocks' => array(
					array( 'p', __( 'Anything inside the site header gets **In the site header → Show this element**: always, only once scrolled, only before scrolling, only while transparent, or not while transparent.', 'velox' ) ),
					array( 'tip', __( 'Two logos, one set to “only once scrolled” and one to “only before scrolling”, swap when the header sticks.', 'velox' ) ),
					array( 'p', __( 'Set the last box of a page or template to **The site footer** to **Reveal from behind the content as the page ends**, or to **Keep at the bottom of short pages**.', 'velox' ) ),
				),
			),
		),
	),

	/* ------------------------------------------------------------ 12 */
	array(
		'id'       => 'forms-code',
		'title'    => __( 'Forms, reviews, code and the shop', 'velox' ),
		'intro'    => __( 'Elements that connect to other parts of Velox, and the places for your own HTML, CSS and JavaScript.', 'velox' ),
		'sections' => array(
			array(
				'title'  => __( 'Forms', 'velox' ),
				'blocks' => array(
					array( 'steps', array(
						__( 'Add the **Form** element (group Velox).', 'velox' ),
						__( 'Pick the form under **Which form**, or click **Create a form**.', 'velox' ),
						__( 'Style it like any other element. The canvas draws the real form.', 'velox' ),
					) ),
					array( 'p', __( 'Fields, emails, the thank-you page, webhooks and spam protection are set in the form itself. Entries arrive in Velox → Mail.', 'velox' ) ),
					array( 'note', __( 'Forms need Velox → Utilities → Mail to be switched on.', 'velox' ) ),
				),
			),
			array(
				'title'  => __( 'Google Reviews', 'velox' ),
				'blocks' => array(
					array( 'p', __( 'Add a review source under Velox → Utilities → Google Reviews first. Then pick it under **Connection** in the element, with an optional **Design preset**.', 'velox' ) ),
					array( 'p', __( 'Choose how many reviews, the minimum rating, grid or other layouts, card and star look, and what to show: picture, date, Google logo. **Show example reviews** helps while you design.', 'velox' ) ),
				),
			),
			array(
				'title'  => __( 'Code block and HTML embed', 'velox' ),
				'blocks' => array(
					array( 'list', array(
						__( '**Code block:** your own **HTML**, **CSS** and **JavaScript** in one element. The JavaScript runs on the page.', 'velox' ),
						__( '**HTML embed:** paste embed code. Iframes and video work; scripts are removed.', 'velox' ),
					) ),
					array( 'note', __( 'Only administrators can change code that runs on the page.', 'velox' ) ),
				),
			),
			array(
				'title'  => __( 'CSS and JavaScript for the site or one page', 'velox' ),
				'blocks' => array(
					array( 'path', __( 'Editor → Global CSS / JS (every page)', 'velox' ) ),
					array( 'list', array(
						__( '**CSS:** one or more files that apply to every Velox page. **New file** adds one.', 'velox' ),
						__( '**JavaScript:** scripts for every Velox page. For each, choose **Load in** footer or head, the **Timing** (immediately, after the page loads, or without blocking) and whether it is **Enabled**.', 'velox' ),
					) ),
					array( 'p', __( 'Both save as you type. A script with an error is not saved and the error is shown. For one page only, use **Settings → Page settings → Scripts**.', 'velox' ) ),
					array( 'note', __( 'Scripts do not run inside the editor. Check the published page.', 'velox' ) ),
				),
			),
			array(
				'title'  => __( 'Shop elements', 'velox' ),
				'blocks' => array(
					array( 'p', __( 'With Velox Shop switched on, the **Shop** group has the product grid, add to cart, a complete product, the cart and account buttons, the cart, the checkout, the customer account and the withdrawal form, with live previews.', 'velox' ) ),
					array( 'p', __( 'Their settings start at **From the shop design**, so they look like the rest of the shop until you choose otherwise. Product values for loops are under Insert Data → Product.', 'velox' ) ),
				),
			),
		),
	),

	/* ------------------------------------------------------------ 13 */
	array(
		'id'       => 'elements',
		'title'    => __( 'Element reference', 'velox' ),
		'intro'    => __( 'Every element in the Add panel, group by group, with one line on what it is for.', 'velox' ),
		'sections' => array(
			array(
				'title'  => __( 'Containers', 'velox' ),
				'blocks' => array(
					array( 'list', array(
						__( '**Section:** a full-width band of the page with its own padding. The usual top-level element.', 'velox' ),
						__( '**Div block:** a plain box to group and lay out other elements.', 'velox' ),
						__( '**Columns:** two columns side by side that stack on phones.', 'velox' ),
						__( '**Grid:** three columns that become two on tablets and one on phones.', 'velox' ),
						__( '**Spacer:** empty space of a set height.', 'velox' ),
						__( '**Divider:** a thin horizontal line.', 'velox' ),
					) ),
				),
			),
			array(
				'title'  => __( 'Text and links', 'velox' ),
				'blocks' => array(
					array( 'list', array(
						__( '**Heading:** a title from H1 to H6.', 'velox' ),
						__( '**Text:** a paragraph or any running text.', 'velox' ),
						__( '**List:** a bulleted list.', 'velox' ),
						__( '**Quote:** a quotation with an accent line.', 'velox' ),
						__( '**Button:** a link that looks like a button.', 'velox' ),
						__( '**Text link:** a plain link in text style.', 'velox' ),
						__( '**Social icons:** links to your profiles, email or phone, in several shapes and colours.', 'velox' ),
					) ),
				),
			),
			array(
				'title'  => __( 'Media, WordPress and Velox', 'velox' ),
				'blocks' => array(
					array( 'list', array(
						__( '**Image:** a picture from the media library.', 'velox' ),
						__( '**Video:** YouTube, Vimeo or an .mp4 file, also as a muted background loop.', 'velox' ),
						__( '**Icon:** one of more than 2,000 icons.', 'velox' ),
						__( '**Gallery:** a grid of images with captions and a lightbox.', 'velox' ),
						__( '**Before / after:** two images with a slider between them.', 'velox' ),
						__( '**Map:** a map of an address, loaded only after a click if you want, for privacy.', 'velox' ),
						__( '**Post title**, **Post content** and **Featured image:** the live values of the page or post being shown.', 'velox' ),
						__( '**Menu / Nav:** a WordPress menu.', 'velox' ),
						__( '**Form:** one of your Velox forms.', 'velox' ),
						__( '**Google Reviews:** reviews from a connected Google source.', 'velox' ),
					) ),
				),
			),
			array(
				'title'  => __( 'Layout', 'velox' ),
				'blocks' => array(
					array( 'list', array(
						__( '**Split screen:** two halves side by side, with a chosen split, that stack below a set width.', 'velox' ),
						__( '**Full-height section:** a section at least as tall as the screen, content centred.', 'velox' ),
						__( '**Aspect-ratio box:** a box that keeps a shape such as 16:9.', 'velox' ),
						__( '**Bento grid:** a grid of wide and narrow tiles, one column on phones.', 'velox' ),
						__( '**Shape divider:** a wave, curve, tilt, peak or zigzag edge between sections.', 'velox' ),
						__( '**Overlap wrapper:** pulls its content up over the section above.', 'velox' ),
						__( '**Auto stack:** places its children under each other with an even gap.', 'velox' ),
						__( '**Cluster:** a row of items that wraps onto new lines, such as tags or buttons.', 'velox' ),
						__( '**Content + sidebar:** a main column and a narrower sidebar that stack below a set width.', 'velox' ),
					) ),
				),
			),
			array(
				'title'  => __( 'Typography', 'velox' ),
				'blocks' => array(
					array( 'list', array(
						__( '**Gradient text:** text filled with a colour gradient.', 'velox' ),
						__( '**Outlined text:** text drawn as an outline.', 'velox' ),
						__( '**Highlighted text:** text with a marker-style highlight.', 'velox' ),
						__( '**Drop cap paragraph:** a paragraph that starts with a large first letter.', 'velox' ),
						__( '**Eyebrow:** a small label above a heading, with an optional line.', 'velox' ),
						__( '**Pull quote:** a large quote with a quotation mark or accent line.', 'velox' ),
						__( '**Vertical text:** text that reads from bottom to top or top to bottom.', 'velox' ),
						__( '**Rotating text:** a sentence whose key word keeps changing.', 'velox' ),
					) ),
				),
			),
			array(
				'title'  => __( 'Content', 'velox' ),
				'blocks' => array(
					array( 'list', array(
						__( '**Icon box:** an icon with a heading and text.', 'velox' ),
						__( '**Card:** a white box with corners, padding and a soft shadow.', 'velox' ),
						__( '**Stats row:** a row of key figures.', 'velox' ),
						__( '**Team member:** photo, name, role and social links.', 'velox' ),
						__( '**Logo strip:** a row of logos, grey until hovered if you like.', 'velox' ),
						__( '**Counter:** a number that counts up when it comes into view.', 'velox' ),
						__( '**Star rating:** stars for a rating, with an optional “4.8 / 5”.', 'velox' ),
						__( '**Progress circle:** a ring that fills to a value.', 'velox' ),
						__( '**Skill bars:** labelled bars that fill to a value.', 'velox' ),
						__( '**Checklist:** a list with check marks.', 'velox' ),
						__( '**Table:** rows and cells typed as text, with heading row and stripes.', 'velox' ),
						__( '**Timeline:** dated steps along a line.', 'velox' ),
						__( '**Callout:** a tinted box for a tip or warning.', 'velox' ),
						__( '**Badge:** a small label such as “New”.', 'velox' ),
						__( '**Avatar group:** overlapping profile pictures.', 'velox' ),
						__( '**Trust badges:** icons with short promises, such as secure checkout or free delivery.', 'velox' ),
						__( '**Payment icons:** the payment methods you accept.', 'velox' ),
						__( '**Case study card:** image, label, title, text and a link to the story.', 'velox' ),
						__( '**CTA band:** a wide band with a message and a button.', 'velox' ),
						__( '**Notice box:** a box for a short notice, such as holiday opening hours.', 'velox' ),
						__( '**Feature grid:** a grid of icon boxes that wraps on small screens.', 'velox' ),
					) ),
				),
			),
			array(
				'title'  => __( 'Utility', 'velox' ),
				'blocks' => array(
					array( 'list', array(
						__( '**Skip link:** a “skip to content” link for keyboard users that shows when focused.', 'velox' ),
						__( '**Screen-reader text:** text only screen readers announce.', 'velox' ),
						__( '**Table of contents:** links to the page’s H2 (and H3, H4) headings.', 'velox' ),
						__( '**Share buttons:** share the page on Facebook, X, LinkedIn, WhatsApp, by email or by copying the link. No tracking scripts.', 'velox' ),
						__( '**Copyright year:** “©” with the current year and the site name, optionally since a year.', 'velox' ),
						__( '**Reading time:** the minutes it takes to read the page.', 'velox' ),
						__( '**Last updated:** the date of the last change, or “3 days ago”.', 'velox' ),
						__( '**Loading skeleton:** a grey placeholder with a shimmer.', 'velox' ),
						__( '**Empty state:** an icon, heading, text and button for a “nothing here yet” message.', 'velox' ),
						__( '**HTML embed:** embed code such as an iframe or video.', 'velox' ),
						__( '**Code block:** your own HTML, CSS and JavaScript.', 'velox' ),
						__( '**Anchor target:** a jump point that stops below a sticky header.', 'velox' ),
					) ),
				),
			),
			array(
				'title'  => __( 'Interactive', 'velox' ),
				'blocks' => array(
					array( 'list', array(
						__( '**Navigation:** the main menu, with burger and side panel on phones.', 'velox' ),
						__( '**Breadcrumbs:** the path to the current page, with search-engine markup.', 'velox' ),
						__( '**Anchor navigation:** links to sections of the page that highlight the one you are reading.', 'velox' ),
						__( '**Floating button:** a button fixed to the screen that links, calls or opens a popup or panel.', 'velox' ),
						__( '**Back to top:** a button that scrolls up, with an optional progress ring.', 'velox' ),
						__( '**Announcement bar:** a message at the top or bottom that visitors can close.', 'velox' ),
						__( '**Sticky contact bar:** contact buttons fixed to the screen edge, phones only if you like.', 'velox' ),
						__( '**Reading progress:** a thin bar that fills as the page is read.', 'velox' ),
						__( '**Marquee** and **Logo carousel:** text or logos that scroll by endlessly.', 'velox' ),
						__( '**Countdown:** days, hours, minutes and seconds to a date.', 'velox' ),
						__( '**Flip card:** a card that turns over on hover or click.', 'velox' ),
						__( '**Slider**, **Image carousel** and **Testimonial slider:** slides with arrows, dots and autoplay; the carousels come filled.', 'velox' ),
						__( '**Content switcher:** two contents with a switch between them.', 'velox' ),
						__( '**Read more:** shows the start of its content and opens the rest with a button.', 'velox' ),
						__( '**Copy button:** copies a text such as a discount code and says “Copied!”.', 'velox' ),
						__( '**Offcanvas panel:** a panel that slides in from the side.', 'velox' ),
						__( '**Popup / modal** and the three ready-made popups: a window over the page.', 'velox' ),
						__( '**Dropdown:** a button that opens more content.', 'velox' ),
						__( '**Tabs:** content in tabs, pills or a switch.', 'velox' ),
						__( '**Accordion:** sections that fold open.', 'velox' ),
						__( '**FAQ:** questions and answers that fold open, with FAQ markup for search engines.', 'velox' ),
					) ),
				),
			),
			array(
				'title'  => __( 'Blocks', 'velox' ),
				'blocks' => array(
					array( 'p', __( 'Ready-made designs built from normal elements, so every part can be changed:', 'velox' ) ),
					array( 'list', array(
						__( '**Site header** and **Site footer:** logo, menu and button; columns of links and a copyright line.', 'velox' ),
						__( '**Hero:** a large opening section with heading, text and buttons.', 'velox' ),
						__( '**Pricing card** and **Pricing table:** one plan, or three side by side.', 'velox' ),
						__( '**Testimonial** and **Testimonials:** one customer quote, or several.', 'velox' ),
						__( '**Steps:** numbered steps of a process.', 'velox' ),
						__( '**Contact details:** address, phone and email with icons.', 'velox' ),
						__( '**Post card:** image, title, text and a link for a blog post.', 'velox' ),
						__( '**Logo wall:** a grid of client logos.', 'velox' ),
					) ),
				),
			),
			array(
				'title'  => __( 'Template and Shop', 'velox' ),
				'blocks' => array(
					array( 'list', array(
						__( '**Inner Content:** in a template, the place where each page’s own content goes. Only offered in templates.', 'velox' ),
						__( '**Product grid:** your products, by category and sort order, with optional category buttons.', 'velox' ),
						__( '**Add to cart** and **Product (complete):** the buy button, or a whole product with photos and options.', 'velox' ),
						__( '**Cart button** and **Account button:** icons for the header, with item count or account menu.', 'velox' ),
						__( '**Cart**, **Checkout**, **Customer account** and **Withdrawal form:** the shop pages as elements.', 'velox' ),
					) ),
					array( 'note', __( 'The Shop group only appears when Velox Shop is switched on.', 'velox' ) ),
				),
			),
		),
	),

	/* ------------------------------------------------------------ 14 */
	array(
		'id'       => 'templates',
		'title'    => __( 'Templates and reusables', 'velox' ),
		'intro'    => __( 'Share a header, footer and layout across many pages, decide which pages use which template, and build blocks once to use them anywhere.', 'velox' ),
		'sections' => array(
			array(
				'title'  => __( 'Create a template', 'velox' ),
				'blocks' => array(
					array( 'path', __( 'Velox Builder → Templates → New template', 'velox' ) ),
					array( 'p', __( 'A template is the frame around your pages: navbar, footer, anything shared. Its **Inner Content** element marks where each page’s own content goes. Change the template once and every page that uses it changes with it.', 'velox' ) ),
					array( 'steps', array(
						__( 'Click **New template**, give it a name and choose **What is it for?**', 'velox' ),
						__( 'Click **Create and open**. The template starts with an Inner Content element in place.', 'velox' ),
						__( 'Add your navbar above it and your footer below it.', 'velox' ),
						__( 'Publish it. Until then the site does not use it.', 'velox' ),
					) ),
					array( 'tip', __( 'Already built the layout as a normal page? Switch its **Document type** from Page to Template.', 'velox' ) ),
				),
			),
			array(
				'title'  => __( 'What a template is for', 'velox' ),
				'blocks' => array(
					array( 'list', array(
						__( '**Front page:** the site homepage only.', 'velox' ),
						__( '**404:** the page-not-found screen.', 'velox' ),
						__( '**Search results** and **Archives:** the search page, and category, tag and date listings.', 'velox' ),
						__( '**Posts** and **Pages:** every single blog post, or every WordPress page.', 'velox' ),
						__( '**Catch-all:** anything without a better match.', 'velox' ),
					) ),
					array( 'p', __( '**Make default** makes a template the site default, used for any page no other template claims.', 'velox' ) ),
				),
			),
			array(
				'title'  => __( 'Choose exactly where a template is used', 'velox' ),
				'blocks' => array(
					array( 'p', __( 'Click **Where to use** on a template. Under **Use on**, add places with **Add a place**: the entire site, the front page, the blog page, single posts or pages, a post type, specific pages, child pages, posts in a category, archives of any kind, search results, the 404 page or an address that matches a pattern such as /shop/*.', 'velox' ) ),
					array( 'p', __( 'Add exceptions under **But not on**, limit it with **For** to logged-in or logged-out visitors, and set a **Priority**.', 'velox' ) ),
					array( 'note', __( 'Rules apply once the template is published. With no “Use on” rules, the template works by its purpose.', 'velox' ) ),
				),
			),
			array(
				'title'  => __( 'Which template wins', 'velox' ),
				'blocks' => array(
					array( 'steps', array(
						__( 'The template chosen on the page itself.', 'velox' ),
						__( 'A template whose “Where to use” rules match. On a tie, the higher priority wins, then the more specific rule.', 'velox' ),
						__( 'A template made for it by purpose: front page first, then posts or pages.', 'velox' ),
						__( 'The site default.', 'velox' ),
						__( 'A catch-all template.', 'velox' ),
					) ),
					array( 'tip', __( 'Not sure? Paste an address into **Which template is used here?** on the Templates screen and click **Check**. It tells you the template and why.', 'velox' ) ),
				),
			),
			array(
				'title'  => __( 'One page, and pages not built with Velox', 'velox' ),
				'blocks' => array(
					array( 'p', __( 'In the WordPress page editor, the **Velox Builder** box has **Render page using template**: the site default, a specific template, or no template for this page only.', 'velox' ) ),
					array( 'p', __( 'With **Use templates on pages not built with Velox** switched on under Velox Builder → Settings, pages and posts made in the WordPress editor get your template, with their content in its Inner Content. Off, they keep the theme.', 'velox' ) ),
				),
			),
			array(
				'title'  => __( 'Preview a template with real content', 'velox' ),
				'blocks' => array(
					array( 'p', __( 'While editing a template, **View as…** in the top bar fills the Inner Content with a page or post of your choice. That content is only a preview and is not saved. **Stop preview** ends it.', 'velox' ) ),
				),
			),
			array(
				'title'  => __( 'Make a reusable', 'velox' ),
				'blocks' => array(
					array( 'p', __( 'A reusable (also called a component) is a block you place on many pages and templates. Edit it in one place and every copy updates.', 'velox' ) ),
					array( 'list', array(
						__( '**From an element:** right-click it and choose **Make re-usable**, then give it a name. The element is replaced by a linked copy.', 'velox' ),
						__( '**From scratch:** **Velox Builder → Reusables → New reusable**.', 'velox' ),
					) ),
					array( 'note', __( 'Only published reusables are shown on the site. Velox reminds you when you publish a page that places one that is still a draft.', 'velox' ) ),
				),
			),
			array(
				'title'  => __( 'Place and edit a reusable', 'velox' ),
				'blocks' => array(
					array( 'p', __( 'Open **+ Add** and scroll to the **Reusables** group. A reusable goes right after the section you are in, not inside it.', 'velox' ) ),
					array( 'p', __( 'To change it, select a copy and click **Edit component**. It opens in a new tab; publish it and every page shows the change. The Reusables screen shows where each one is used.', 'velox' ) ),
				),
			),
			array(
				'title'  => __( 'Different content in each copy', 'velox' ),
				'blocks' => array(
					array( 'steps', array(
						__( 'Open the reusable and select an element in it, such as its heading or image.', 'velox' ),
						__( 'In **Advanced → Component property**, tick **Editable in each copy** and give it a name.', 'velox' ),
						__( 'Choose what may change: text, image, link, or whether it can be hidden.', 'velox' ),
						__( 'Save and publish the reusable. Each copy now shows these fields in its settings.', 'velox' ),
					) ),
					array( 'p', __( 'Empty fields in a copy use the reusable’s own content. **Reset this copy to the component** clears them all.', 'velox' ) ),
				),
			),
		),
	),

	/* ------------------------------------------------------------ 16 */
	array(
		'id'       => 'design-system',
		'title'    => __( 'The design system', 'velox' ),
		'intro'    => __( 'Classes, global styles, design tokens and fonts: the styles every page shares.', 'velox' ),
		'sections' => array(
			array(
				'title'  => __( 'Classes', 'velox' ),
				'blocks' => array(
					array( 'path', __( 'Velox Builder → Classes', 'velox' ) ),
					array( 'p', __( 'Every styling class across your Velox pages, with its origin (Velox or yours), the pages it is used on and how many properties it has. Search and filter by **My classes**, **Velox defaults** or **Unused**.', 'velox' ) ),
					array( 'list', array(
						__( '**Edit:** rename the class and edit its CSS, including states such as :hover and the responsive rules of the site breakpoints.', 'velox' ),
						__( '**Delete:** removes the class and its styles from every page.', 'velox' ),
					) ),
					array( 'note', __( 'Properties the builder does not understand, and media queries at other widths, are dropped on save.', 'velox' ) ),
				),
			),
			array(
				'title'  => __( 'Unused and starter classes', 'velox' ),
				'blocks' => array(
					array( 'p', __( 'The **Unused** filter lists classes no element, no markup and no custom CSS uses. **Delete all unused** removes them in one go; Design history keeps a version to restore.', 'velox' ) ),
					array( 'note', __( 'Velox starter classes carry the default look of new elements. Deleting one strips that look from every page that uses it, so Velox asks you to type the class name to confirm.', 'velox' ) ),
				),
			),
			array(
				'title'  => __( 'Global styles', 'velox' ),
				'blocks' => array(
					array( 'path', __( 'Velox Builder → Global styles', 'velox' ) ),
					array( 'p', __( 'The defaults every built page inherits. Leave a field empty to inherit instead of forcing a value.', 'velox' ) ),
					array( 'list', array(
						__( '**Body text:** font family, size, line height and colour for ordinary text.', 'velox' ),
						__( '**Headings:** a heading font, and size and colour for H1 to H6.', 'velox' ),
						__( '**Links:** colour, hover colour and underline.', 'velox' ),
						__( '**Sections & containers:** the padding every section starts with and the gap of columns and div blocks. In the editor these show greyed out in empty fields.', 'velox' ),
					) ),
					array( 'tip', __( 'In the editor, **Settings → Global styles** shows the same styles, plus **Smooth scrolling**.', 'velox' ) ),
				),
			),
			array(
				'title'  => __( 'Design tokens', 'velox' ),
				'blocks' => array(
					array( 'list', array(
						__( '**Colours:** name your brand colours with **Add colour**. Each one is available everywhere as var(--name), and every colour field offers it.', 'velox' ),
						__( '**Fluid type scale:** font sizes that grow smoothly from the small to the large screen, no breakpoints needed. Set a base size and a ratio for each end.', 'velox' ),
						__( '**Fluid spacing scale:** padding, margins and gaps that grow with the screen, from 3xs to 3xl.', 'velox' ),
						__( '**Custom tokens:** corner radii, shadows, widths or anything else, with **Add token**. The group decides which fields offer it: radius for corners, size for widths and heights, space for spacing, type for font sizes.', 'velox' ),
					) ),
					array( 'p', __( 'Size fields list the fitting tokens in their unit menu. Change a token and every place that uses it follows.', 'velox' ) ),
				),
			),
			array(
				'title'  => __( 'Width, breakpoints and theme CSS', 'velox' ),
				'blocks' => array(
					array( 'p', __( 'Under **Width & breakpoints**, set the page width and the widths where the tablet and landscape sizes begin. They apply to the editor and the site together.', 'velox' ) ),
					array( 'p', __( '**Theme → Theme CSS on Velox pages** is off by default, so pages look like the editor. Load it only if you need the theme’s styles; it can override yours.', 'velox' ) ),
					array( 'note', __( 'Changing a breakpoint rewrites the media queries in every page stylesheet. Republish a page if it looks stale.', 'velox' ) ),
				),
			),
			array(
				'title'  => __( 'Design history', 'velox' ),
				'blocks' => array(
					array( 'p', __( 'Every change to classes, design tokens and global styles is kept, from any screen. **Restore** brings back an earlier version; what is there now is kept too.', 'velox' ) ),
				),
			),
			array(
				'title'  => __( 'Fonts', 'velox' ),
				'blocks' => array(
					array( 'path', __( 'Velox Builder → Fonts', 'velox' ) ),
					array( 'steps', array(
						__( 'Click **Add font** and type the family name.', 'velox' ),
						__( 'Choose the source: Google Fonts, a CSS URL, or local .woff2 files from the media library.', 'velox' ),
						__( 'Tick only the weights you use under **Weights to load**.', 'velox' ),
						__( 'Under **Preload**, tick only the one or two weights your first screen uses.', 'velox' ),
						__( 'Save. The font is now in every font menu of the editor.', 'velox' ),
					) ),
					array( 'tip', __( '**While fonts load** sets one choice for the whole site. “swap” shows fallback text right away and is recommended.', 'velox' ) ),
				),
			),
		),
	),

	/* ------------------------------------------------------------ 17 */
	array(
		'id'       => 'publishing',
		'title'    => __( 'Saving, publishing and versions', 'velox' ),
		'intro'    => __( 'Drafts and live pages, the history of your changes, saved versions and the page check.', 'velox' ),
		'sections' => array(
			array(
				'title'  => __( 'Save is a draft, Publish goes live', 'velox' ),
				'blocks' => array(
					array( 'p', __( '**Save** (Ctrl+S) keeps a draft. Visitors see nothing of it. **Publish** (Ctrl+Shift+S) saves and puts this version on the site. For a template or reusable, publishing means it is now used on the site.', 'velox' ) ),
					array( 'p', __( '**View → Saved changes** opens your saved draft as the page will look; only you see it. **View → Live version** shows what visitors see right now.', 'velox' ) ),
				),
			),
			array(
				'title'  => __( 'The History panel', 'velox' ),
				'blocks' => array(
					array( 'list', array(
						__( '**This session:** every change since you opened the editor. Click one to go back to it; Redo brings the rest back. It is cleared when you close the editor.', 'velox' ),
						__( '**Saved versions:** every save and every publish, kept for good. Saves a few minutes apart fold into one.', 'velox' ),
					) ),
				),
			),
			array(
				'title'  => __( 'Go back to a saved version', 'velox' ),
				'blocks' => array(
					array( 'steps', array(
						__( 'Open **History** and click a version under **Saved versions**.', 'velox' ),
						__( 'The canvas shows that version, and what changed since then.', 'velox' ),
						__( 'Click **Restore this version**, or **Back to editing** to leave it.', 'velox' ),
					) ),
					array( 'p', __( 'A restore is saved as a new version, so Undo and the list always bring back what you had. Versions are marked Saved, Published, Restored, Imported or By AI.', 'velox' ) ),
				),
			),
			array(
				'title'  => __( 'Throw away changes, or get lost work back', 'velox' ),
				'blocks' => array(
					array( 'p', __( 'When a published page has saved changes you no longer want, open the **Velox menu** (the logo) and choose **Discard unpublished changes**. The page goes back to what visitors see.', 'velox' ) ),
					array( 'p', __( 'The editor also keeps a copy of unsaved work in this browser. If the page is reopened before it was saved (a crash, an expired session, a closed tab), a bar above the canvas offers **Restore** or **Discard**.', 'velox' ) ),
				),
			),
			array(
				'title'  => __( 'Page check', 'velox' ),
				'blocks' => array(
					array( 'p', __( 'The shield button in the top bar lists what would trip up visitors, screen readers or search engines, with a count on the button:', 'velox' ) ),
					array( 'list', array(
						__( 'Images without a picture or without alt text.', 'velox' ),
						__( 'A missing or second H1, skipped heading levels, empty headings.', 'velox' ),
						__( 'Links without text, or that go nowhere (“#”).', 'velox' ),
						__( 'Low contrast, text under 12px, buttons smaller than 24×24px.', 'velox' ),
						__( 'Videos and embedded frames without a title.', 'velox' ),
					) ),
					array( 'tip', __( 'Each row selects the element and offers a fix in one click: write the alt text, set the link, change the tag, colours or size.', 'velox' ) ),
				),
			),
		),
	),

	/* ------------------------------------------------------------ 18 */
	array(
		'id'       => 'access-transfer',
		'title'    => __( 'Access, import and export', 'velox' ),
		'intro'    => __( 'Let other people work in the builder, move your work between Velox sites, and bring layouts over from Oxygen.', 'velox' ),
		'sections' => array(
			array(
				'title'  => __( 'Access levels', 'velox' ),
				'blocks' => array(
					array( 'list', array(
						__( '**Administrators:** can always do everything.', 'velox' ),
						__( '**Designer:** changes layout, styles and element settings, and can start a Velox layout for a page. No custom CSS or JavaScript.', 'velox' ),
						__( '**Client:** changes text, images, links and component fields. Layout and styles stay as they are.', 'velox' ),
						__( '**No access:** the role does not see the builder.', 'velox' ),
					) ),
					array( 'note', __( 'Templates, components, global styles, fonts and the builder settings stay with administrators.', 'velox' ) ),
				),
			),
			array(
				'title'  => __( 'Give a role access', 'velox' ),
				'blocks' => array(
					array( 'path', __( 'Velox Builder → Settings → Who can use the builder', 'velox' ) ),
					array( 'steps', array(
						__( 'Pick a level for each role: **No access**, **Client** or **Designer**.', 'velox' ),
						__( 'Tick **May publish** if the role may put changes live.', 'velox' ),
						__( 'Save. People in that role can now open the pages they may edit in WordPress.', 'velox' ),
					) ),
					array( 'p', __( 'Without **May publish**, changes are saved and wait for an administrator to publish them. Every save is kept under History → Saved versions.', 'velox' ) ),
				),
			),
			array(
				'title'  => __( 'What a client sees', 'velox' ),
				'blocks' => array(
					array( 'p', __( 'A client gets a calm editor: double-click a text on the page to change it, and the inspector shows only the text, image and link of the selected element. Elements locked by an administrator stay locked.', 'velox' ) ),
					array( 'tip', __( 'Lock the elements a client should never touch, and make component parts **Editable in each copy** where they may change content.', 'velox' ) ),
				),
			),
			array(
				'title'  => __( 'Export', 'velox' ),
				'blocks' => array(
					array( 'path', __( 'Velox Builder → Import & export', 'velox' ) ),
					array( 'steps', array(
						__( 'Under **Export**, tick the pages, templates and components you want.', 'velox' ),
						__( 'Tick the parts of the design system to include: classes, design tokens, global styles, fonts, global CSS and global JavaScript.', 'velox' ),
						__( 'Click **Download file**.', 'velox' ),
					) ),
					array( 'p', __( 'Components and forms that your choice uses come along by themselves. To move a single element, no file is needed: copy it in the editor and paste it on the other site.', 'velox' ) ),
				),
			),
			array(
				'title'  => __( 'Import', 'velox' ),
				'blocks' => array(
					array( 'steps', array(
						__( 'Under **Import**, click **Choose a file** or drop the .json file made by Export.', 'velox' ),
						__( 'Check what is in it. Nothing changes until you confirm.', 'velox' ),
						__( 'For anything this site already has, choose **Add as a copy**, **Replace this site’s** or **Skip (use this site’s)**.', 'velox' ),
						__( 'For the design system, choose whether to add only new things, use the file’s where both have one, or not import it.', 'velox' ),
						__( 'Start the import, then open each page or template, check it and publish it.', 'velox' ),
					) ),
					array( 'p', __( 'Tick **Copy … images into this site’s media library**, otherwise they keep loading from the old site and break when it goes away. **Point links to the old site at this site** rewrites the links too.', 'velox' ) ),
					array( 'note', __( 'Pages and templates arrive as drafts. Global JavaScript arrives switched off: check it in the editor’s code panel, then switch it on.', 'velox' ) ),
				),
			),
			array(
				'title'  => __( 'Bring a page over from Oxygen', 'velox' ),
				'blocks' => array(
					array( 'list', array(
						__( 'Open a page that was built with Oxygen: the empty canvas says so and offers **Import Oxygen layout**.', 'velox' ),
						__( 'Or open any page, then **Velox menu → Import from Oxygen…** and pick a page or template built with Oxygen. Its layout is added to the page you have open.', 'velox' ),
					) ),
					array( 'p', __( 'Velox reads Oxygen’s stored layouts (classic Oxygen and Oxygen 6), including global classes and colours. Oxygen does not need to be active, and the Oxygen version stays untouched.', 'velox' ) ),
					array( 'note', __( 'Velox lists anything it could not convert; those parts arrive as plain containers or text. Check the page before you publish it.', 'velox' ) ),
				),
			),
		),
	),

	/* ------------------------------------------------------------ 19 */
	array(
		'id'       => 'claude',
		'title'    => __( 'Working with Claude', 'velox' ),
		'intro'    => __( 'Let Claude build and change pages for you, with your own Claude account. Everything it does is a draft you check and publish.', 'velox' ),
		'sections' => array(
			array(
				'title'  => __( 'Two ways to use Claude', 'velox' ),
				'blocks' => array(
					array( 'list', array(
						__( '**Claude with your Claude account (connector):** you chat in Claude on the web, in the desktop or mobile app, or in Claude Code. Your Claude plan covers it: no API key, no extra costs.', 'velox' ),
						__( '**Claude inside the editor (with an API key):** an optional chat panel right in the editor. You pay Anthropic per use.', 'velox' ),
					) ),
					array( 'p', __( 'Either way, Claude only does what your role allows. It reads your design system and uses your classes and design tokens. It works only in drafts: nothing changes for visitors until a person publishes. Every change is kept under History → Saved versions, marked “By AI”.', 'velox' ) ),
				),
			),
			array(
				'title'  => __( 'Switch the connector on', 'velox' ),
				'blocks' => array(
					array( 'path', __( 'Velox Builder → AI agents', 'velox' ) ),
					array( 'steps', array(
						__( 'Switch on **Let Claude work on this site**.', 'velox' ),
						__( 'Copy the **Connector address**.', 'velox' ),
						__( 'Follow the guide for where you use Claude: **Claude (web and app)**, **Claude Code** or **Other apps**.', 'velox' ),
					) ),
					array( 'note', __( 'Claude only connects to https addresses. Switch the site to https first.', 'velox' ) ),
				),
			),
			array(
				'title'  => __( 'Add the site in Claude', 'velox' ),
				'blocks' => array(
					array( 'steps', array(
						__( 'On claude.ai or in the Claude app, open Customize → Connectors, click + and choose “Add custom connector”.', 'velox' ),
						__( 'Paste the connector address and click Add.', 'velox' ),
						__( 'Your site’s sign-in opens. Sign in, choose the whole site or only some pages, and allow.', 'velox' ),
						__( 'In a chat, switch the connector on in the tools menu and say what to build.', 'velox' ),
					) ),
					array( 'tip', __( 'Free plans can add one custom connector. In Team and Enterprise plans, an owner adds it for the organisation first.', 'velox' ) ),
				),
			),
			array(
				'title'  => __( 'Check and manage connections', 'velox' ),
				'blocks' => array(
					array( 'list', array(
						__( '**Check the connection:** tests from the server what Claude will see.', 'velox' ),
						__( '**Connected apps:** who is connected, to which pages and when it was last used. Change the pages or **Disconnect**; the app is signed out at once.', 'velox' ),
					) ),
				),
			),
			array(
				'title'  => __( 'Work on the open page', 'velox' ),
				'blocks' => array(
					array( 'steps', array(
						__( 'In the editor, click **Claude** in the top bar.', 'velox' ),
						__( 'Under **Work on this page in Claude**, type what to do.', 'velox' ),
						__( 'Click **Open in Claude**. Claude opens in a new tab and knows which page you mean. Or copy the request for Claude Desktop or Claude Code.', 'velox' ),
					) ),
					array( 'p', __( 'The page saves first if needed, because Claude works on the saved version. What Claude changes then shows up live in the editor, as a draft; Undo takes it back.', 'velox' ) ),
					array( 'note', __( 'If you have unsaved changes when Claude changes the page, you choose: **Keep mine** or **Load Claude’s version**. Claude’s changes stay under History → Saved versions either way.', 'velox' ) ),
				),
			),
			array(
				'title'  => __( 'The chat inside the editor', 'velox' ),
				'blocks' => array(
					array( 'steps', array(
						__( 'Under **AI agents → Claude inside the editor (with an API key)**, click **Create an API key** and create one in the Claude Console.', 'velox' ),
						__( 'Paste it and click **Connect**. Velox checks the key and stores it encrypted.', 'velox' ),
						__( 'Choose the **Model** and a **Monthly limit** in US dollars (0 means no limit).', 'velox' ),
						__( 'Open a page in the editor and click **Claude** in the top bar.', 'velox' ),
					) ),
					array( 'p', __( 'Designers and administrators can use it. Select an element first to tell Claude which one you mean.', 'velox' ) ),
				),
			),
		),
	),

	/* ------------------------------------------------------------ 20 */
	array(
		'id'       => 'shortcuts',
		'title'    => __( 'Keyboard shortcuts', 'velox' ),
		'intro'    => __( 'The keys the editor listens to, and how to change them. On a Mac, use Cmd instead of Ctrl.', 'velox' ),
		'sections' => array(
			array(
				'title'  => __( 'Save and edit', 'velox' ),
				'blocks' => array(
					array( 'list', array(
						__( 'Ctrl+S: save the draft. Ctrl+Shift+S or Ctrl+Shift+P: publish.', 'velox' ),
						__( 'Ctrl+Z: undo. Ctrl+Shift+Z or Ctrl+Y: redo.', 'velox' ),
						__( 'Ctrl+C, Ctrl+V, Ctrl+D: copy, paste, duplicate. Delete: delete the element.', 'velox' ),
						__( 'Ctrl+Shift+C and Ctrl+Shift+V: copy and paste a style.', 'velox' ),
					) ),
				),
			),
			array(
				'title'  => __( 'Select, move, find and view', 'velox' ),
				'blocks' => array(
					array( 'list', array(
						__( 'Ctrl+Shift+← and →: select the parent or the first child. Ctrl+Shift+↑ and ↓: select the previous or next element.', 'velox' ),
						__( 'Alt+↑ and Alt+↓: move up or down. Alt+←: move out of its container. Alt+→: move into the element above.', 'velox' ),
						__( 'Ctrl+K: command search. Ctrl+Shift+F: find a style property.', 'velox' ),
						__( 'Ctrl+Shift+\\: show or hide the right panels.', 'velox' ),
						__( 'Shift+1: zoom to fit. Shift+0: zoom to 100%.', 'velox' ),
					) ),
				),
			),
			array(
				'title'  => __( 'Keys that always work the same', 'velox' ),
				'blocks' => array(
					array( 'list', array(
						__( 'Double-click: edit text, change an image.', 'velox' ),
						__( 'Esc: select the parent, close a panel or menu, stop editing, cancel a drag.', 'velox' ),
						__( 'Enter: finish editing text. Shift+Enter: new line.', 'velox' ),
						__( 'Ctrl+click or Shift+click: select several elements.', 'velox' ),
						__( '↑ ↓ in a number field: ±1, with Shift ±10, with Alt ±0.1. Tab in a code field: indent.', 'velox' ),
					) ),
				),
			),
			array(
				'title'  => __( 'Change the shortcuts', 'velox' ),
				'blocks' => array(
					array( 'path', __( 'Velox Builder → Keyboard shortcuts', 'velox' ) ),
					array( 'steps', array(
						__( 'Find the action in the list or search for it, for example “save” or “Ctrl+D”.', 'velox' ),
						__( 'Click its keys and press the new combination. **+ Add keys** gives an action another combination; × removes one.', 'velox' ),
						__( 'Click **Save changes** and reload the editor.', 'velox' ),
					) ),
					array( 'p', __( 'Velox tells you what a key normally does in the browser, refuses keys the browser keeps for itself, and marks clashes in red. **Reset all** puts every shortcut back to its default.', 'velox' ) ),
				),
			),
		),
	),

	/* ------------------------------------------------------------ 21 */
	array(
		'id'       => 'troubleshooting',
		'title'    => __( 'Troubleshooting', 'velox' ),
		'intro'    => __( 'What to check when something does not look or behave the way you expect.', 'velox' ),
		'sections' => array(
			array(
				'title'  => __( 'My changes are not on the site', 'velox' ),
				'blocks' => array(
					array( 'list', array(
						__( 'Saving only keeps a draft. Click **Publish**; the badge next to the title should say **Live**.', 'velox' ),
						__( 'A reusable or template that is still a draft is not shown on the site. Publish it too.', 'velox' ),
						__( 'Without **May publish**, an administrator has to publish your changes.', 'velox' ),
					) ),
				),
			),
			array(
				'title'  => __( 'The page looks different than in the editor', 'velox' ),
				'blocks' => array(
					array( 'list', array(
						__( 'Check which template the page uses: **Templates → Which template is used here?** Only published templates are used, and one chosen on the page itself wins.', 'velox' ),
						__( 'Pages made in the WordPress editor only get a template with **Use templates on pages not built with Velox** switched on.', 'velox' ),
						__( 'Check the smaller screen sizes: a value set there only shows on those screens.', 'velox' ),
						__( 'Check **Global styles → Theme**: with theme CSS loaded, the theme can override your styles.', 'velox' ),
						__( 'After changing breakpoints, republish pages that look stale.', 'velox' ),
					) ),
				),
			),
			array(
				'title'  => __( 'Animations, scripts or interactions do nothing in the editor', 'velox' ),
				'blocks' => array(
					array( 'p', __( 'That is on purpose. Scroll animations, motion, interactions and scripts run on the published page. Look at **View → Saved changes** to see them before publishing.', 'velox' ) ),
					array( 'note', __( 'If your system is set to reduce motion, you will not see animations on the site either.', 'velox' ) ),
				),
			),
			array(
				'title'  => __( 'An element will not go where I drop it', 'velox' ),
				'blocks' => array(
					array( 'p', __( 'Sections only go on the page or directly into another section, and Inner Content stays at page level. Use a div block instead of a section inside cards and columns, or wrap the content with **Wrap with div**.', 'velox' ) ),
				),
			),
			array(
				'title'  => __( 'I cannot change an element or find a setting', 'velox' ),
				'blocks' => array(
					array( 'list', array(
						__( 'The element is locked: right-click it and choose **Unlock**.', 'velox' ),
						__( 'The class is locked: unlock it in the class menu.', 'velox' ),
						__( 'The element has its own value that overrides the class: use **Move to class** or **Remove** in the note.', 'velox' ),
						__( 'Your role may not change it: clients change only text, images and links; custom CSS and code stay with administrators.', 'velox' ),
						__( 'A setting seems missing: **Essentials** only shows what fits the element. Open **Advanced**, or type it in **Find a property**, which also finds options that do not apply yet.', 'velox' ),
					) ),
				),
			),
			array(
				'title'  => __( 'I changed something by mistake', 'velox' ),
				'blocks' => array(
					array( 'list', array(
						__( 'Ctrl+Z undoes it, or pick an earlier step under **History → This session**.', 'velox' ),
						__( 'An earlier save or publish: **History → Saved versions → Restore this version**.', 'velox' ),
						__( 'A class, token or global style: **Global styles → Design history → Restore**.', 'velox' ),
					) ),
				),
			),
			array(
				'title'  => __( 'The editor does not open', 'velox' ),
				'blocks' => array(
					array( 'list', array(
						__( 'Make sure Velox Builder is switched on under Velox → Utilities.', 'velox' ),
						__( 'Check that your role has access under Velox Builder → Settings → Who can use the builder.', 'velox' ),
						__( 'An old link to something that was deleted says so instead of opening the editor. Open the page from the Overview.', 'velox' ),
					) ),
				),
			),
		),
	),
);
