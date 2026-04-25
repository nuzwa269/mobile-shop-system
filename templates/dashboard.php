<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex,nofollow">
<title><?php echo esc_html( get_bloginfo( 'name' ) ); ?> – Mobile Shop Dashboard</title>
<!-- Favicons & web fonts omitted intentionally for full offline compatibility -->
<link rel="stylesheet" href="<?php echo esc_url( MSP_PLUGIN_URL . 'assets/app.css' ); ?>?v=<?php echo esc_attr( MSP_VERSION ); ?>">
</head>
<body>

<!-- Animated Background -->
<div id="msp-bg"></div>

<!-- Toast Container -->
<div id="msp-toasts"></div>

<!-- App Shell -->
<div id="msp-app">

  <!-- ── Sidebar ─────────────────────────────────────────────────────────── -->
  <aside id="msp-sidebar">
    <div class="msp-logo">
      <div class="logo-icon">📱</div>
      <div>
        <h1><?php echo esc_html( get_bloginfo( 'name' ) ); ?></h1>
        <span>Smart Management</span>
      </div>
    </div>

    <ul id="msp-nav">
      <li id="nav-dashboard" class="active" data-section="dashboard">
        <a href="#"><span class="nav-icon">🏠</span><span class="nav-label">Dashboard</span></a>
      </li>
      <li id="nav-pos" data-section="pos">
        <a href="#"><span class="nav-icon">🛒</span><span class="nav-label">Point of Sale</span></a>
      </li>
      <li id="nav-inventory" data-section="inventory">
        <a href="#"><span class="nav-icon">📦</span><span class="nav-label">Inventory</span></a>
      </li>
      <li id="nav-customers" data-section="customers">
        <a href="#"><span class="nav-icon">👥</span><span class="nav-label">Customers</span></a>
      </li>
      <li id="nav-repair" data-section="repair">
        <a href="#"><span class="nav-icon">🔧</span><span class="nav-label">Repair Lab</span></a>
      </li>
      <li id="nav-crm" data-section="crm">
        <a href="#"><span class="nav-icon">📒</span><span class="nav-label">CRM / Ledger</span></a>
      </li>
      <li id="nav-expenses" data-section="expenses">
        <a href="#"><span class="nav-icon">💸</span><span class="nav-label">Expenses</span></a>
      </li>
      <li id="nav-reports" data-section="reports">
        <a href="#"><span class="nav-icon">📊</span><span class="nav-label">Reports</span></a>
      </li>
    </ul>

    <div class="msp-sidebar-footer">
      Mobile Shop Pro v<?php echo esc_html( MSP_VERSION ); ?><br>
      <?php echo esc_html( MSP_DEVELOPER ); ?>
    </div>
  </aside>

  <!-- ── Main Panel ──────────────────────────────────────────────────────── -->
  <div id="msp-main">

    <!-- Header -->
    <header id="msp-header">
      <h2 id="msp-header-title">Dashboard</h2>
      <div id="msp-header-meta">
        <span class="msp-badge accent">👤 <?php echo esc_html( wp_get_current_user()->display_name ); ?></span>
        <span class="msp-badge info" id="live-clock"></span>
        <a href="<?php echo esc_url( home_url() ); ?>" class="msp-btn msp-btn-ghost msp-btn-sm" style="text-decoration:none">🏠 Site</a>
      </div>
    </header>

    <!-- Content Area -->
    <main id="msp-content">

      <!-- ════════════════════════════════════════════════════════════════════ -->
      <!-- DASHBOARD SECTION                                                    -->
      <!-- ════════════════════════════════════════════════════════════════════ -->
      <div id="section-dashboard" class="msp-section active">

        <div class="msp-metrics">
          <div class="msp-metric-card purple">
            <div class="metric-icon">💰</div>
            <div class="metric-label">Today's Sales</div>
            <div class="metric-value" id="metric-daily-sales">–</div>
          </div>
          <div class="msp-metric-card green">
            <div class="metric-icon">📈</div>
            <div class="metric-label">Today's Profit</div>
            <div class="metric-value" id="metric-daily-profit">–</div>
          </div>
          <div class="msp-metric-card blue">
            <div class="metric-icon">📅</div>
            <div class="metric-label">Monthly Revenue</div>
            <div class="metric-value" id="metric-monthly-revenue">–</div>
          </div>
          <div class="msp-metric-card amber">
            <div class="metric-icon">⏳</div>
            <div class="metric-label">Pending Payments</div>
            <div class="metric-value" id="metric-pending">–</div>
          </div>
          <div class="msp-metric-card red">
            <div class="metric-icon">⚠️</div>
            <div class="metric-label">Low Stock Items</div>
            <div class="metric-value" id="metric-low-stock">–</div>
          </div>
          <div class="msp-metric-card purple">
            <div class="metric-icon">📦</div>
            <div class="metric-label">Total Products</div>
            <div class="metric-value" id="metric-total-products">–</div>
          </div>
          <div class="msp-metric-card amber">
            <div class="metric-icon">🔧</div>
            <div class="metric-label">Open Repairs</div>
            <div class="metric-value" id="metric-open-repairs">–</div>
          </div>
        </div>

        <div class="msp-card">
          <div class="msp-card-title">Recent Sales</div>
          <div class="msp-table-wrap">
            <table class="msp-table">
              <thead>
                <tr><th>#</th><th>Customer</th><th>Net Total</th><th>Status</th><th>Date</th></tr>
              </thead>
              <tbody id="recent-sales-tbody">
                <tr><td colspan="5" style="text-align:center;padding:20px"><span class="msp-spinner"></span></td></tr>
              </tbody>
            </table>
          </div>
        </div>

      </div><!-- /dashboard -->

      <!-- ════════════════════════════════════════════════════════════════════ -->
      <!-- POS SECTION                                                           -->
      <!-- ════════════════════════════════════════════════════════════════════ -->
      <div id="section-pos" class="msp-section">
        <div id="msp-pos-layout">

          <!-- Left: product search + results -->
          <div id="msp-pos-left">

            <div class="msp-card">
              <div class="msp-card-title">🔍 Scan / Search Product</div>
              <div class="msp-form-group">
                <label for="msp-barcode-input">Barcode / IMEI / Product Name</label>
                <input type="text" id="msp-barcode-input" class="msp-input" placeholder="Scan barcode or type product name, then press Enter…" autocomplete="off" autofocus>
              </div>
              <div id="msp-pos-results"></div>
            </div>

            <div class="msp-card">
              <div class="msp-card-title">⚙️ Sale Options</div>
              <div class="msp-form-row">
                <div class="msp-form-group">
                  <label>Customer</label>
                  <div class="msp-customer-field">
                    <div class="msp-customer-search-wrap">
                      <input type="text" id="pos-customer-search" class="msp-input" placeholder="🔍 Search by name or phone…" autocomplete="off">
                      <div id="pos-customer-dropdown" class="msp-customer-dropdown" style="display:none"></div>
                    </div>
                    <input type="hidden" id="pos-customer-id" value="0">
                    <button type="button" id="btn-pos-quick-add-customer" class="msp-btn msp-btn-sm msp-btn-green">＋ New</button>
                  </div>
                </div>
                <div class="msp-form-group">
                  <label for="pos-payment-status">Payment Status</label>
                  <select id="pos-payment-status" class="msp-select">
                    <option value="paid">Paid</option>
                    <option value="credit">Credit (Deferred Payment)</option>
                    <option value="partial">Partial</option>
                  </select>
                </div>
              </div>
            </div>

          </div><!-- /pos-left -->

          <!-- Right: cart -->
          <div id="msp-pos-cart">
            <div class="msp-card">
              <div class="msp-card-title">🛒 Cart</div>
              <div id="msp-cart-items">
                <div class="msp-empty"><div class="empty-icon">🛒</div><p>Cart is empty</p></div>
              </div>
              <div id="msp-cart-totals">
                <div class="total-row"><span>Subtotal</span><span id="cart-subtotal">PKR 0.00</span></div>
                <div class="total-row">
                  <span>Discount</span>
                  <span><input type="number" id="pos-discount" class="msp-input" placeholder="0" min="0" step="any" style="width:90px;display:inline-block;padding:4px 8px;text-align:right"></span>
                </div>
                <div class="total-row net"><span>Net Total</span><span id="cart-net">PKR 0.00</span></div>
              </div>
              <div style="margin-top:14px">
                <button id="btn-pos-checkout" class="msp-btn msp-btn-success" style="width:100%;justify-content:center;font-size:15px;padding:12px">
                  💳 Checkout
                </button>
              </div>
            </div>
          </div>

        </div><!-- /pos-layout -->
      </div><!-- /pos -->

      <!-- ════════════════════════════════════════════════════════════════════ -->
      <!-- INVENTORY SECTION                                                     -->
      <!-- ════════════════════════════════════════════════════════════════════ -->
      <div id="section-inventory" class="msp-section">
        <div class="msp-toolbar">
          <button id="btn-add-product" class="msp-btn msp-btn-primary">＋ Add Product</button>
          <input type="text" id="inv-search" class="msp-input" placeholder="Search products…" style="max-width:240px">
          <select id="inv-category-filter" class="msp-select" style="max-width:160px">
            <option value="">All Categories</option>
            <option value="mobile">Mobile</option>
            <option value="accessory">Accessory</option>
            <option value="part">Part</option>
          </select>
        </div>
        <div class="msp-card">
          <div class="msp-table-wrap">
            <table class="msp-table">
              <thead>
                <tr><th>#</th><th>Product</th><th>Category</th><th>Variant</th><th>Cost</th><th>Price</th><th>Stock</th><th>Actions</th></tr>
              </thead>
              <tbody id="inventory-tbody">
                <tr><td colspan="8" style="text-align:center;padding:20px"><span class="msp-spinner"></span></td></tr>
              </tbody>
            </table>
          </div>
        </div>
      </div><!-- /inventory -->

      <!-- ════════════════════════════════════════════════════════════════════ -->
      <!-- CUSTOMERS SECTION                                                     -->
      <!-- ════════════════════════════════════════════════════════════════════ -->
      <div id="section-customers" class="msp-section">
        <div class="msp-toolbar">
          <button id="btn-add-customer" class="msp-btn msp-btn-green">＋ Add Customer</button>
          <input type="text" id="customer-search" class="msp-input msp-search-blue" placeholder="🔍 Search customers…" style="max-width:280px">
        </div>
        <div class="msp-card">
          <div class="msp-table-wrap">
            <table class="msp-table">
              <thead>
                <tr><th>#</th><th>Name</th><th>Phone</th><th>Email</th><th>Address</th><th>Balance</th><th>Joined</th><th>Actions</th></tr>
              </thead>
              <tbody id="customers-tbody">
                <tr><td colspan="8" style="text-align:center;padding:20px"><span class="msp-spinner"></span></td></tr>
              </tbody>
            </table>
          </div>
        </div>
      </div><!-- /customers -->

      <!-- ════════════════════════════════════════════════════════════════════ -->
      <!-- REPAIR LAB SECTION                                                    -->
      <!-- ════════════════════════════════════════════════════════════════════ -->
      <div id="section-repair" class="msp-section">
        <div class="msp-toolbar">
          <button id="btn-add-repair" class="msp-btn msp-btn-primary">＋ New Job Card</button>
          <select id="repair-status-filter" class="msp-select" style="max-width:180px">
            <option value="">All Statuses</option>
            <option value="pending">Pending</option>
            <option value="repairing">Repairing</option>
            <option value="fixed">Fixed</option>
            <option value="unrepairable">Unrepairable</option>
          </select>
        </div>
        <div class="msp-card">
          <div class="msp-table-wrap">
            <table class="msp-table">
              <thead>
                <tr><th>Job Card</th><th>Customer</th><th>Device</th><th>Issue</th><th>Est. Cost</th><th>Status</th><th>Received</th><th>Actions</th></tr>
              </thead>
              <tbody id="repair-tbody">
                <tr><td colspan="8" style="text-align:center;padding:20px"><span class="msp-spinner"></span></td></tr>
              </tbody>
            </table>
          </div>
        </div>
      </div><!-- /repair -->

      <!-- ════════════════════════════════════════════════════════════════════ -->
      <!-- CRM / LEDGER SECTION                                                  -->
      <!-- ════════════════════════════════════════════════════════════════════ -->
      <div id="section-crm" class="msp-section">
        <div class="msp-toolbar">
          <button id="btn-add-ledger" class="msp-btn msp-btn-primary">＋ New Entry</button>
          <select id="ledger-user-filter" class="msp-select" style="max-width:260px">
            <option value="">All Customers/Suppliers</option>
          </select>
          <span id="ledger-balance" style="font-weight:700;margin-left:auto"></span>
        </div>
        <div class="msp-card">
          <div class="msp-table-wrap">
            <table class="msp-table">
              <thead>
                <tr><th>#</th><th>Name</th><th>Type</th><th>Amount</th><th>Description</th><th>Date</th></tr>
              </thead>
              <tbody id="ledger-tbody">
                <tr><td colspan="6" style="text-align:center;padding:20px"><span class="msp-spinner"></span></td></tr>
              </tbody>
            </table>
          </div>
        </div>
      </div><!-- /crm -->

      <!-- ════════════════════════════════════════════════════════════════════ -->
      <!-- EXPENSES SECTION                                                       -->
      <!-- ════════════════════════════════════════════════════════════════════ -->
      <div id="section-expenses" class="msp-section">
        <div class="msp-toolbar">
          <button id="btn-add-expense" class="msp-btn msp-btn-primary">＋ Log Expense</button>
          <span id="expense-total" style="font-weight:700;margin-left:auto;color:var(--danger)"></span>
        </div>
        <div class="msp-card">
          <div class="msp-table-wrap">
            <table class="msp-table">
              <thead>
                <tr><th>#</th><th>Type</th><th>Amount</th><th>Description</th><th>Date</th><th>Delete</th></tr>
              </thead>
              <tbody id="expenses-tbody">
                <tr><td colspan="6" style="text-align:center;padding:20px"><span class="msp-spinner"></span></td></tr>
              </tbody>
            </table>
          </div>
        </div>
      </div><!-- /expenses -->

      <!-- ════════════════════════════════════════════════════════════════════ -->
      <!-- REPORTS SECTION                                                        -->
      <!-- ════════════════════════════════════════════════════════════════════ -->
      <div id="section-reports" class="msp-section">
        <div class="msp-toolbar">
          <input type="date" id="report-date-from" class="msp-input" style="max-width:160px">
          <span style="color:var(--text-muted)">to</span>
          <input type="date" id="report-date-to" class="msp-input" style="max-width:160px">
          <button id="btn-run-report" class="msp-btn msp-btn-primary">📊 Run Report</button>
        </div>

        <!-- Summary cards -->
        <div class="msp-metrics" style="margin-bottom:20px">
          <div class="msp-metric-card purple">
            <div class="metric-label">Total Revenue</div>
            <div class="metric-value" id="report-revenue">–</div>
          </div>
          <div class="msp-metric-card blue">
            <div class="metric-label">Total Sales</div>
            <div class="metric-value" id="report-sales-count">–</div>
          </div>
          <div class="msp-metric-card green">
            <div class="metric-label">Gross Profit</div>
            <div class="metric-value" id="report-gross-profit">–</div>
          </div>
          <div class="msp-metric-card red">
            <div class="metric-label">Total Expenses</div>
            <div class="metric-value" id="report-expenses">–</div>
          </div>
          <div class="msp-metric-card green">
            <div class="metric-label">Net Profit</div>
            <div class="metric-value" id="report-net-profit">–</div>
          </div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">
          <div class="msp-card">
            <div class="msp-card-title">📅 Daily Sales Chart</div>
            <div id="report-daily-chart"></div>
          </div>
          <div class="msp-card">
            <div class="msp-card-title">📦 Sales by Category</div>
            <div class="msp-table-wrap">
              <table class="msp-table">
                <thead><tr><th>Category</th><th>Items Sold</th><th>Revenue</th></tr></thead>
                <tbody id="report-category-tbody"></tbody>
              </table>
            </div>
          </div>
          <div class="msp-card">
            <div class="msp-card-title">💸 Expenses by Type</div>
            <div class="msp-table-wrap">
              <table class="msp-table">
                <thead><tr><th>Type</th><th>Total</th></tr></thead>
                <tbody id="report-expense-tbody"></tbody>
              </table>
            </div>
          </div>
        </div>

      </div><!-- /reports -->

    </main><!-- /msp-content -->

    <!-- Footer branding -->
    <footer style="text-align:center;padding:8px 20px;font-size:11px;color:var(--text-muted);border-top:1px solid var(--border);flex-shrink:0">
      <?php echo esc_html( MSP_DEVELOPER ); ?> &bull; Mobile Shop Pro v<?php echo esc_html( MSP_VERSION ); ?>
    </footer>

  </div><!-- /msp-main -->
