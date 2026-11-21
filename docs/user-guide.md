# User guide

This guide explains how people use Village City day to day. It is also available inside the app under **Help & Documentation** (sidebar, or the `?` button in the top bar), which opens on a quick start for your own role. For installation see [setup.md](setup.md).

## Signing in and getting around

- **Log in** at `/login`. **Sign up** at `/register` creates a *buyer* account (a customer record is created automatically). Staff accounts are created by an administrator under Administration → Users.
- The **left sidebar** lists only the modules your role can open. The **top bar** has global search (farmers, suppliers, products, customers, orders, procurement), the cart (for roles that can shop), notifications and your profile menu.
- **My account:** profile and password under the profile menu. Deactivated users are signed out immediately.
- Tables support search, filters and pagination. Most master-data pages have a **New** button, and an actions menu (⋯) per row for edit, activate/deactivate and delete. Records with history can't be deleted — deactivate them instead.

## Roles at a glance

| Role | Typical work |
|---|---|
| Super Administrator | Everything, including roles and settings |
| Administrator | Everything granted to the role (all permissions by default) |
| CEO | Read-only view of every module |
| Operations Manager | Day-to-day management across supply chain, orders and logistics |
| Procurement Officer | Farmers, suppliers, procurement, forecasts |
| County Coordinator | Farmers and suppliers in their area |
| Aggregation Centre Officer | Receiving produce at a centre |
| Quality Control Officer | Inspecting and grading produce |
| Warehouse Manager | Inventory and centres |
| Sales Manager | Customers, orders, invoices, website inquiries |
| Logistics Manager | Vehicles, dispatch, deliveries |
| Finance Manager | Payments, settlements, expenses, reports |
| Customer | Marketplace and their own orders |
| Farmer | Reserved role (no portal pages yet) |

Administrators can change what each role may do in **Administration → Roles & Permissions**.

---

## Workflow 1 — Registering and approving farmers

1. **Farmers register themselves** at `/sell-with-us` (name, phone, county, what they grow). They appear in **Supply Chain → Farmers** as **Pending approval**, and users who can manage farmers get a notification.
2. Staff can also register a farmer directly (**New farmer**): number is generated (`FRM-00001`…), with county/sub-county/ward/village, GPS, farm size, commodities, cooperative and assigned aggregation centre.
3. Open the farmer's actions menu → **Approve registration** to activate them. Only **active** farmers can be selected in procurement.
4. On a farmer's profile you can see supply history, procurement history, payments received, acceptance rate and unpaid balance, and record **seasonal harvest schedules** (expected date and quantity) that feed supplier matching.

Suppliers (individuals, farmer groups, cooperatives, commercial suppliers) work the same way under **Suppliers** and **Cooperatives**. Link farmers to a cooperative from the farmer record.

## Workflow 2 — Buying produce (procurement → quality → stock)

1. **Procurement → New procurement.** Choose farmer or supplier (you can assign later), the collection centre, date, and one or more commodities with quantity and agreed unit price (pre-filled from the catalogue).
2. Open the procurement and **Confirm purchase** (a source must be assigned).
3. When the produce arrives, **Record produce received** and enter the quantity actually delivered per commodity.
4. **Quality Control → Inspect.** For each commodity choose a grade (A, B, C or Rejected), the accepted quantity, comments and an optional photo.
   - *Only accepted quantity* becomes saleable stock (a new batch, with an expiry date for perishables) and **becomes payable** to the supplier.
   - A *Rejected* grade accepts nothing.
5. Payment is made under **Finance → Supplier Settlements** (see Workflow 5).

Statuses: Requested → Confirmed → Received → Inspected (or Cancelled while Requested/Confirmed). Statuses can't skip steps or go backwards.

## Workflow 3 — Taking and fulfilling orders

**Placing an order**

- *Customers* browse the **Marketplace**, add items to the cart, and check out with a delivery address and preferred date. They always pay listed prices.
- *Staff* create an order under **Commerce → Orders → New order** (custom prices and discounts allowed), or place a marketplace order on a customer's behalf.
- Customers on credit terms can't exceed their **credit limit**.

**Handling an order** (order page → action buttons)

1. **Confirm order.** An invoice is issued and stock is allocated automatically, oldest batch first.
   - Enough stock → the order goes to **Processing**.
   - Not enough → **Awaiting procurement**. A procurement request is raised automatically for the shortfall. When new stock is inspected, waiting orders are completed automatically, oldest first.
   - Alternatively choose **Proceed with partial** to reduce the order (and invoice) to what is already allocated.
2. **Mark ready for dispatch** when packed.
3. Dispatch and delivery (Workflow 4) move it through **Dispatched → In transit → Delivered**.
4. **Cancel** is available until dispatch; allocated stock is returned to its original batches and the invoice is voided. An order with payments must have those payments reversed first.

