<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/** Velox guide — the shop. Read by Velox_Help::guide( 'shop' ). */
return array(

	/* ------------------------------------------------------------ Getting started */
	array(
		'id'       => 'start',
		'title'    => __( 'Getting started', 'velox' ),
		'intro'    => __( 'How to switch the shop on, set it up in five questions and get everything ready for your first sale.', 'velox' ),
		'sections' => array(
			array(
				'title'  => __( 'Switch the shop on', 'velox' ),
				'blocks' => array(
					array( 'path', __( 'Velox → Utilities → Shop', 'velox' ) ),
					array( 'p', __( 'Velox Shop is part of Velox. Switch it on once under **Utilities**, then open **Velox Shop** in the WordPress menu on the left. Orders, products, payments and the design are all in this one screen.', 'velox' ) ),
					array( 'p', __( 'The bar at the top holds every screen: **Overview**, **Revenue**, **Payouts**, **Orders**, **Products**, **Coupons**, **Customers**, **Reviews** and **Settings**. **View shop** at the top right opens your shop in a new tab, and **Help** opens this guide.', 'velox' ) ),
					array( 'tip', __( 'The first time you switch the shop on, a short tour shows you where everything is. You can skip it and start it again at any time under **Settings → Help & tour**.', 'velox' ) ),
				),
			),
			array(
				'title'  => __( 'Set up your shop in five questions', 'velox' ),
				'blocks' => array(
					array( 'path', __( 'Overview → Set up your shop in a minute → Start', 'velox' ) ),
					array( 'steps', array(
						__( '**Do you charge VAT?** Choose prices including VAT, or small business (§ 19 UStG) without VAT.', 'velox' ),
						__( '**What do you sell?** Things you ship, downloads, both, or services.', 'velox' ),
						__( '**Where do you deliver?** Germany; Germany, Austria and Switzerland; the EU; or worldwide.', 'velox' ),
						__( '**How can customers pay?** Tick the methods you want. For bank transfer, enter your IBAN and the account holder right away.', 'velox' ),
						__( '**Your business details:** shop name, email for customers and the address printed on invoices. Then click **Set up my shop**.', 'velox' ),
					) ),
					array( 'p', __( 'Velox fills in taxes, shipping and payments, creates the shop pages and opens your first new product. Everything can be changed later under **Settings**.', 'velox' ) ),
					array( 'tip', __( 'Velox starts with standard shipping in Germany for €4.90, free from €50, and international shipping for €14.90 if you deliver abroad. Change the amounts under **Settings → Shipping**.', 'velox' ) ),
				),
			),
			array(
				'title'  => __( 'Before your first sale', 'velox' ),
				'blocks' => array(
					array( 'list', array(
						__( '**Shop pages:** Shop, Cart, Checkout, My account and Withdraw from a purchase. The setup creates them; missing ones are made with **Create the missing pages** on the overview.', 'velox' ),
						__( '**A payment method:** at least one under **Settings → Payments**. Without one, visitors cannot order.', 'velox' ),
						__( '**Shipping costs:** under **Settings → Shipping**, if you ship things.', 'velox' ),
						__( '**Your business details:** name, address and VAT ID under **Settings → Store**. They are printed on every invoice.', 'velox' ),
						__( '**Legal pages:** terms and conditions, withdrawal policy, privacy policy and shipping information. Write them as normal pages, then choose them under **Settings → Pages & legal**.', 'velox' ),
					) ),
					array( 'p', __( 'Also set up Velox Mail (SMTP) so order emails do not land in spam, and add at least one product set to **Live**.', 'velox' ) ),
					array( 'tip', __( 'The overview lists what is still missing, each with a **Fix** link that takes you to the right place.', 'velox' ) ),
				),
			),
			array(
				'title'  => __( 'Test before you go live', 'velox' ),
				'blocks' => array(
					array( 'p', __( 'Place a test order yourself before your customers do. Signed in as an administrator you see the shop exactly as customers see it.', 'velox' ) ),
					array( 'list', array(
						__( '**Stripe:** enter a test key (it starts with sk_test_). The payment settings then show **Test mode**, and you pay with Stripe’s test card numbers. No real money moves.', 'velox' ),
						__( '**PayPal:** keep **Sandbox (test money)** switched on while you test.', 'velox' ),
						__( '**Bank transfer:** order, then open the order and click **Mark as paid** to see what happens next.', 'velox' ),
						__( '**Emails:** every shop email has **Preview** and **Test** under **Settings → Emails**.', 'velox' ),
					) ),
					array( 'note', __( 'Before you open the shop, switch Stripe to your live key and PayPal off the sandbox. Unpaid test orders can be deleted; paid ones have to stay in your books.', 'velox' ) ),
				),
			),
		),
	),

	/* ------------------------------------------------------------ Overview */
	array(
		'id'       => 'overview',
		'title'    => __( 'Overview', 'velox' ),
		'intro'    => __( 'The overview shows the last 30 days at a glance, and everything that is waiting for you.', 'velox' ),
		'sections' => array(
			array(
				'title'  => __( 'Key figures and warnings', 'velox' ),
				'blocks' => array(
					array( 'path', __( 'Overview', 'velox' ) ),
					array( 'list', array(
						__( '**Revenue:** paid orders of the last 30 days, minus refunds.', 'velox' ),
						__( '**Orders:** how many orders were paid.', 'velox' ),
						__( '**Average order:** what one order brings on average.', 'velox' ),
						__( '**Today:** what came in today.', 'velox' ),
					) ),
					array( 'p', __( 'Below, a line chart shows the revenue per day, with the 30 days before drawn dashed. **Open the report** leads to Revenue.', 'velox' ) ),
					array( 'p', __( 'Above the figures, Velox warns about anything that stops the shop from selling or could cause trouble: no payment method, no cart or checkout page, missing legal pages, no business address, Stripe without a webhook secret, or emails without Velox Mail. Each warning has a **Fix** or **Set up** link.', 'velox' ) ),
				),
			),
			array(
				'title'  => __( 'Needs you and shop pages', 'velox' ),
				'blocks' => array(
					array( 'list', array(
						__( '**orders to ship:** paid and waiting for you to send them.', 'velox' ),
						__( '**awaiting payment:** bank transfer, invoice or not paid yet.', 'velox' ),
						__( '**withdrawals to handle:** customers who withdrew from a purchase.', 'velox' ),
					) ),
					array( 'p', __( 'Click a line under **Needs you** to open the matching orders. The number on **Orders** in the top bar counts paid orders to ship and open withdrawals.', 'velox' ) ),
					array( 'p', __( 'The **Shop pages** card lists every page the shop uses, with a check mark once it is published. **Create the missing pages** makes the shop’s own pages; for legal pages, **Choose** opens **Settings → Pages & legal**. **Edit** opens a page where it is built, in Velox Builder if it has a design there.', 'velox' ) ),
				),
			),
			array(
				'title'  => __( 'Low stock, best sellers, latest orders', 'velox' ),
				'blocks' => array(
					array( 'list', array(
						__( '**Low stock:** products at or below your warning level. One-of-a-kind pieces are left out.', 'velox' ),
						__( '**Best sellers:** the five products that brought in the most in the last 30 days.', 'velox' ),
						__( '**Latest orders:** the newest orders; **All orders** opens the full list.', 'velox' ),
					) ),
					array( 'tip', __( 'Set the warning level under **Settings → Stock & products → Warn me at**. **New product** at the top right of the overview starts a new product.', 'velox' ) ),
				),
			),
		),
	),

	/* ------------------------------------------------------------ Revenue and payouts */
	array(
		'id'       => 'money',
		'title'    => __( 'Revenue and payouts', 'velox' ),
		'intro'    => __( 'Revenue shows how the shop is doing over any period. Payouts shows where your money is and how it reaches your bank.', 'velox' ),
		'sections' => array(
			array(
				'title'  => __( 'Choose a period and a comparison', 'velox' ),
				'blocks' => array(
					array( 'path', __( 'Revenue', 'velox' ) ),
					array( 'p', __( 'Pick **7 days**, **30 days**, **90 days**, **This month**, **Last month**, **12 months**, **This year** or **Last year**. With **Custom** you choose the first and last day and click **Show**.', 'velox' ) ),
					array( 'list', array(
						__( '**Compare:** with the previous period, with the same time last year, or **No comparison**. The comparison is drawn as a dashed line.', 'velox' ),
						__( '**Grouping:** per day, per week or per month. Automatic picks what fits the period.', 'velox' ),
					) ),
				),
			),
			array(
				'title'  => __( 'Filters', 'velox' ),
				'blocks' => array(
					array( 'list', array(
						__( '**Paid orders**, or **All orders, also unpaid**.', 'velox' ),
						__( '**incl. VAT** or **excl. VAT**.', 'velox' ),
						__( 'One payment method, or **All payment methods**.', 'velox' ),
						__( 'One product category, or **All categories**.', 'velox' ),
					) ),
				),
			),
			array(
				'title'  => __( 'Reading the report and exporting it', 'velox' ),
				'blocks' => array(
					array( 'p', __( 'Four tiles show revenue, orders, average order and items sold, each with the change against the comparison. Click a tile to draw it in the chart. Point at the chart to see both values for that day.', 'velox' ) ),
					array( 'p', __( 'Below are the numbers behind the chart, period by period with totals, your best sellers, and the revenue by payment method and by country. Refunds in the period are already taken off the revenue.', 'velox' ) ),
					array( 'p', __( '**Export CSV** downloads the table of the period you are looking at, ready for a spreadsheet or your accountant.', 'velox' ) ),
					array( 'tip', __( 'For a list of every single order with invoice number and VAT, use **Export CSV** on the Orders screen.', 'velox' ) ),
				),
			),
			array(
				'title'  => __( 'Payouts at a glance', 'velox' ),
				'blocks' => array(
					array( 'path', __( 'Payouts', 'velox' ) ),
					array( 'p', __( 'The top shows what came in over the last 30 days with all methods, what is still to come in by bank transfer, invoice or cash on delivery, and, once connected, your balance at Stripe and at PayPal. **Refresh** fetches the latest figures.', 'velox' ) ),
					array( 'tip', __( 'Stripe and PayPal keep their fee before paying out. The amounts shown are what is left for you.', 'velox' ) ),
				),
			),
			array(
				'title'  => __( 'Money from Stripe', 'velox' ),
				'blocks' => array(
					array( 'p', __( 'Stripe collects card, Apple Pay, Google Pay, SEPA and Klarna payments, keeps its fee and pays the rest out to your bank account on a schedule you choose. Velox shows the way: **Available**, **On the way** and **Your bank**, with the schedule and the latest payouts.', 'velox' ) ),
					array( 'p', __( 'If your Stripe account is set to manual payouts, a button pays out the available balance now. It usually arrives in 1–3 working days. **Bank account & schedule in Stripe** opens the place to change both.', 'velox' ) ),
					array( 'note', __( 'In test mode the amounts are test amounts. No real money moves.', 'velox' ) ),
				),
			),
			array(
				'title'  => __( 'Money from PayPal and straight to your account', 'velox' ),
				'blocks' => array(
					array( 'p', __( 'PayPal payments land in your PayPal account. Transfer them to your bank in PayPal; that is usually free and takes 1–2 working days. **Open my PayPal money** takes you there.', 'velox' ) ),
					array( 'p', __( 'Bank transfer, invoice and cash on delivery have no one in between. **Straight to your account** shows what came in and what is still open for each. Click an open amount to see the unpaid orders, and mark each one as paid when the money is there.', 'velox' ) ),
					array( 'tip', __( 'If Velox cannot show your PayPal balance, open your app in the PayPal developer dashboard and switch on “Balances” (or “Transaction search”) under its features.', 'velox' ) ),
				),
			),
		),
	),

	/* ------------------------------------------------------------ Orders */
	array(
		'id'       => 'orders',
		'title'    => __( 'Orders', 'velox' ),
		'intro'    => __( 'How to find an order and take it from payment to delivery, including changes, invoices, refunds, cancellations and withdrawals.', 'velox' ),
		'sections' => array(
			array(
				'title'  => __( 'The order list', 'velox' ),
				'blocks' => array(
					array( 'path', __( 'Orders', 'velox' ) ),
					array( 'p', __( 'Tabs filter the list: **All**, **To ship**, **Unpaid**, **Shipped**, **Cancelled** and **Withdrawals**. The search finds an order by number, name or email. Click a row to open the order.', 'velox' ) ),
					array( 'p', __( 'Every order shows two states: the payment (for example **Awaiting payment**, **Paid**, **Partly refunded**) and the delivery (for example **To ship**, **Shipped**, **Ready for pickup**, **Delivered**). Orders with nothing to ship, such as downloads, only show the payment.', 'velox' ) ),
					array( 'tip', __( '**Export CSV** downloads every order with date, invoice number, customer, items, amounts, VAT and both states.', 'velox' ) ),
				),
			),
			array(
				'title'  => __( 'Mark an order as paid', 'velox' ),
				'blocks' => array(
					array( 'p', __( 'At the top, every open order says in plain words what comes next, for example **Paid — ready to ship** or **Waiting for the money**.', 'velox' ) ),
					array( 'p', __( 'Card and PayPal payments are marked as paid by themselves. Bank transfers, invoices and cash on delivery are paid outside the shop: when the money is in your account, click **Mark as paid**. You can add a **Reference**, such as the date of the bank statement.', 'velox' ) ),
					array( 'p', __( 'The customer gets a payment confirmation, and the order gets its invoice number.', 'velox' ) ),
					array( 'tip', __( 'Still waiting for a bank transfer or an invoice? **Send payment reminder** in the **…** menu sends the customer a reminder.', 'velox' ) ),
				),
			),
			array(
				'title'  => __( 'Mark an order as shipped', 'velox' ),
				'blocks' => array(
					array( 'steps', array(
						__( 'Pack the order and click **Mark as shipped**.', 'velox' ),
						__( 'Choose the **Carrier**: DHL, Deutsche Post, DPD, Hermes, GLS, UPS, FedEx, TNT, Österreichische Post or Swiss Post.', 'velox' ),
						__( 'Enter the **Tracking number**, if you have one.', 'velox' ),
						__( 'Click **Mark as shipped**. The customer gets an email with the tracking link.', 'velox' ),
					) ),
					array( 'p', __( 'For any other carrier choose **Other carrier…**, enter its name and a **Tracking link** with {code} where the tracking number goes. The carrier you used last is chosen for you next time.', 'velox' ) ),
					array( 'p', __( 'Pickup orders have **Ready for pickup** instead: click it when the order is ready, and the customer gets an email. When a parcel has arrived, **Delivered** completes the order.', 'velox' ) ),
					array( 'tip', __( 'Marked as shipped by mistake? **Undo shipping** in the **…** menu sets it back.', 'velox' ) ),
				),
			),
			array(
				'title'  => __( 'Change an order', 'velox' ),
				'blocks' => array(
					array( 'p', __( 'Unpaid orders by bank transfer, invoice or cash on delivery can still be changed. **Edit order** changes quantities and prices, removes or adds products, sets the shipping cost and a discount, or switches between these payment methods.', 'velox' ) ),
					array( 'p', __( 'Stock, VAT and the total are worked out again and the timeline notes the change. With **Email the customer the updated order**, the customer gets the new version.', 'velox' ) ),
					array( 'p', __( '**Edit** on the **Customer** card changes the email, phone, billing address and delivery address. Leave the delivery address empty to deliver to the billing address.', 'velox' ) ),
					array( 'note', __( 'Paid, shipped or invoiced orders are locked, because an invoice must not change afterwards. The order says why. Refund the difference, or cancel the order and take a new one.', 'velox' ) ),
				),
			),
			array(
				'title'  => __( 'Invoices', 'velox' ),
				'blocks' => array(
					array( 'p', __( 'Every order gets an invoice number when it is paid. The numbers run on without gaps, as the tax office expects.', 'velox' ) ),
					array( 'p', __( '**Open invoice** in the **…** menu shows a printable invoice with the details German law asks for. **Print / save as PDF** prints it or saves it as a PDF file.', 'velox' ) ),
					array( 'p', __( 'Need the invoice before the payment? **Create invoice** assigns the number right away.', 'velox' ) ),
					array( 'tip', __( 'The logo, business address, VAT ID and tax number on the invoice come from **Settings → Store**.', 'velox' ) ),
				),
			),
			array(
				'title'  => __( 'Refund money', 'velox' ),
				'blocks' => array(
					array( 'steps', array(
						__( 'Open the order and choose **Refund** in the **…** menu.', 'velox' ),
						__( 'Check the **Amount**. The full open amount is filled in; a smaller amount is a partial refund.', 'velox' ),
						__( 'For card and PayPal payments, leave **Send the money back through …** on: Velox refunds through Stripe or PayPal. Switched off, the refund is only noted and you transfer the money yourself.', 'velox' ),
						__( 'Tick **Put the items back in stock** if the goods came back.', 'velox' ),
						__( 'Click **Refund**. The customer gets an email about the refund.', 'velox' ),
					) ),
					array( 'p', __( 'Bank transfers, invoices and cash on delivery are always refunded by hand: Velox notes the refund on the order, and you transfer the money.', 'velox' ) ),
					array( 'note', __( 'A refund through Stripe or PayPal goes out right away. Check the amount before you click **Refund**.', 'velox' ) ),
				),
			),
			array(
				'title'  => __( 'Cancel, reopen or delete', 'velox' ),
				'blocks' => array(
					array( 'list', array(
						__( '**Cancel order:** the items go back into stock and the customer gets an email. A paid order has to be refunded afterwards.', 'velox' ),
						__( '**Reopen order:** for an order cancelled by mistake. Its items are reserved again, and an unpaid order gets its full payment time again. **Tell the customer by email** decides whether they hear about it.', 'velox' ),
						__( '**Delete:** moves an unpaid or cancelled order to the trash. Paid orders have to be kept for your books.', 'velox' ),
					) ),
					array( 'p', __( 'If an item was sold to someone else in the meantime, Velox does not reopen the order and tells you which item is missing.', 'velox' ) ),
					array( 'tip', __( 'Unpaid card and PayPal orders cancel themselves after the minutes set under **Settings → Checkout → Reserved stock**, and unpaid bank transfers after the days set there.', 'velox' ) ),
				),
			),
			array(
				'title'  => __( 'Withdrawals', 'velox' ),
				'blocks' => array(
					array( 'p', __( 'When a customer withdraws online, you get an email, the order is marked **Withdrawn** and it appears under **Withdrawals**. The order shows the date and the customer’s message.', 'velox' ) ),
					array( 'steps', array(
						__( 'Wait until the goods are back.', 'velox' ),
						__( 'Refund the money within 14 days with **Refund** in the **…** menu.', 'velox' ),
						__( 'Click **Mark as handled**.', 'velox' ),
					) ),
					array( 'note', __( 'The customer gets a confirmation of the withdrawal by themselves. The law requires it, so this email cannot be switched off.', 'velox' ) ),
				),
			),
			array(
				'title'  => __( 'Timeline, notes and emails', 'velox' ),
				'blocks' => array(
					array( 'p', __( 'The **Timeline** records what happened to the order: payment, shipping, reminders, refunds and changes. Write a note and click **Add** to keep something for yourself. Tick **Also email it to the customer** to send it to them as a message about their order.', 'velox' ) ),
					array( 'list', array(
						__( '**Send confirmation again:** the order confirmation, once more.', 'velox' ),
						__( '**View as customer:** the order page your customer sees.', 'velox' ),
						__( '**Came from:** where the customer came from before ordering, when the shop knows it.', 'velox' ),
					) ),
				),
			),
		),
	),

	/* ------------------------------------------------------------ Products */
	array(
		'id'       => 'products',
		'title'    => __( 'Products', 'velox' ),
		'intro'    => __( 'How to add and manage products: photos, prices, options and variants, stock, shipping, downloads and the details German law asks for.', 'velox' ),
		'sections' => array(
			array(
				'title'  => __( 'The product list', 'velox' ),
				'blocks' => array(
					array( 'path', __( 'Products', 'velox' ) ),
					array( 'p', __( 'Tabs show **All**, **Live**, **Drafts** or **Low or out of stock**. Filter by category or search by name, and click a row to open the product.', 'velox' ) ),
					array( 'p', __( 'Click a price or a stock number in the list to change it right there. Enter saves, Esc cancels. Products with variants are changed in the product itself.', 'velox' ) ),
					array( 'p', __( 'Tick products on the left to **Publish**, move them **To drafts**, **Duplicate** or **Delete** them in one go. Deleted products move to the trash; orders that contain them are not affected.', 'velox' ) ),
					array( 'tip', __( 'Bulk edits, imports and exports work in the classic WordPress list: **Settings → Stock & products → Open the classic list**.', 'velox' ) ),
				),
			),
			array(
				'title'  => __( 'Categories', 'velox' ),
				'blocks' => array(
					array( 'p', __( '**Categories** at the top of the list opens the category manager. Add a category, rename one by typing over its name, or delete it. The number shows how many products are in it.', 'velox' ) ),
					array( 'p', __( 'In a product, tick its categories on the right. **Manage** opens the category manager from there.', 'velox' ) ),
				),
			),
			array(
				'title'  => __( 'Name, descriptions and photos', 'velox' ),
				'blocks' => array(
					array( 'path', __( 'Products → New product', 'velox' ) ),
					array( 'list', array(
						__( '**Name:** the product’s name. It is the only field you must fill in, besides the price.', 'velox' ),
						__( '**Short description:** one or two sentences next to the price.', 'velox' ),
						__( '**Description:** everything a buyer wants to know. HTML is allowed.', 'velox' ),
					) ),
					array( 'p', __( '**Add photos** opens the media library, where you can pick several at once. The first photo is the main one. Click ★ on another photo to make it the main photo, or × to remove it.', 'velox' ) ),
				),
			),
			array(
				'title'  => __( 'Price', 'velox' ),
				'blocks' => array(
					array( 'list', array(
						__( '**Price:** what customers pay, including VAT. In small business mode it is shown without VAT.', 'velox' ),
						__( '**Was price:** an older, higher price, shown crossed out as a sale.', 'velox' ),
						__( '**Sale ends after:** the last day of the sale. Empty means until you remove the was price.', 'velox' ),
						__( '**VAT rate:** standard, reduced or 0 %.', 'velox' ),
						__( '**Price on request:** no cart button; visitors ask you instead. For one-off or custom work.', 'velox' ),
					) ),
					array( 'p', __( '**Customers see** shows the price exactly as it appears in the shop, with the VAT note, “plus shipping” and the unit price.', 'velox' ) ),
				),
			),
			array(
				'title'  => __( 'Type and stock', 'velox' ),
				'blocks' => array(
					array( 'list', array(
						__( '**What is it?** **Physical**, **Download** or **Service**. Only physical products are shipped.', 'velox' ),
						__( '**One of a kind:** sells exactly once, then shows “Sold”. For art, vintage and originals.', 'velox' ),
						__( '**Count stock:** stops selling when it runs out and warns you when it runs low. Enter the number **In stock** and, if you like, a **Max. per order**.', 'velox' ),
						__( '**Keep selling when sold out:** the product stays orderable and shows “Available to order”.', 'velox' ),
						__( '**Item number (SKU)** and **EAN / GTIN:** optional. They also go into the product data for Google.', 'velox' ),
					) ),
					array( 'tip', __( 'When customers asked to be told that a sold-out product is back, the product says how many are waiting. They get one email as soon as it can be bought again.', 'velox' ) ),
				),
			),
			array(
				'title'  => __( 'Options and variants', 'velox' ),
				'blocks' => array(
					array( 'steps', array(
						__( 'Under **Options**, click **Add option** and give it a name, for example “Size”.', 'velox' ),
						__( 'Type the values, for example S, M and L, and press Enter after each one.', 'velox' ),
						__( 'Add up to three options, for example size and colour.', 'velox' ),
						__( 'Velox makes a variant for every combination. Give each one its own price, was price, stock, SKU and photo, or leave the price empty to use the product price.', 'velox' ),
						__( 'Switch off **On sale** for combinations you don’t sell.', 'velox' ),
					) ),
					array( 'tip', __( 'An option called “Colour” (or “Farbe”) gets a colour field for each value, filled in from the name. These colours show as dots on product cards and in the filters.', 'velox' ) ),
					array( 'note', __( 'Removing an option or a value also removes its variants. One-of-a-kind pieces have no options.', 'velox' ) ),
				),
			),
			array(
				'title'  => __( 'Shipping and downloads', 'velox' ),
				'blocks' => array(
					array( 'list', array(
						__( '**Delivery time:** for example “2–4 working days”. Required in Germany before ordering; customers see it at checkout too.', 'velox' ),
						__( '**Weight:** in kg, optional.', 'velox' ),
					) ),
					array( 'p', __( 'For a **Download**, add the files under **Files to download**. Buyers get private download links after paying, in the order and in their account. The files are never linked publicly.', 'velox' ) ),
					array( 'note', __( 'At checkout, buyers of downloads must agree that the download starts before the withdrawal period ends. Velox adds this checkbox by itself.', 'velox' ) ),
				),
			),
			array(
				'title'  => __( 'Legal information and details', 'velox' ),
				'blocks' => array(
					array( 'list', array(
						__( '**Contents** and **Unit:** for goods sold by weight, volume or length, for example 250 and g. Velox works out the price per kg, l or m and shows it next to the price, as the PAngV requires.', 'velox' ),
						__( '**Manufacturer and safety information:** name and address of the manufacturer (or the EU importer), plus any warnings. The EU product safety regulation (GPSR) requires it.', 'velox' ),
					) ),
					array( 'p', __( '**Details** appear as a table on the product page: material, size, care, year … Click **Add a line**, then enter the name on the left and the value on the right.', 'velox' ) ),
				),
			),
			array(
				'title'  => __( 'Visibility, extras and saving', 'velox' ),
				'blocks' => array(
					array( 'list', array(
						__( '**Visibility:** **Live** is visible in the shop and can be bought; a **Draft** only you can see.', 'velox' ),
						__( '**Badge:** a small label on the photo, for example “New” or “Bestseller”.', 'velox' ),
						__( '**Button text:** your own words instead of “Add to cart”.', 'velox' ),
						__( '**Web address:** the last part of the product’s address. Empty takes it from the name.', 'velox' ),
						__( '**Recommended with it:** products suggested on this product’s page. Type a name and pick it from the list.', 'velox' ),
					) ),
					array( 'p', __( 'Click **Create product**, later **Save**, at the bottom. **View** at the top opens the product in the shop. **Delete** moves it to the trash; orders that contain it are not affected.', 'velox' ) ),
					array( 'tip', __( 'A product is only for sale once its visibility is **Live**.', 'velox' ) ),
				),
			),
		),
	),

	/* ------------------------------------------------------------ Coupons */
	array(
		'id'       => 'coupons',
		'title'    => __( 'Coupons', 'velox' ),
		'intro'    => __( 'Discount codes that customers type in at the cart or the checkout.', 'velox' ),
		'sections' => array(
			array(
				'title'  => __( 'Create a code', 'velox' ),
				'blocks' => array(
					array( 'path', __( 'Coupons → New code', 'velox' ) ),
					array( 'steps', array(
						__( 'Click **New code**.', 'velox' ),
						__( 'Type the **Code**: letters, numbers, dashes and underscores. Velox writes it in capitals.', 'velox' ),
						__( 'Choose the **Type** and enter the percentage or the amount.', 'velox' ),
						__( 'Set limits if you like, then click **Save**.', 'velox' ),
					) ),
				),
			),
			array(
				'title'  => __( 'Types of codes', 'velox' ),
				'blocks' => array(
					array( 'list', array(
						__( '**% off:** a percentage off the order.', 'velox' ),
						__( '**Amount off:** a fixed amount off the order.', 'velox' ),
						__( '**Free shipping:** no shipping costs for this order.', 'velox' ),
					) ),
				),
			),
			array(
				'title'  => __( 'Limits and switching off', 'velox' ),
				'blocks' => array(
					array( 'list', array(
						__( '**Minimum order:** the code only works from this order value.', 'velox' ),
						__( '**Valid until:** the last day the code works.', 'velox' ),
						__( '**Uses:** how often the code can be used in total. Empty means without a limit. Velox counts how often it was used.', 'velox' ),
					) ),
					array( 'p', __( 'Switch **Active** off to pause a code without deleting it. The bin removes a code once you save.', 'velox' ) ),
					array( 'tip', __( 'A code can also go into the cart reminder email: choose it under **Settings → Storefront → Cart reminders**.', 'velox' ) ),
				),
			),
		),
	),

	/* ------------------------------------------------------------ Customers */
	array(
		'id'       => 'customers',
		'title'    => __( 'Customers', 'velox' ),
		'intro'    => __( 'Everyone who has an account or has ordered, and what you can do for them.', 'velox' ),
		'sections' => array(
			array(
				'title'  => __( 'The customer list', 'velox' ),
				'blocks' => array(
					array( 'path', __( 'Customers', 'velox' ) ),
					array( 'p', __( 'The list shows customer accounts and guests together, with their orders, what they spent and when they were last active. Tabs show **All**, **With account**, **Guests** or **Locked**, and the search finds a name or email. Accounts of your team are marked **Staff**.', 'velox' ) ),
				),
			),
			array(
				'title'  => __( 'One customer', 'velox' ),
				'blocks' => array(
					array( 'p', __( 'Click a customer to see all their orders, their details and address, and at a glance: orders, money spent, customer since, last sign-in, two-factor sign-in and privacy consent. Each order opens with one click.', 'velox' ) ),
					array( 'p', __( 'For an account you can change the name, email and address and click **Save**. The customer signs in with the email; the old address is told about the change.', 'velox' ) ),
					array( 'p', __( 'Guests have no account to change. Edit their details in the order, or click **Create account**: Velox makes an account for the guest’s email, links all their orders to it and, with **Email the customer a link to choose a password**, sends them a link to set their password.', 'velox' ) ),
				),
			),
			array(
				'title'  => __( 'Passwords, sign-in and emails', 'velox' ),
				'blocks' => array(
					array( 'list', array(
						__( '**Send password link:** the customer gets a link to choose a new password. It works for 24 hours; the current password keeps working until then.', 'velox' ),
						__( '**Sign out on all devices:** ends every session of this account.', 'velox' ),
						__( '**Turn off two-factor sign-in:** for a customer who lost their phone and their recovery codes. Make sure it really is them first, for example by replying to an email from their address.', 'velox' ),
						__( '**Write an email:** a message in your shop’s email design. Replies go to your shop email.', 'velox' ),
					) ),
					array( 'note', __( 'The shop never shows or sets passwords. The customer always chooses one with the link you send.', 'velox' ) ),
				),
			),
			array(
				'title'  => __( 'Lock, export or delete an account', 'velox' ),
				'blocks' => array(
					array( 'list', array(
						__( '**Lock account:** the customer is signed out and cannot sign in until you unlock the account. Orders and data stay, and ordering as a guest still works.', 'velox' ),
						__( '**Download their data:** everything the shop stores about the customer, as a file. Useful when a customer asks for their data under the GDPR.', 'velox' ),
						__( '**Delete account:** the account and saved address are deleted for good. Orders are kept, as tax law requires, but no longer linked to an account.', 'velox' ),
					) ),
					array( 'note', __( 'Deleting an account cannot be undone.', 'velox' ) ),
				),
			),
		),
	),

	/* ------------------------------------------------------------ Reviews */
	array(
		'id'       => 'reviews',
		'title'    => __( 'Reviews', 'velox' ),
		'intro'    => __( 'Star reviews on your product pages: how to switch them on, check them and reply.', 'velox' ),
		'sections' => array(
			array(
				'title'  => __( 'Switch reviews on', 'velox' ),
				'blocks' => array(
					array( 'path', __( 'Settings → Storefront → Reviews', 'velox' ) ),
					array( 'list', array(
						__( '**Star reviews on product pages:** with the average under the product name and in Google’s search results.', 'velox' ),
						__( '**Check reviews before they appear:** new reviews wait under Reviews until you publish them. This is on from the start.', 'velox' ),
						__( '**Only buyers may review:** the email address must belong to a paid order with this product. Switched off, anyone may review, and reviews from buyers are marked “Verified purchase”.', 'velox' ),
						__( '**Stars on product cards:** the rating in the product grid too.', 'velox' ),
					) ),
				),
			),
			array(
				'title'  => __( 'What customers see', 'velox' ),
				'blocks' => array(
					array( 'p', __( 'Under each product: the average, how the stars are spread, the reviews and a form to write one. Each email address can review a product once, and the form keeps spam out.', 'velox' ) ),
					array( 'p', __( 'The product page also says how reviews are checked, as German law (§ 5b UWG) requires. Velox writes this sentence to match your settings.', 'velox' ) ),
				),
			),
			array(
				'title'  => __( 'Handle reviews', 'velox' ),
				'blocks' => array(
					array( 'path', __( 'Reviews', 'velox' ) ),
					array( 'p', __( 'Tabs show **Waiting**, **Published** or **All** reviews. The number on **Reviews** in the top bar counts the waiting ones.', 'velox' ) ),
					array( 'list', array(
						__( '**Publish:** shows the review on the product page.', 'velox' ),
						__( '**Hide:** takes a published review off the page again.', 'velox' ),
						__( '**Reply:** your answer appears under the review, with your shop’s name. Leave it empty to remove it.', 'velox' ),
						__( 'The bin moves a review to the trash. You can restore it under Comments → Trash in WordPress.', 'velox' ),
					) ),
					array( 'tip', __( 'Ask happy customers for a review. It helps the next buyer most.', 'velox' ) ),
				),
			),
		),
	),

	/* ------------------------------------------------------------ Store and taxes */
	array(
		'id'       => 'store',
		'title'    => __( 'Store and taxes', 'velox' ),
		'intro'    => __( 'Your business details, how prices are shown, and how VAT works in your shop.', 'velox' ),
		'sections' => array(
			array(
				'title'  => __( 'Your business', 'velox' ),
				'blocks' => array(
					array( 'path', __( 'Settings → Store', 'velox' ) ),
					array( 'list', array(
						__( '**Shop name** and **Email for customers:** replies to order emails go to this address.', 'velox' ),
						__( '**Business address:** company name, street, postcode and town, printed on invoices.', 'velox' ),
						__( '**VAT ID** and **Tax number:** printed on invoices.', 'velox' ),
						__( '**Country of your business:** where your business is based.', 'velox' ),
					) ),
				),
			),
			array(
				'title'  => __( 'Look and money', 'velox' ),
				'blocks' => array(
					array( 'list', array(
						__( '**Logo for emails and invoices:** **Choose** picks it from the media library.', 'velox' ),
						__( '**Accent colour:** buttons and links in emails and on invoices.', 'velox' ),
						__( '**Currency**, **Symbol** and **Position:** euro, Swiss franc, pound or dollar, with the symbol after the amount (49,90 €) or before it (€ 49,90).', 'velox' ),
					) ),
					array( 'tip', __( 'The colours, fonts and layout of the shop itself are under **Settings → Design**.', 'velox' ) ),
				),
			),
			array(
				'title'  => __( 'VAT', 'velox' ),
				'blocks' => array(
					array( 'path', __( 'Settings → Taxes', 'velox' ) ),
					array( 'p', __( 'With **Prices include VAT** you enter and show every price including VAT, as German shops must. Set the **Standard rate** (19 %) and the **Reduced rate** (7 %, for example for books, food or art); each product chooses its rate.', 'velox' ) ),
					array( 'p', __( 'Shipping is taxed at the standard rate. Invoices list the VAT per rate.', 'velox' ) ),
				),
			),
			array(
				'title'  => __( 'Small business (§ 19 UStG)', 'velox' ),
				'blocks' => array(
					array( 'p', __( 'As a Kleinunternehmer, choose **Small business — no VAT**. No VAT is charged or shown, and the **Note on invoices and at checkout** says why. Velox starts with: “According to § 19 UStG no VAT is charged.”', 'velox' ) ),
					array( 'note', __( 'If you passed €25,000 last year or pass €100,000 this year, switch back to **Prices include VAT**.', 'velox' ) ),
				),
			),
		),
	),

	/* ------------------------------------------------------------ Shipping */
	array(
		'id'       => 'shipping',
		'title'    => __( 'Shipping', 'velox' ),
		'intro'    => __( 'Where you deliver, what it costs, when it is free, and pickup.', 'velox' ),
		'sections' => array(
			array(
				'title'  => __( 'Where you deliver', 'velox' ),
				'blocks' => array(
					array( 'path', __( 'Settings → Shipping', 'velox' ) ),
					array( 'p', __( '**Countries customers can choose** are the countries in the checkout’s country field. Add one with **+ country** and remove one with ×. Empty means every country in the list.', 'velox' ) ),
				),
			),
			array(
				'title'  => __( 'Shipping zones', 'velox' ),
				'blocks' => array(
					array( 'p', __( 'A zone is a group of countries with its own shipping prices. The delivery country picks the zone: the first zone that lists the country wins, and a zone without countries catches all the rest.', 'velox' ) ),
					array( 'p', __( '**Add a zone** adds one. Give it a **Zone name** and add its countries; the bin deletes a zone.', 'velox' ) ),
					array( 'tip', __( 'For most German shops two zones are enough: Germany, and everywhere else.', 'velox' ) ),
				),
			),
			array(
				'title'  => __( 'Shipping methods', 'velox' ),
				'blocks' => array(
					array( 'p', __( 'Each zone has one or more methods the customer chooses from, for example standard and express. **+ Add a shipping method** adds one.', 'velox' ) ),
					array( 'list', array(
						__( '**Method:** the name customers see.', 'velox' ),
						__( '**Cost:** the shipping price.', 'velox' ),
						__( '**Free from:** from this order value shipping is free. Empty means never free.', 'velox' ),
						__( '**Working days:** the delivery time, for example 2–4.', 'velox' ),
					) ),
					array( 'tip', __( 'The mini cart shows customers how much is still missing for free shipping.', 'velox' ) ),
				),
			),
			array(
				'title'  => __( 'Pickup, downloads and services', 'velox' ),
				'blocks' => array(
					array( 'p', __( 'Switch on **Customers can pick up their order** and enter a **Name** and the **Address and opening hours**. Pickup is free. When the order is ready, click **Ready for pickup** in the order, and the customer gets an email.', 'velox' ) ),
					array( 'p', __( 'Orders with only downloads or services need no shipping: the checkout skips the delivery details and adds no shipping costs.', 'velox' ) ),
				),
			),
		),
	),

	/* ------------------------------------------------------------ Payments */
	array(
		'id'       => 'payments',
		'title'    => __( 'Payments', 'velox' ),
		'intro'    => __( 'The payment methods customers can choose, and how to connect Stripe and PayPal.', 'velox' ),
		'sections' => array(
			array(
				'title'  => __( 'Choose the payment methods', 'velox' ),
				'blocks' => array(
					array( 'path', __( 'Settings → Payments', 'velox' ) ),
					array( 'p', __( 'Switch each method on or off; its settings open below it. The arrows set the order customers see at checkout.', 'velox' ) ),
					array( 'list', array(
						__( '**Card, Apple Pay, Google Pay & more:** through Stripe.', 'velox' ),
						__( '**PayPal:** paid on PayPal’s page.', 'velox' ),
						__( '**Bank transfer:** customers get your bank details right after ordering.', 'velox' ),
						__( '**Invoice:** customers pay after receiving the goods.', 'velox' ),
						__( '**Cash on delivery:** customers pay when the parcel arrives.', 'velox' ),
					) ),
					array( 'tip', __( 'Card and PayPal payments happen on the provider’s page. No card data ever touches your site.', 'velox' ) ),
				),
			),
			array(
				'title'  => __( 'Bank transfer', 'velox' ),
				'blocks' => array(
					array( 'p', __( 'Enter the **Account holder** and the **IBAN**, and if you like the **BIC** and the **Bank**. Velox checks the IBAN when you save.', 'velox' ) ),
					array( 'p', __( 'When the money arrives, mark the order as paid. Unpaid bank transfers are cancelled after the days set under **Settings → Checkout → Reserved stock**.', 'velox' ) ),
				),
			),
			array(
				'title'  => __( 'Invoice and cash on delivery', 'velox' ),
				'blocks' => array(
					array( 'list', array(
						__( '**Only for customers who have paid before:** protects you from not getting paid. On from the start.', 'velox' ),
						__( '**Payment term:** the days customers have to pay, printed on the invoice.', 'velox' ),
						__( '**Extra fee:** for cash on delivery, shown separately at checkout.', 'velox' ),
						__( '**Only up to an order value of:** for both methods. Larger orders do not see the method.', 'velox' ),
					) ),
				),
			),
			array(
				'title'  => __( 'Connect Stripe', 'velox' ),
				'blocks' => array(
					array( 'steps', array(
						__( 'In Stripe, open Developers → API keys and copy the secret key into **Secret key**.', 'velox' ),
						__( 'In Stripe, open Developers → Webhooks and add the address Velox shows (the copy button copies it), with the events checkout.session.completed and checkout.session.async_payment_succeeded.', 'velox' ),
						__( 'Copy the signing secret of this webhook into **Webhook signing secret**.', 'velox' ),
						__( 'In Stripe, switch on what you want to offer: cards, Apple Pay, Google Pay, SEPA, Klarna …', 'velox' ),
						__( 'Click **Save**, then **Test the connection**.', 'velox' ),
					) ),
					array( 'note', __( 'Without the webhook secret, payments are still confirmed when customers come back to the shop, but not if they close the tab after paying. Always add it.', 'velox' ) ),
				),
			),
			array(
				'title'  => __( 'Stripe methods and test mode', 'velox' ),
				'blocks' => array(
					array( 'p', __( 'Under **What customers see at Stripe** tick the methods to offer: cards (with Apple Pay and Google Pay), SEPA Direct Debit, Klarna, PayPal through Stripe, EPS, iDEAL, Bancontact, Przelewy24 and Link. Nothing ticked means whatever is switched on in your Stripe dashboard.', 'velox' ) ),
					array( 'p', __( 'A secret key that starts with sk_test_ puts Stripe in **Test mode**: payments use test money, and Payouts shows test amounts. A key that starts with sk_live_ shows **Live**.', 'velox' ) ),
					array( 'tip', __( 'A method Stripe cannot offer for an order (not active, another currency) is left out by itself, so the checkout always works.', 'velox' ) ),
				),
			),
			array(
				'title'  => __( 'Connect PayPal', 'velox' ),
				'blocks' => array(
					array( 'steps', array(
						__( 'On developer.paypal.com, open Apps & Credentials and create an app.', 'velox' ),
						__( 'Copy its Client ID and secret into **Client ID** and **Secret**.', 'velox' ),
						__( 'Keep **Sandbox (test money)** on while you test.', 'velox' ),
						__( 'Click **Save**, then **Test the connection**.', 'velox' ),
						__( 'When you go live, switch the sandbox off and use the Client ID and secret of your live app.', 'velox' ),
					) ),
				),
			),
			array(
				'title'  => __( 'When an order counts as paid', 'velox' ),
				'blocks' => array(
					array( 'p', __( 'The order is created first. With card or PayPal it only counts as paid once Stripe or PayPal confirms the payment; Velox checks that with the provider itself.', 'velox' ) ),
					array( 'p', __( 'If a customer does not finish paying, the order is cancelled after the minutes set under **Settings → Checkout → Reserved stock**, and the stock goes back.', 'velox' ) ),
					array( 'tip', __( 'Bank transfer, invoice and cash on delivery orders stay unpaid until you click **Mark as paid** in the order.', 'velox' ) ),
				),
			),
		),
	),

	/* ------------------------------------------------------------ Checkout and emails */
	array(
		'id'       => 'checkout',
		'title'    => __( 'Checkout and emails', 'velox' ),
		'intro'    => __( 'The order form, customer accounts, order and invoice numbers, and every email the shop sends.', 'velox' ),
		'sections' => array(
			array(
				'title'  => __( 'How the checkout works', 'velox' ),
				'blocks' => array(
					array( 'p', __( 'The checkout is one page in three steps: contact, delivery (or billing address) and payment. Next to it sits the order summary with the discount code field, the totals and the order button. The totals update as customers make their choices.', 'velox' ) ),
					array( 'p', __( 'After ordering, the checkout page shows the order with what to pay and how.', 'velox' ) ),
				),
			),
			array(
				'title'  => __( 'Customer accounts', 'velox' ),
				'blocks' => array(
					array( 'path', __( 'Settings → Checkout → Customer accounts', 'velox' ) ),
					array( 'list', array(
						__( '**Customers can create an account:** on the account page customers sign in, create an account or reset a forgotten password, without WordPress’s own login page. Customers never see the dashboard.', 'velox' ),
						__( '**Offer an account while ordering:** a box in the checkout. Tick it, choose a password, done.', 'velox' ),
					) ),
					array( 'p', __( 'Ordering without an account always stays possible.', 'velox' ) ),
				),
			),
			array(
				'title'  => __( 'The order form', 'velox' ),
				'blocks' => array(
					array( 'list', array(
						__( '**Phone number** and **Company:** **Don’t ask**, **Optional** or **Required**.', 'velox' ),
						__( '**Customers can add a note:** a note field for the order.', 'velox' ),
						__( '**Customers tick “I accept the terms”:** common in Germany. The terms are part of the contract either way.', 'velox' ),
						__( '**Order button text:** your own words for the order button. Empty uses the safe default.', 'velox' ),
						__( '**Minimum order value:** below it, customers cannot order.', 'velox' ),
					) ),
					array( 'note', __( 'German law requires the order button to say clearly that ordering costs money, for example “zahlungspflichtig bestellen” or “Jetzt kaufen”. If you write your own text, keep that meaning.', 'velox' ) ),
				),
			),
			array(
				'title'  => __( 'Reserved stock', 'velox' ),
				'blocks' => array(
					array( 'list', array(
						__( '**Card and PayPal orders:** not paid within these minutes, the order is cancelled and the stock goes back.', 'velox' ),
						__( '**Bank transfers:** after these days without payment, the order is cancelled. 0 means never cancel automatically.', 'velox' ),
					) ),
				),
			),
			array(
				'title'  => __( 'Order and invoice numbers', 'velox' ),
				'blocks' => array(
					array( 'list', array(
						__( '**Order number prefix:** for example “SHOP-” makes SHOP-1001.', 'velox' ),
						__( '**Invoice number:** the start of every invoice number, where {Y} is the year. Velox starts with RE-{Y}-, which makes RE-2026-1.', 'velox' ),
					) ),
					array( 'note', __( 'Invoice numbers run on without gaps, as the tax office expects. Change the prefix at the start of a year, not in the middle.', 'velox' ) ),
				),
			),
			array(
				'title'  => __( 'Sender and footer', 'velox' ),
				'blocks' => array(
					array( 'path', __( 'Settings → Emails', 'velox' ) ),
					array( 'list', array(
						__( '**Sender name** and **Sender address:** who the emails come from. An empty sender address uses the site default.', 'velox' ),
						__( '**New orders go to:** your address for new orders and withdrawals.', 'velox' ),
						__( '**Footer:** the text at the bottom of every email. Empty uses the shop name and address.', 'velox' ),
					) ),
					array( 'note', __( 'Without Velox Mail (SMTP), emails go out through the server’s mail function and often end up in spam. **Set up** opens Velox Mail.', 'velox' ) ),
				),
			),
			array(
				'title'  => __( 'Emails customers get', 'velox' ),
				'blocks' => array(
					array( 'list', array(
						__( '**Order confirmation:** right after ordering, with what to pay and how, plus your terms and withdrawal policy.', 'velox' ),
						__( '**Payment received:** when the payment has arrived.', 'velox' ),
						__( '**Shipped:** when you mark the order as shipped, with tracking.', 'velox' ),
						__( '**Cancelled:** when an order is cancelled.', 'velox' ),
						__( '**Order active again:** when you reopen a cancelled order.', 'velox' ),
						__( '**Refund:** when money goes back.', 'velox' ),
						__( '**Withdrawal received:** the confirmation of a withdrawal. The law requires it, so it is always on.', 'velox' ),
						__( '**Payment reminder:** sent by you from an unpaid order.', 'velox' ),
						__( '**New order (to you):** to your shop address for every new order and withdrawal.', 'velox' ),
					) ),
				),
			),
			array(
				'title'  => __( 'Check and switch emails', 'velox' ),
				'blocks' => array(
					array( 'p', __( 'Switch each email on or off. **Preview** shows it in your shop’s design; **Test** sends an example to your own email address.', 'velox' ) ),
					array( 'tip', __( 'The wording of every email can be changed under **Settings → Texts**.', 'velox' ) ),
				),
			),
		),
	),

	/* ------------------------------------------------------------ Design */
	array(
		'id'       => 'design',
		'title'    => __( 'Design', 'velox' ),
		'intro'    => __( 'How the shop looks: a template, your own choices on top, page by page, tried on the real shop before you save.', 'velox' ),
		'sections' => array(
			array(
				'title'  => __( 'Pick a template', 'velox' ),
				'blocks' => array(
					array( 'path', __( 'Settings → Design → Template', 'velox' ) ),
					array( 'p', __( 'A template is a complete set of choices: colours, fonts, cards, photo shape, buttons, badges, hover and product page. Each tile shows the template with your own products. Click one, check the preview, then click **Save** to put it live.', 'velox' ) ),
					array( 'list', array(
						__( '**Velox:** clean and quiet, fits almost anything.', 'velox' ),
						__( '**Atelier:** art, prints and handmade work, uncropped on warm paper.', 'velox' ),
						__( '**Nordic:** home and furniture, products cut out on light grey.', 'velox' ),
						__( '**Maison:** fragrance, beauty and jewellery, sand tones and a fine serif.', 'velox' ),
						__( '**Journal:** editorial rows like a magazine.', 'velox' ),
						__( '**Bold:** streetwear, sport and drinks, black frames and huge capitals.', 'velox' ),
						__( '**Candy:** beauty, kids and gifts, pastel tiles and round corners.', 'velox' ),
						__( '**Circuit:** tech and gadgets, rounded tiles and centred products.', 'velox' ),
						__( '**Noir:** a truly dark shop for gaming, audio and nightlife.', 'velox' ),
						__( '**Manufaktur:** tools, kitchen and everyday goods, framed cards with all the details.', 'velox' ),
						__( '**Your own design:** a neutral base that takes your site’s colours and fonts.', 'velox' ),
					) ),
				),
			),
			array(
				'title'  => __( 'Your own choices on top', 'velox' ),
				'blocks' => array(
					array( 'p', __( 'Every choice below the templates starts at **Template**, which keeps the template’s value. Change any of them, and your choice wins over the template. A line under the tiles counts your changes; **Back to the pure template** clears them.', 'velox' ) ),
					array( 'p', __( 'Picking another template starts fresh: your changes to the old one are cleared. The message has **Undo** if you change your mind.', 'velox' ) ),
					array( 'note', __( 'Your own colours over a template are why a dark template can look light. A note above the templates lists them, with **Use … as it comes** to clear them.', 'velox' ) ),
				),
			),
			array(
				'title'  => __( 'Preview and try it on the real shop', 'velox' ),
				'blocks' => array(
					array( 'p', __( 'The **Preview** draws your real products with the choices on the page, before you save. Switch between **Desktop**, **Tablet** and **Phone**.', 'velox' ) ),
					array( 'p', __( 'Under **Try it on the real shop, before saving**, open the **Shop page**, **A product**, the **Cart**, the **Checkout** or the **Account** with your unsaved choices. Only you see it, for 30 minutes; visitors keep the saved design.', 'velox' ) ),
				),
			),
			array(
				'title'  => __( 'Everywhere: colours', 'velox' ),
				'blocks' => array(
					array( 'p', __( 'The choices under **Everywhere** apply to every shop page. **Colours** sets nine colours: **Page**, **Cards**, **Photo backdrop**, **Text**, **Quiet text**, **Lines**, **Buttons**, **Text on buttons** and **Sale price**. The × next to a colour takes it back to the template.', 'velox' ) ),
					array( 'p', __( '**Page background** decides whether the whole page behind the shop takes the template colour, which suits dark or tinted templates, or keeps your site’s background.', 'velox' ) ),
				),
			),
			array(
				'title'  => __( 'Everywhere: typography and buttons', 'velox' ),
				'blocks' => array(
					array( 'list', array(
						__( '**Headings** and **Text:** one font each, from 25 fonts.', 'velox' ),
						__( '**Heading weight**, **Headings in** normal case or capitals, and **Letter spacing in headings**.', 'velox' ),
						__( '**Small labels:** category, badges and option names in normal letters or spaced capitals.', 'velox' ),
						__( '**Button shape**, **Buttons in the grid** (filled or outline), **Button text** in capitals or not, and the **Corner radius** of cards, photos and fields.', 'velox' ),
					) ),
					array( 'tip', __( 'Fonts are downloaded once and served from your own site. No visitor ever connects to Google.', 'velox' ) ),
				),
			),
			array(
				'title'  => __( 'Each page: the shop page', 'velox' ),
				'blocks' => array(
					array( 'p', __( 'Under **Each page**, every page has a section that opens and closes. **Shop page** covers the categories, the product grid and every card:', 'velox' ) ),
					array( 'list', array(
						__( '**Categories above the products:** rounded buttons, underlined words or solid blocks.', 'velox' ),
						__( '**Layout:** grid, masonry, list or scroll row, with the columns on desktop, tablets and phones and the space between columns and rows.', 'velox' ),
						__( '**Each product as:** photo and text, on a tile, a card, framed, text on the photo, or side by side.', 'velox' ),
						__( '**Photo shape**, **Photo in its frame** and **Behind the photo**.', 'velox' ),
						__( '**On hover:** second photo, fade to second, zoom, lift, shadow, glow or nothing. Also the text alignment, what the card shows, the price style, the **Buy button** and the badges.', 'velox' ),
					) ),
					array( 'tip', __( 'Sorting, search and filters are switched on under **Settings → Storefront**.', 'velox' ) ),
				),
			),
			array(
				'title'  => __( 'Each page: product, cart and account', 'velox' ),
				'blocks' => array(
					array( 'list', array(
						__( '**Product page:** the **Layout** (photos left, photos right, big photos, photos on top), the **Photos** (thumbnails below or at the side, all stacked, a photo grid), whether the buy column stays in view **While scrolling**, and whether the **Details** are all open or fold out.', 'velox' ),
						__( '**Cart & checkout:** the **Checkout** in two columns or one, the **Cart icon**, how the **Items in the cart** are shown, and the **Cart total next to the icon**.', 'velox' ),
						__( '**Customer account:** the **Menu** as tabs on top, a list at the side or rounded buttons. On phones the menu is always on top.', 'velox' ),
					) ),
				),
			),
			array(
				'title'  => __( 'Build a page yourself in Velox Builder', 'velox' ),
				'blocks' => array(
					array( 'p', __( 'Each page section ends with **Or build it yourself:** and a button such as “Shop in Velox Builder”. It turns that shop page into a Velox Builder page that holds the same shop part as an element, so you can frame and style it by hand.', 'velox' ) ),
					array( 'p', __( 'The page keeps showing the old way until you publish it in the builder. For product pages, **Design product pages yourself** leads to **Settings → Pages & legal**.', 'velox' ) ),
					array( 'note', __( 'This needs Velox Builder switched on. A greyed-out button means the page does not exist yet: create it under **Pages & legal**.', 'velox' ) ),
				),
			),
			array(
				'title'  => __( 'Your own CSS', 'velox' ),
				'blocks' => array(
					array( 'p', __( '**Your own CSS** is loaded after the shop’s own styles, on every page with shop parts. **Every part you can style** lists the shop’s classes, which you insert with a click, and the variables for colours and sizes.', 'velox' ) ),
					array( 'tip', __( 'The same classes can be styled in Velox Builder too.', 'velox' ) ),
				),
			),
		),
	),

	/* ------------------------------------------------------------ Storefront */
	array(
		'id'       => 'storefront',
		'title'    => __( 'Storefront', 'velox' ),
		'intro'    => __( 'How the shop behaves for visitors: the cart, the shop page, product pages, wishlist, back-in-stock emails, cart reminders and the notice bar.', 'velox' ),
		'sections' => array(
			array(
				'title'  => __( 'Adding to the cart', 'velox' ),
				'blocks' => array(
					array( 'path', __( 'Settings → Storefront', 'velox' ) ),
					array( 'list', array(
						__( '**After “Add to cart”:** open the mini cart, stay and show a short note, or go to the cart page.', 'velox' ),
						__( '**Buy bar on product pages:** price and button stay in reach once the visitor scrolls past them, at the bottom on phones and floating on larger screens.', 'velox' ),
					) ),
				),
			),
			array(
				'title'  => __( 'The shop page', 'velox' ),
				'blocks' => array(
					array( 'list', array(
						__( '**Products before “Show more”:** how many products load at first.', 'velox' ),
						__( '**“New” badge:** for products in their first 7, 14, 30 or 60 days. Sale, sold and few-left badges come first.', 'velox' ),
						__( '**Sort menu:** recommended, newest, price up or down, name.', 'velox' ),
						__( '**Search field:** worth it from about 30 products.', 'velox' ),
						__( '**Filters with counts:** price, your product options (colour, size …), available now and on sale. Each shows how many products it leaves.', 'velox' ),
						__( '**Colour dots on product cards:** for products with a colour option. Pointing at a dot shows that colour’s photo.', 'velox' ),
					) ),
					array( 'tip', __( 'Every choice a visitor makes stays in the address, so Back and shared links land in the same place.', 'velox' ) ),
				),
			),
			array(
				'title'  => __( 'Product pages', 'velox' ),
				'blocks' => array(
					array( 'list', array(
						__( '**Photos open full screen:** click or tap a photo; arrows, swipe and Esc work.', 'velox' ),
						__( '**More from the same category:** under products without hand-picked suggestions.', 'velox' ),
						__( '**Reasons to buy:** one per line, up to six, under the buy button. The icon follows the words: shipping, returns, payment, gift, handmade.', 'velox' ),
					) ),
				),
			),
			array(
				'title'  => __( 'Wishlist and back in stock', 'velox' ),
				'blocks' => array(
					array( 'p', __( '**Wishlist** puts a heart on every product. Guests keep their list in the browser. With an account it is saved there, on all devices, and a guest’s list moves into the account when they sign in.', 'velox' ) ),
					array( 'p', __( 'With **“Tell me when it is back”**, sold-out products and sold-out variants offer an email field. After a confirmation click, the customer gets exactly one email when it can be bought again; the address is then deleted.', 'velox' ) ),
				),
			),
			array(
				'title'  => __( 'Cart reminders', 'velox' ),
				'blocks' => array(
					array( 'p', __( '**Remind customers of a cart they left** sends one email, only to customers who tick “Remind me” at checkout. The button in the email restores the cart.', 'velox' ) ),
					array( 'list', array(
						__( '**Send after:** from 1 hour to 2 days.', 'velox' ),
						__( '**Coupon in the email:** optional. Create the codes under **Coupons**.', 'velox' ),
					) ),
					array( 'p', __( 'Below, Velox counts the reminders sent, the orders that followed with their revenue, and how many are waiting.', 'velox' ) ),
					array( 'note', __( 'An advertising email needs consent in Germany, so reminders are off from the start and only go to customers who ticked the box. Without the tick, nothing is stored.', 'velox' ) ),
				),
			),
			array(
				'title'  => __( 'Notice bar', 'velox' ),
				'blocks' => array(
					array( 'p', __( '**A slim bar across the top of every page** for free shipping, a sale or your holidays. Visitors can close it.', 'velox' ) ),
					array( 'p', __( 'Write the **Message**; several lines take turns every five seconds. With a **Link**, the whole message becomes a link.', 'velox' ) ),
				),
			),
		),
	),

	/* ------------------------------------------------------------ Pages, stock and texts */
	array(
		'id'       => 'pages',
		'title'    => __( 'Pages, stock and texts', 'velox' ),
		'intro'    => __( 'The pages the shop uses, your legal pages, the withdrawal button, stock rules, every text customers see, and this help.', 'velox' ),
		'sections' => array(
			array(
				'title'  => __( 'Shop pages', 'velox' ),
				'blocks' => array(
					array( 'path', __( 'Settings → Pages & legal', 'velox' ) ),
					array( 'p', __( 'Choose which page is the **Shop**, **Cart**, **Checkout**, **Customer account** and **Withdrawal form**. The checkout page also shows the order after buying. **Create the missing pages** makes any that are not there yet.', 'velox' ) ),
				),
			),
			array(
				'title'  => __( 'Legal pages', 'velox' ),
				'blocks' => array(
					array( 'p', __( 'Choose your **Terms and conditions**, **Withdrawal policy**, **Privacy policy** and **Shipping and payment info**. They are linked at checkout. Terms and withdrawal policy are also copied into the order confirmation, as the law requires.', 'velox' ) ),
					array( 'p', __( 'The shipping page is linked from “plus shipping” next to every price.', 'velox' ) ),
					array( 'note', __( 'Velox links these pages but does not write them. Their texts must come from you or a lawyer.', 'velox' ) ),
				),
			),
			array(
				'title'  => __( 'The withdrawal button', 'velox' ),
				'blocks' => array(
					array( 'list', array(
						__( '**Customers can withdraw online:** order pages and emails get a “Withdraw from the contract” link. You get an email and the order is flagged.', 'velox' ),
						__( '**Withdrawal period:** at least 14 days by law.', 'velox' ),
					) ),
					array( 'p', __( 'The withdrawal form works without an account: customers enter their order number and email, then confirm on the next step, and get a confirmation with date and time.', 'velox' ) ),
					array( 'note', __( 'The withdrawal button has been required since June 2026 (§ 356a BGB). Link the **Withdrawal form** page in your site footer.', 'velox' ) ),
				),
			),
			array(
				'title'  => __( 'Product pages', 'velox' ),
				'blocks' => array(
					array( 'list', array(
						__( '**Build product pages automatically:** photos, price, options and the cart button next to each other. Switch it off to design product pages yourself with the shop elements in Velox Builder.', 'velox' ),
						__( '**Product web address:** the part of the address in front of each product’s name.', 'velox' ),
					) ),
				),
			),
			array(
				'title'  => __( 'Stock', 'velox' ),
				'blocks' => array(
					array( 'path', __( 'Settings → Stock & products', 'velox' ) ),
					array( 'list', array(
						__( '**Warn me at:** at this stock level you get an email, and the overview lists the product.', 'velox' ),
						__( '**Show stock to customers:** only when it runs low, always or never.', 'velox' ),
						__( '**Sold items in the shop:** show them as sold, show them as sold at the end, or hide them.', 'velox' ),
					) ),
					array( 'p', __( 'A cancelled order puts its items back, and they show up as available again.', 'velox' ) ),
				),
			),
			array(
				'title'  => __( 'Texts', 'velox' ),
				'blocks' => array(
					array( 'path', __( 'Settings → Texts', 'velox' ) ),
					array( 'p', __( 'Every text your customers see can be changed here: buttons, notes, the account, the checkout and the emails.', 'velox' ) ),
					array( 'steps', array(
						__( 'Search for the text, or pick an area: shop and product pages, cart and checkout, customer account, wishlist, reviews and alerts, or emails and order states.', 'velox' ),
						__( 'Type your own wording into the field on the right. The grey text is the one in use now.', 'velox' ),
						__( 'Click **Save texts**. The change shows on the site right away.', 'velox' ),
					) ),
					array( 'tip', __( '**Changed** lists only your own texts, and × goes back to the original. Keep the placeholders that start with a % sign: they are filled in with a number or a name, and Velox checks them when you save.', 'velox' ) ),
				),
			),
			array(
				'title'  => __( 'Help & tour', 'velox' ),
				'blocks' => array(
					array( 'path', __( 'Settings → Help & tour', 'velox' ) ),
					array( 'p', __( '**Start the tour** walks you through the shop again. Below it are this guide and the common questions, all searchable.', 'velox' ) ),
				),
			),
		),
	),

	/* ------------------------------------------------------------ Customer account */
	array(
		'id'       => 'account',
		'title'    => __( 'The customer account', 'velox' ),
		'intro'    => __( 'What your customers see on the account page, and what they can do there by themselves.', 'velox' ),
		'sections' => array(
			array(
				'title'  => __( 'Sign in and create an account', 'velox' ),
				'blocks' => array(
					array( 'p', __( 'On the account page customers sign in with email and password, create an account, or ask for a link when they have forgotten their password. They never see WordPress’s login page or the dashboard.', 'velox' ) ),
					array( 'p', __( 'Creating an account asks for consent to the privacy policy, and new customers get a welcome email. Customers can also create an account while ordering, or afterwards from the page that shows their order.', 'velox' ) ),
					array( 'tip', __( 'Guests who ordered without an account always find their order through the link in their confirmation email.', 'velox' ) ),
				),
			),
			array(
				'title'  => __( 'The overview', 'velox' ),
				'blocks' => array(
					array( 'p', __( 'After signing in, customers see a greeting, how many orders they have, how many are in progress and what they have spent so far, the latest order with its progress from ordered to delivered, and tiles for address, security and profile.', 'velox' ) ),
					array( 'p', __( 'As long as two-factor sign-in is off, a note suggests setting it up.', 'velox' ) ),
				),
			),
			array(
				'title'  => __( 'Orders, downloads and wishlist', 'velox' ),
				'blocks' => array(
					array( 'list', array(
						__( '**Orders:** every order with its state. One click shows the details, the progress, the invoice and the tracking.', 'velox' ),
						__( '**Downloads:** the files they bought, as soon as the payment has arrived.', 'velox' ),
						__( '**Wishlist:** their saved products, when the wishlist is switched on.', 'velox' ),
					) ),
				),
			),
			array(
				'title'  => __( 'Address and profile', 'velox' ),
				'blocks' => array(
					array( 'list', array(
						__( '**Address:** used for invoices and delivery, and filled in at every checkout. Until one is saved, the address from the latest order is suggested.', 'velox' ),
						__( '**Profile:** the name, and the email address. A new email only counts once the customer confirms it from that inbox; the old address gets a note with a link to stop the change.', 'velox' ),
					) ),
				),
			),
			array(
				'title'  => __( 'Security and privacy', 'velox' ),
				'blocks' => array(
					array( 'list', array(
						__( '**Password:** changed through the inbox. The customer gets a link by email, and afterwards every device is signed out.', 'velox' ),
						__( '**Two-factor sign-in:** a 6-digit code from an authenticator app besides the password, with 10 one-time recovery codes.', 'velox' ),
						__( '**Signed-in devices:** where the account is signed in right now, with a button to sign out on all other devices.', 'velox' ),
						__( '**Download my data:** a file with account, address and orders.', 'velox' ),
						__( '**Delete my account:** with the password. Orders are kept as long as tax law requires (10 years), but no longer linked to the account.', 'velox' ),
					) ),
				),
			),
			array(
				'title'  => __( 'The account button in the header', 'velox' ),
				'blocks' => array(
					array( 'p', __( 'Velox Builder has an **Account button** element for your header. With a mouse, pointing at it opens a small menu: name and email, overview, orders, address, security and sign out, or sign in and “create an account” for visitors.', 'velox' ) ),
					array( 'p', __( 'Signed in, the button shows the customer’s initials. On touch screens it is a plain link to the account page.', 'velox' ) ),
				),
			),
		),
	),

	/* ------------------------------------------------------------ Velox Builder */
	array(
		'id'       => 'builder',
		'title'    => __( 'The shop in Velox Builder', 'velox' ),
		'intro'    => __( 'The parts of the shop are elements in Velox Builder: place them on any page and give each one its own settings.', 'velox' ),
		'sections' => array(
			array(
				'title'  => __( 'The shop elements', 'velox' ),
				'blocks' => array(
					array( 'path', __( 'Velox Builder → + Add → Shop', 'velox' ) ),
					array( 'p', __( 'While the shop is switched on, the **Shop** group in Velox Builder holds the **Product grid**, **Add to cart**, **Product (complete)**, **Cart button**, **Account button**, **Cart**, **Checkout**, **Customer account** and **Withdrawal form**. The editor shows each one with a live preview.', 'velox' ) ),
					array( 'tip', __( 'Every element starts with the shop design from **Settings → Design**. Its own settings only change that one element; empty fields keep the shop design.', 'velox' ) ),
				),
			),
			array(
				'title'  => __( 'Product grid', 'velox' ),
				'blocks' => array(
					array( 'list', array(
						__( '**How many**, **Columns**, **Category** and **Sort order:** which products, how many, and in which order.', 'velox' ),
						__( '**Category buttons above the grid:** lets visitors filter by category.', 'velox' ),
						__( '**Layout**, **Each product as**, **Buy button** and **On hover:** override the shop design for this grid only.', 'velox' ),
					) ),
				),
			),
			array(
				'title'  => __( 'Add to cart and complete product', 'velox' ),
				'blocks' => array(
					array( 'list', array(
						__( '**Add to cart:** the buy box of one product. Choose the **Product**, or keep “This product page” to use the product the page shows. **Quantity field** and **Button text** are optional.', 'velox' ),
						__( '**Product (complete):** photos, buy box and details of one product, as on its own page.', 'velox' ),
					) ),
				),
			),
			array(
				'title'  => __( 'Cart button and account button', 'velox' ),
				'blocks' => array(
					array( 'list', array(
						__( '**Cart button:** **Text next to the icon**, **Cart icon**, **Items in the cart** and **Cart total next to the icon**.', 'velox' ),
						__( '**Account button:** **Text next to the icon**, and **On hover** a menu or just a link.', 'velox' ),
					) ),
				),
			),
			array(
				'title'  => __( 'Cart, checkout, account and withdrawal form', 'velox' ),
				'blocks' => array(
					array( 'list', array(
						__( '**Cart** and **Withdrawal form:** a **Button colour** and **Corner radius** of their own.', 'velox' ),
						__( '**Checkout:** the **Layout** in one or two columns, plus **Button colour** and **Corner radius**.', 'velox' ),
						__( '**Customer account:** the **Menu** style, a maximum width (**Never wider than**), an **Accent colour** and **Corner radius**.', 'velox' ),
					) ),
				),
			),
			array(
				'title'  => __( 'Product data and product pages', 'velox' ),
				'blocks' => array(
					array( 'p', __( 'In a loop over products, or on a product page, **Insert Data** offers the product’s price, the add to cart button, the badge, the availability, the price per kg, l or m, the note “incl. VAT, plus shipping”, the delivery time, the item number and the was price.', 'velox' ) ),
					array( 'p', __( 'To design product pages yourself, switch off **Build product pages automatically** under **Settings → Pages & legal** and place shop elements such as **Product (complete)** or **Add to cart** where you want them.', 'velox' ) ),
				),
			),
		),
	),

	/* ------------------------------------------------------------ Law in Germany */
	array(
		'id'       => 'law',
		'title'    => __( 'Law in Germany', 'velox' ),
		'intro'    => __( 'What Velox Shop takes care of for German and EU law, and what you still have to provide yourself.', 'velox' ),
		'sections' => array(
			array(
				'title'  => __( 'Prices and product pages', 'velox' ),
				'blocks' => array(
					array( 'list', array(
						__( 'Prices show “incl. VAT” (or the § 19 UStG note) and “plus shipping”, linked to your shipping page.', 'velox' ),
						__( 'Goods sold by quantity show the price per kg, l or m (PAngV), worked out from **Contents** and **Unit**.', 'velox' ),
						__( 'The delivery time is shown before ordering.', 'velox' ),
						__( 'Manufacturer and safety information (GPSR) appears on the product page once you fill it in.', 'velox' ),
					) ),
				),
			),
			array(
				'title'  => __( 'The checkout', 'velox' ),
				'blocks' => array(
					array( 'list', array(
						__( 'The order button says that ordering costs money (“zahlungspflichtig bestellen”), with the order overview right above it.', 'velox' ),
						__( 'Links to terms, withdrawal policy and privacy policy, and an optional terms checkbox.', 'velox' ),
						__( 'For downloads, the customer agrees that the download starts before the withdrawal period ends.', 'velox' ),
						__( 'Card and PayPal payments happen on the provider’s page.', 'velox' ),
					) ),
				),
			),
			array(
				'title'  => __( 'After the order', 'velox' ),
				'blocks' => array(
					array( 'list', array(
						__( 'The order confirmation contains your terms and withdrawal policy.', 'velox' ),
						__( 'The withdrawal button (§ 356a BGB): an online form that works without an account and sends a confirmation with date and time.', 'velox' ),
						__( 'Invoices with gap-free numbers and the details § 14 UStG asks for.', 'velox' ),
						__( 'Paid orders cannot be deleted, so your books stay complete.', 'velox' ),
					) ),
				),
			),
			array(
				'title'  => __( 'Emails, reviews and privacy', 'velox' ),
				'blocks' => array(
					array( 'list', array(
						__( 'Cart reminders only go out with the customer’s tick at checkout.', 'velox' ),
						__( 'Back-in-stock emails only after a confirmation click (double opt-in); the address is deleted afterwards.', 'velox' ),
						__( 'Product pages say how reviews are checked (§ 5b UWG).', 'velox' ),
						__( 'Shop fonts are served from your own site, so no visitor data goes to Google.', 'velox' ),
						__( 'Customers download their data and delete their account themselves.', 'velox' ),
					) ),
				),
			),
			array(
				'title'  => __( 'What you must provide', 'velox' ),
				'blocks' => array(
					array( 'list', array(
						__( '**Legal texts:** terms and conditions, withdrawal policy, privacy policy, and shipping and payment information, written by you or a lawyer and chosen under **Settings → Pages & legal**.', 'velox' ),
						__( '**Imprint:** not part of the shop settings. Link it in your site footer, next to the withdrawal form.', 'velox' ),
						__( '**Your details:** business address, VAT ID or tax number under **Settings → Store**.', 'velox' ),
						__( '**Product facts:** delivery times, contents for unit prices, and manufacturer and safety information for every product.', 'velox' ),
						__( '**Small business limit:** keep an eye on the turnover limits of § 19 UStG and switch VAT on in time.', 'velox' ),
					) ),
					array( 'note', __( 'Velox gives you the tools, not legal advice. Have your shop checked by a lawyer or a trusted service before you open it.', 'velox' ) ),
				),
			),
		),
	),

	/* ------------------------------------------------------------ Troubleshooting */
	array(
		'id'       => 'trouble',
		'title'    => __( 'Troubleshooting', 'velox' ),
		'intro'    => __( 'Common problems and how to solve them.', 'velox' ),
		'sections' => array(
			array(
				'title'  => __( 'Visitors cannot order', 'velox' ),
				'blocks' => array(
					array( 'p', __( 'The checkout says “Ordering is not possible right now” when no payment method is ready. Switch one on under **Settings → Payments** and fill in its details. The overview lists what is still missing.', 'velox' ) ),
					array( 'p', __( 'Also check the **Minimum order value** under **Settings → Checkout**, and that the cart and checkout pages exist and are published.', 'velox' ) ),
				),
			),
			array(
				'title'  => __( 'Customers do not get emails', 'velox' ),
				'blocks' => array(
					array( 'steps', array(
						__( 'Set up Velox Mail (SMTP). Without it, emails often land in spam or never arrive.', 'velox' ),
						__( 'Under **Settings → Emails**, check that the email is switched on and the sender address is right.', 'velox' ),
						__( 'Click **Test** next to the email and look in your own inbox, and in the spam folder.', 'velox' ),
					) ),
				),
			),
			array(
				'title'  => __( 'Card payments stay unpaid', 'velox' ),
				'blocks' => array(
					array( 'p', __( 'Stripe tells the shop about payments through the webhook. Check that the webhook has the address Velox shows and both events, and that the **Webhook signing secret** belongs to this webhook.', 'velox' ) ),
					array( 'p', __( 'Test and live mode are separate in Stripe: a test key needs a webhook made in test mode, a live key one made in live mode. **Test the connection** shows whether the key works.', 'velox' ) ),
				),
			),
			array(
				'title'  => __( 'An order cannot be changed or reopened', 'velox' ),
				'blocks' => array(
					array( 'list', array(
						__( '**Locked:** paid, shipped or invoiced orders cannot change, and neither can card or PayPal orders the customer was sent to pay. The order says why.', 'velox' ),
						__( '**Cancelled:** reopen the order first, then change it.', 'velox' ),
						__( '**Cannot be reopened:** an item has been sold to someone else in the meantime. Take a new order instead.', 'velox' ),
					) ),
				),
			),
			array(
				'title'  => __( 'A product does not show in the shop', 'velox' ),
				'blocks' => array(
					array( 'list', array(
						__( 'Its visibility is **Draft**: set it to **Live** and save.', 'velox' ),
						__( 'It is sold out, and **Sold items in the shop** is set to **Hide them**.', 'velox' ),
						__( 'A product grid in Velox Builder shows only one category or fewer products: check its **Category** and **How many**.', 'velox' ),
					) ),
				),
			),
			array(
				'title'  => __( 'The design looks different than expected', 'velox' ),
				'blocks' => array(
					array( 'list', array(
						__( 'Design changes only go live after **Save**. The test pages from **Try it on the real shop** are seen by you alone.', 'velox' ),
						__( 'A dark template that looks light has your own colours on top: click **Use … as it comes**.', 'velox' ),
						__( 'A shop element in Velox Builder can have its own settings that win over the design.', 'velox' ),
					) ),
				),
			),
			array(
				'title'  => __( 'Other questions', 'velox' ),
				'blocks' => array(
					array( 'list', array(
						__( '**PayPal balance missing on Payouts:** switch on “Balances” for your app in the PayPal developer dashboard.', 'velox' ),
						__( '**A “… in Velox Builder” button is greyed out:** the page does not exist yet. Create it under **Settings → Pages & legal**.', 'velox' ),
						__( '**Unsaved changes:** a bar at the bottom of the screen shows them, and Velox asks before you leave. In the settings, **Discard** throws them away and **Save** keeps them.', 'velox' ),
					) ),
					array( 'tip', __( 'Still stuck? Take the tour again under **Settings → Help & tour**, or search this guide and the questions.', 'velox' ) ),
				),
			),
		),
	),
);