</div><!-- /msp-app -->

<!-- ══════════════════════════════════════════════════════════════════════════ -->
<!-- MODALS                                                                      -->
<!-- ══════════════════════════════════════════════════════════════════════════ -->

<!-- Add / Edit Product Modal -->
<div id="modal-product" class="msp-modal-overlay" style="display:none">
  <div class="msp-modal">
    <div class="msp-modal-header">
      <h3 id="modal-product-title">Add New Product</h3>
      <button class="msp-modal-close" type="button">✕</button>
    </div>
    <div class="msp-modal-body">
      <form id="form-product">
        <input type="hidden" id="product-form-id">
        <div class="msp-form-row">
          <div class="msp-form-group">
            <label for="product-name">Product Name *</label>
            <input type="text" id="product-name" class="msp-input" required placeholder="e.g. Samsung Galaxy A55">
          </div>
          <div class="msp-form-group">
            <label for="product-category">Category *</label>
            <select id="product-category" class="msp-select" required>
              <option value="mobile">Mobile</option>
              <option value="accessory">Accessory</option>
              <option value="part">Part</option>
            </select>
          </div>
        </div>
        <div class="msp-form-group">
          <label for="product-variant">Variant (Storage / Color)</label>
          <input type="text" id="product-variant" class="msp-input" placeholder="e.g. 128GB / Midnight Black">
        </div>
        <div class="msp-form-row">
          <div class="msp-form-group">
            <label for="product-cost">Cost Price *</label>
            <input type="number" id="product-cost" class="msp-input" min="0" step="any" required placeholder="0.00">
          </div>
          <div class="msp-form-group">
            <label for="product-sell">Selling Price *</label>
            <input type="number" id="product-sell" class="msp-input" min="0" step="any" required placeholder="0.00">
          </div>
        </div>
        <div class="msp-form-group">
          <label for="product-stock">Stock Quantity</label>
          <input type="number" id="product-stock" class="msp-input" min="0" value="0">
        </div>
        <div class="msp-modal-footer" style="padding:0;border:0;margin-top:6px">
          <button type="button" class="msp-btn msp-btn-ghost msp-modal-close">Cancel</button>
          <button type="submit" class="msp-btn msp-btn-primary">💾 Save Product</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- IMEI / Serial Management Modal -->