Order statuses: Draft · Pending confirmation · Confirmed · Awaiting procurement · Processing · Ready for dispatch · Dispatched · In transit · Delivered · Cancelled.

Customers see a progress tracker and delivery timeline on their own orders, and a **Reorder** button that refills the cart.

## Workflow 4 — Dispatch and delivery

1. **Logistics → Dispatch → New dispatch.** Pick orders that are *Ready for dispatch*, the dispatch location, route, vehicle (available vehicles only), driver, departure and expected arrival, and transport cost.
2. Click **Depart** when the vehicle leaves. This marks the orders Dispatched, puts the vehicle on trip, and records the transport cost as a **transport expense**.
3. **Mark in transit** if you wish.
4. Open each delivery (**Deliveries**) to record the outcome:
   - **Delivered** — recipient name, optional phone and proof of delivery (photo or PDF). The order becomes Delivered.
   - **Failed / Returned** — a reason is required; the order goes back to *Ready for dispatch* so it can be rescheduled.
5. When every delivery on a dispatch is finished, the dispatch completes and the vehicle becomes available again.

Delivery statuses: Scheduled · Assigned · Dispatched · In transit · Delivered · Failed · Returned. Deliveries past their expected time are flagged **Late**.

## Workflow 5 — Money in, money out

**Customer payments** — *Commerce → Payments → Record payment* (or from an invoice): choose the open invoice, amount (cannot exceed the balance), method (cash, bank transfer, M-Pesa, cheque, other), reference such as the M-Pesa code, and date. Invoice and order payment status update to Partial or Paid. Mistakes are fixed with **Reverse** (with a reason), which restores the balance and adds a reversing ledger entry.

**Supplier settlements** — *Finance → Supplier Settlements*: the **Amounts owed** tab lists inspected procurements with a balance. Click **Pay**, enter the amount, method and reference. Partial payments are supported and reversible.

**Expenses** — *Finance → Expenses*: record transport, packaging, labour, storage, fuel, utilities, administration, marketing and other costs. Recorded expenses can't be edited or deleted; **Void** adds a reversing entry.

**Financial Transactions** is the read-only ledger of every payment, settlement, expense and reversal, filterable by date, direction and category.

## Workflow 6 — Inventory

- **Inventory → Stock by product** shows saleable and expired quantity per commodity, value at cost and a Low / Out of stock indicator; filter by location.
- **Batches** lists every batch with received date, expiry, grade and remaining quantity. From the ⋯ menu you can:
  - **Transfer** part of a batch to another centre or warehouse.
  - **Adjust count** up or down after a stock count (a reason is required).
  - **Write off** damaged, expired or wasted produce.
- **Movement history** is a permanent log of every change. Nothing can be silently edited — corrections are new movements.
- Expired batches are excluded from sales automatically. Low-stock alerts notify users who can view inventory.

## Workflow 7 — Reports and intelligence

- **Reports** (*Finance → Reports*): pick a report, set filters (date range, county, centre, product, supplier/farmer, customer, status — each report uses the relevant ones), review on screen and **Export CSV**. See [modules.md](modules.md#reports) for the list.
- **Analytics:** monthly sales vs procurement, margins, top and most profitable commodities, wastage.
- **Demand Forecasting:** weekly demand estimates per commodity with trend, stock cover and confidence, plus **procurement recommendations** (what to buy, how much, estimated cost, and why).
- **Market Intelligence:** choose a commodity to rank farmers and suppliers (quality, reliability, price, availability), and see slow movers, wastage, best sourcing counties, delivery performance and overdue-payment risks.

All intelligence output is labelled as **system-generated estimates**, not verified business data.

---

## For customers

1. Browse `/produce` (public) or the **Marketplace** (logged in). Add items — the product flies into your cart. Guests can fill a cart first; it is kept when you log in or sign up.
2. Open the cart, adjust quantities with − / +, choose a delivery address and date, add instructions, **Place order**.
3. Track the order under **Orders**. When the order is confirmed you receive a notification, and an invoice appears once it is confirmed.
4. Use **Reorder** on any past order to refill the cart.

## For administrators

| Task | Where |
|---|---|
| Create staff users, assign roles, activate/deactivate | Administration → Users |
| Create roles, edit permissions | Administration → Roles & Permissions |
| Add counties and sub-counties (the platform is not limited to 11) | Administration → Counties |
| Company details and defaults | Administration → Settings |
| Manage SEO, website copy, FAQs and product page metadata | Administration → SEO & Site Content (see the [SEO guide](seo-guide.md)) |
| Read website enquiries | Administration → Website Inquiries |
| Review who did what | Administration → Audit Logs |

Rules built into user management: you can't deactivate or delete your own account, and only a Super Administrator can create, edit or assign the Super Administrator role.