<div id="modal-imei" class="msp-modal-overlay" style="display:none">
  <div class="msp-modal">
    <div class="msp-modal-header">
      <h3 id="modal-imei-title">IMEI / Serials</h3>
      <button class="msp-modal-close" type="button">✕</button>
    </div>
    <div class="msp-modal-body">
      <input type="hidden" id="imei-product-id">
      <form id="form-add-imei" style="display:flex;gap:10px;margin-bottom:16px">
        <input type="text" id="new-imei-serial" class="msp-input" placeholder="Enter IMEI or Serial number…" required autocomplete="off">
        <button type="submit" class="msp-btn msp-btn-primary" style="white-space:nowrap">＋ Add</button>
      </form>
      <div class="msp-table-wrap">
        <table class="msp-table">
          <thead><tr><th>#</th><th>IMEI / Serial</th><th>Status</th></tr></thead>
          <tbody id="imei-list-tbody"></tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- Add / Edit Repair Modal -->
<div id="modal-repair" class="msp-modal-overlay" style="display:none">
  <div class="msp-modal">
    <div class="msp-modal-header">
      <h3 id="modal-repair-title">New Repair Job</h3>
      <button class="msp-modal-close" type="button">✕</button>
    </div>
    <div class="msp-modal-body">
      <form id="form-repair">
        <input type="hidden" id="repair-form-id">
        <div class="msp-form-row">
          <div class="msp-form-group">
            <label>Customer</label>
            <div class="msp-customer-field">
              <div class="msp-customer-search-wrap">
                <input type="text" id="repair-customer-search" class="msp-input" placeholder="🔍 Search customer…" autocomplete="off">
                <div id="repair-customer-dropdown" class="msp-customer-dropdown" style="display:none"></div>
              </div>
              <input type="hidden" id="repair-customer-id" value="">
              <button type="button" id="btn-repair-quick-add-customer" class="msp-btn msp-btn-sm msp-btn-green">＋ Add</button>
            </div>
          </div>
          <div class="msp-form-group">
            <label for="repair-device">Device Model *</label>
            <input type="text" id="repair-device" class="msp-input" required placeholder="e.g. iPhone 13 Pro">
          </div>
        </div>
        <div class="msp-form-group">
          <label for="repair-issue">Issue Description</label>
          <textarea id="repair-issue" class="msp-textarea" placeholder="Describe the fault…"></textarea>
        </div>
        <div class="msp-form-row">
          <div class="msp-form-group">
            <label for="repair-cost">Estimated Cost</label>
            <input type="number" id="repair-cost" class="msp-input" min="0" step="any" placeholder="0.00">
          </div>
          <div class="msp-form-group">
            <label for="repair-status">Status</label>
            <select id="repair-status" class="msp-select">
              <option value="pending">Pending</option>
              <option value="repairing">Repairing</option>
              <option value="fixed">Fixed</option>
              <option value="unrepairable">Unrepairable</option>
            </select>
          </div>
        </div>
        <div class="msp-modal-footer" style="padding:0;border:0;margin-top:6px">
          <button type="button" class="msp-btn msp-btn-ghost msp-modal-close">Cancel</button>
          <button type="submit" class="msp-btn msp-btn-primary">💾 Save Job</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Ledger Entry Modal -->
<div id="modal-ledger" class="msp-modal-overlay" style="display:none">
  <div class="msp-modal">
    <div class="msp-modal-header">
      <h3>New Ledger Entry</h3>
      <button class="msp-modal-close" type="button">✕</button>
    </div>
    <div class="msp-modal-body">
      <form id="form-ledger">
        <div class="msp-form-group">
          <label>Customer *</label>
          <div class="msp-customer-field">
            <div style="flex:1">
              <select id="ledger-entry-user" class="msp-select" required>
                <option value="">Select customer…</option>
              </select>
            </div>
            <button type="button" id="btn-ledger-quick-add-customer" class="msp-btn msp-btn-sm msp-btn-green">＋ New</button>
          </div>
        </div>
        <div class="msp-form-row">
          <div class="msp-form-group">
            <label for="ledger-entry-type">Transaction Type *</label>
            <select id="ledger-entry-type" class="msp-select" required>
              <option value="debit">Debit (Amount Owed)</option>
              <option value="credit">Credit (Payment Received)</option>
            </select>
          </div>
          <div class="msp-form-group">
            <label for="ledger-entry-amount">Amount *</label>
            <input type="number" id="ledger-entry-amount" class="msp-input" min="0.01" step="any" required placeholder="0.00">
          </div>
        </div>
        <div class="msp-form-group">
          <label for="ledger-entry-desc">Description</label>
          <textarea id="ledger-entry-desc" class="msp-textarea" placeholder="e.g. Payment received for sale #123"></textarea>
        </div>
        <div class="msp-modal-footer" style="padding:0;border:0;margin-top:6px">
          <button type="button" class="msp-btn msp-btn-ghost msp-modal-close">Cancel</button>
          <button type="submit" class="msp-btn msp-btn-primary">💾 Save Entry</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Expense Modal -->
<div id="modal-expense" class="msp-modal-overlay" style="display:none">
  <div class="msp-modal">
    <div class="msp-modal-header">
      <h3>Log Expense</h3>
      <button class="msp-modal-close" type="button">✕</button>
    </div>
    <div class="msp-modal-body">
      <form id="form-expense">
        <div class="msp-form-row">
          <div class="msp-form-group">
            <label for="expense-type">Expense Type *</label>
            <select id="expense-type" class="msp-select" required>
              <option value="rent">Rent</option>
              <option value="bill">Bill / Utility</option>
              <option value="salary">Salary</option>
              <option value="misc">Miscellaneous</option>
            </select>
          </div>
          <div class="msp-form-group">
            <label for="expense-amount">Amount *</label>
            <input type="number" id="expense-amount" class="msp-input" min="0.01" step="any" required placeholder="0.00">
          </div>
        </div>
        <div class="msp-form-group">
          <label for="expense-desc">Description</label>
          <textarea id="expense-desc" class="msp-textarea" placeholder="Optional notes…"></textarea>
        </div>
        <div class="msp-modal-footer" style="padding:0;border:0;margin-top:6px">
          <button type="button" class="msp-btn msp-btn-ghost msp-modal-close">Cancel</button>
          <button type="submit" class="msp-btn msp-btn-primary">💾 Log Expense</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Thermal Receipt Modal -->
<div id="modal-receipt" class="msp-modal-overlay" style="display:none">
  <div class="msp-modal" style="max-width:400px">
    <div class="msp-modal-header">
      <h3>🧾 Sale Receipt</h3>
      <button class="msp-modal-close" type="button">✕</button>
    </div>
    <div class="msp-modal-body" style="padding:14px">
      <iframe id="msp-receipt-frame" title="Receipt"></iframe>
    </div>
    <div class="msp-modal-footer">
      <button id="btn-print-receipt" class="msp-btn msp-btn-primary">🖨️ Print Receipt</button>
      <button class="msp-btn msp-btn-ghost msp-modal-close">Close</button>
    </div>
  </div>
</div>

<!-- Add / Edit Customer Modal -->
<div id="modal-customer" class="msp-modal-overlay" style="display:none">
  <div class="msp-modal">
    <div class="msp-modal-header">
      <h3 id="modal-customer-title">Add Customer</h3>
      <button class="msp-modal-close" type="button">✕</button>
    </div>
    <div class="msp-modal-body">
      <form id="form-customer">
        <input type="hidden" id="customer-form-id">
        <div class="msp-form-row">
          <div class="msp-form-group">
            <label for="customer-name">Full Name *</label>
            <input type="text" id="customer-name" class="msp-input" required placeholder="e.g. Ahmad Ali">
          </div>
          <div class="msp-form-group">
            <label for="customer-phone">Phone *</label>
            <input type="text" id="customer-phone" class="msp-input" required placeholder="03xx-xxxxxxx">
          </div>
        </div>
        <div class="msp-form-row">
          <div class="msp-form-group">
            <label for="customer-email">Email</label>
            <input type="email" id="customer-email" class="msp-input" placeholder="optional@email.com">
          </div>
          <div class="msp-form-group">
            <label for="customer-address">Address</label>
            <input type="text" id="customer-address" class="msp-input" placeholder="Optional">
          </div>
        </div>
        <div class="msp-modal-footer" style="padding:0;border:0;margin-top:6px">
          <button type="button" class="msp-btn msp-btn-ghost msp-modal-close">Cancel</button>
          <button type="submit" class="msp-btn msp-btn-green">💾 Save Customer</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Quick-Add Customer Modal (triggered from POS / Repair / Ledger) -->
<div id="modal-quick-customer" class="msp-modal-overlay" style="display:none">
  <div class="msp-modal" style="max-width:480px">
    <div class="msp-modal-header">
      <h3>➕ Quick Add Customer</h3>
      <button class="msp-modal-close" type="button">✕</button>
    </div>
    <div class="msp-modal-body">
      <form id="form-quick-customer">
        <input type="hidden" id="qc-trigger" value="">
        <div class="msp-form-row">
          <div class="msp-form-group">
            <label for="qc-name">Full Name *</label>
            <input type="text" id="qc-name" class="msp-input" required placeholder="e.g. Ahmad Ali">
          </div>
          <div class="msp-form-group">
            <label for="qc-phone">Phone *</label>
            <input type="text" id="qc-phone" class="msp-input" required placeholder="03xx-xxxxxxx">
          </div>
        </div>
        <div class="msp-form-row">
          <div class="msp-form-group">
            <label for="qc-email">Email</label>
            <input type="email" id="qc-email" class="msp-input" placeholder="optional@email.com">
          </div>
          <div class="msp-form-group">
            <label for="qc-address">Address</label>
            <input type="text" id="qc-address" class="msp-input" placeholder="Optional">
          </div>
        </div>
        <div class="msp-modal-footer" style="padding:0;border:0;margin-top:6px">
          <button type="button" class="msp-btn msp-btn-ghost msp-modal-close">Cancel</button>
          <button type="submit" class="msp-btn msp-btn-green">💾 Save &amp; Select</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Customer Statement Modal -->
<div id="modal-customer-statement" class="msp-modal-overlay" style="display:none">
  <div class="msp-modal" style="max-width:720px">
    <div class="msp-modal-header">
      <h3 id="statement-title">📋 Customer Statement</h3>
      <button class="msp-modal-close" type="button">✕</button>
    </div>
    <div class="msp-modal-body" id="statement-body">
      <div style="text-align:center;padding:30px"><span class="msp-spinner"></span></div>
    </div>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════════════════════════ -->
<!-- Scripts                                                                     -->
<!-- ══════════════════════════════════════════════════════════════════════════ -->
<script src="<?php echo esc_url( includes_url( 'js/jquery/jquery.min.js' ) ); ?>"></script>
<script>
var MSP_AJAX = {
    ajaxurl: <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>,
    nonce:   <?php echo wp_json_encode( wp_create_nonce( 'msp_nonce' ) ); ?>
};
// Live clock
(function tick() {
    var now = new Date();
    var h = String(now.getHours()).padStart(2,'0');
    var m = String(now.getMinutes()).padStart(2,'0');
    var s = String(now.getSeconds()).padStart(2,'0');
    document.getElementById('live-clock').textContent = h + ':' + m + ':' + s;
    setTimeout(tick, 1000);
}());
</script>
<script src="<?php echo esc_url( MSP_PLUGIN_URL . 'assets/app.js' ); ?>?v=<?php echo esc_attr( MSP_VERSION ); ?>"></script>

</body>
</html>
