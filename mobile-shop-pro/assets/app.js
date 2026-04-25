/**
 * Mobile Shop Smart Management System – app.js
 * jQuery-driven AJAX frontend for the standalone POS dashboard.
 * All CRUD operations are handled without page reloads.
 *
 * Designed and Developed by Sikandar Hayat Baba
 */

/* global MSP_AJAX, jQuery */
(function ($) {
    'use strict';

    /* ── Helpers ────────────────────────────────────────────────────────────── */

    function toast(msg, type) {
        type = type || 'info';
        var icons = { success: '✅', error: '❌', info: 'ℹ️', warning: '⚠️' };
        var $t = $('<div class="msp-toast ' + type + '">' + (icons[type] || '') + ' ' + msg + '</div>');
        $('#msp-toasts').append($t);
        setTimeout(function () { $t.remove(); }, 4200);
    }

    function ajax(action, data, done, fail) {
        var payload = $.extend({ action: action, nonce: MSP_AJAX.nonce }, data);
        return $.post(MSP_AJAX.ajaxurl, payload)
            .done(function (res) {
                if (res && res.success) {
                    if (typeof done === 'function') done(res.data);
                } else {
                    var msg = (res && res.data && res.data.message) ? res.data.message : 'Request failed.';
                    toast(msg, 'error');
                    if (typeof fail === 'function') fail(msg);
                }
            })
            .fail(function () {
                toast('Server error. Please try again.', 'error');
                if (typeof fail === 'function') fail('Server error.');
            });
    }

    function openModal(id) { $('#' + id).show(); }
    function closeModal(id) { $('#' + id).hide(); }

    function confirmAction(msg, cb) {
        // Simple native confirm; replace with a custom modal if desired.
        if (window.confirm(msg)) cb();
    }

    function statusPill(status) {
        var map = {
            in_stock:      'pill-green',
            sold:          'pill-red',
            returned:      'pill-amber',
            paid:          'pill-green',
            credit:        'pill-red',
            partial:       'pill-amber',
            pending:       'pill-amber',
            repairing:     'pill-blue',
            fixed:         'pill-green',
            unrepairable:  'pill-red',
            mobile:        'pill-purple',
            accessory:     'pill-blue',
            part:          'pill-gray',
            credit_type:   'pill-red',
            debit:         'pill-green',
        };
        return '<span class="pill ' + (map[status] || 'pill-gray') + '">' + status + '</span>';
    }

    function esc(str) {
        return $('<div>').text(str).html();
    }

    /* ── Navigation ─────────────────────────────────────────────────────────── */

    function navigate(section) {
        $('.msp-section').removeClass('active');
        $('#msp-nav li').removeClass('active');
        $('#section-' + section).addClass('active');
        $('#nav-' + section).addClass('active');
        $('#msp-header-title').text($('#nav-' + section + ' a .nav-label').text() || 'Dashboard');

        var loaders = {
            dashboard:  loadDashboard,
            pos:        initPOS,
            inventory:  loadInventory,
            repair:     loadRepairs,
            crm:        loadLedger,
            reports:    loadReports,
            expenses:   loadExpenses,
        };
        if (loaders[section]) loaders[section]();
    }

    $('#msp-nav li').on('click', function () {
        navigate($(this).data('section'));
    });

    /* ════════════════════════════════════════════════════════════════════════ */
    /* SECTION: DASHBOARD                                                       */
    /* ════════════════════════════════════════════════════════════════════════ */

    function loadDashboard() {
        ajax('msp_get_metrics', {}, function (data) {
            $('#metric-daily-sales').text('PKR ' + data.daily_sales);
            $('#metric-daily-profit').text('PKR ' + data.daily_profit);
            $('#metric-pending').text('PKR ' + data.pending_payments);
            $('#metric-low-stock').text(data.low_stock);
            $('#metric-total-products').text(data.total_products);
            $('#metric-open-repairs').text(data.open_repairs);
            $('#metric-monthly-revenue').text('PKR ' + data.monthly_revenue);

            // Recent sales table
            var rows = '';
            if (data.recent_sales && data.recent_sales.length) {
                $.each(data.recent_sales, function (i, s) {
                    rows += '<tr>' +
                        '<td>#' + esc(String(s.id)) + '</td>' +
                        '<td>' + esc(s.customer_name) + '</td>' +
                        '<td>PKR ' + esc(s.net_total) + '</td>' +
                        '<td>' + statusPill(s.payment_status) + '</td>' +
                        '<td>' + esc(s.sale_date) + '</td>' +
                        '</tr>';
                });
            } else {
                rows = '<tr><td colspan="5" class="msp-empty"><p>No sales today yet.</p></td></tr>';
            }
            $('#recent-sales-tbody').html(rows);
        });
    }

    /* ════════════════════════════════════════════════════════════════════════ */
    /* SECTION: POINT OF SALE (POS)                                            */
    /* ════════════════════════════════════════════════════════════════════════ */

    var cart = [];

    function initPOS() {
        cart = [];
        renderCart();
        loadPOSCustomers();
        $('#msp-barcode-input').focus();
    }

    function loadPOSCustomers() {
        ajax('msp_get_customers', {}, function (data) {
            var opts = '<option value="">Walk-in Customer</option>';
            $.each(data, function (i, u) {
                opts += '<option value="' + esc(String(u.id)) + '">' + esc(u.name) + '</option>';
            });
            $('#pos-customer').html(opts);
        });
    }

    // ── Barcode / Product Search ─────────────────────────────────────────────
    // Listens for Enter keypress – optimised for USB barcode scanner input.
    var barcodeTimer = null;
    $('#msp-barcode-input').on('keydown', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            clearTimeout(barcodeTimer);
            doProductLookup();
        }
    }).on('input', function () {
        // Auto-trigger after 800 ms of inactivity (for manual typing).
        clearTimeout(barcodeTimer);
        var val = $(this).val().trim();
        if (val.length >= 3) {
            barcodeTimer = setTimeout(doProductLookup, 800);
        } else {
            $('#msp-pos-results').empty();
        }
    });

    function doProductLookup() {
        var term = $('#msp-barcode-input').val().trim();
        if (!term) return;

        ajax('msp_pos_lookup', { term: term }, function (data) {
            if (data.type === 'imei') {
                // Exact IMEI match – add directly to cart.
                addToCart({
                    product_id:   data.item.product_id,
                    imei_id:      data.item.imei_id,
                    product_name: data.item.product_name + (data.item.variant ? ' (' + data.item.variant + ')' : ''),
                    price:        parseFloat(data.item.selling_price),
                    quantity:     1,
                    imei_serial:  data.item.imei_serial,
                    is_imei:      true,
                });
                $('#msp-barcode-input').val('').focus();
                $('#msp-pos-results').empty();
            } else {
                // Product list – show for user selection.
                renderProductResults(data.items);
            }
        });
    }

    function renderProductResults(items) {
        var $results = $('#msp-pos-results');
        $results.empty();
        if (!items || !items.length) {
            $results.html('<p style="color:var(--text-muted);padding:10px;">No products found.</p>');
            return;
        }
        var html = '<table class="msp-table"><thead><tr><th>Product</th><th>Variant</th><th>Price</th><th>Stock</th><th>Add</th></tr></thead><tbody>';
        $.each(items, function (i, p) {
            html += '<tr>' +
                '<td>' + esc(p.product_name) + '</td>' +
                '<td>' + esc(p.variant || '-') + '</td>' +
                '<td>PKR ' + esc(p.selling_price) + '</td>' +
                '<td>' + esc(String(p.stock_quantity)) + '</td>' +
                '<td><button class="msp-btn msp-btn-primary msp-btn-sm pos-add-btn" ' +
                    'data-id="' + p.product_id + '" ' +
                    'data-name="' + esc(p.product_name + (p.variant ? ' (' + p.variant + ')' : '')) + '" ' +
                    'data-price="' + p.selling_price + '">+ Add</button></td>' +
                '</tr>';
        });
        html += '</tbody></table>';
        $results.html(html);
    }

    $(document).on('click', '.pos-add-btn', function () {
        addToCart({
            product_id:   $(this).data('id'),
            imei_id:      null,
            product_name: $(this).data('name'),
            price:        parseFloat($(this).data('price')),
            quantity:     1,
            is_imei:      false,
        });
        $('#msp-pos-results').empty();
        $('#msp-barcode-input').val('').focus();
    });

    function addToCart(item) {
        // For non-IMEI items, merge with existing cart line.
        if (!item.is_imei) {
            var existing = null;
            $.each(cart, function (i, c) {
                if (c.product_id === item.product_id && !c.is_imei) {
                    existing = i;
                    return false;
                }
            });
            if (existing !== null) {
                cart[existing].quantity += 1;
                renderCart();
                toast(item.product_name + ' qty updated.', 'success');
                return;
            }
        }
        cart.push(item);
        renderCart();
        toast(item.product_name + ' added to cart.', 'success');
    }

    function renderCart() {
        var $body = $('#msp-cart-items');
        if (!cart.length) {
            $body.html('<div class="msp-empty"><div class="empty-icon">🛒</div><p>Cart is empty</p></div>');
            updateCartTotals(0, 0);
            return;
        }

        var html = '';
        var subtotal = 0;
        $.each(cart, function (i, item) {
            var lineTotal = item.price * item.quantity;
            subtotal += lineTotal;
            html += '<div class="msp-cart-item" data-index="' + i + '">' +
                '<div class="item-name">' +
                    '<div style="font-weight:600">' + esc(item.product_name) + '</div>' +
                    (item.imei_serial ? '<div style="font-size:11px;color:var(--text-muted)">IMEI: ' + esc(item.imei_serial) + '</div>' : '') +
                '</div>' +
                '<div class="item-qty">' +
                    (item.is_imei
                        ? '<span>1</span>'
                        : '<input type="number" class="msp-input cart-qty" min="1" value="' + item.quantity + '" style="width:54px;padding:4px 6px;text-align:center">') +
                '</div>' +
                '<div class="item-price">PKR ' + lineTotal.toFixed(2) + '</div>' +
                '<button class="msp-btn msp-btn-danger msp-btn-sm cart-remove" data-index="' + i + '">✕</button>' +
                '</div>';
        });
        $body.html(html);

        var discount = parseFloat($('#pos-discount').val()) || 0;
        updateCartTotals(subtotal, discount);
    }

    function updateCartTotals(subtotal, discount) {
        var net = Math.max(0, subtotal - discount);
        $('#cart-subtotal').text('PKR ' + subtotal.toFixed(2));
        $('#cart-discount').text('PKR ' + discount.toFixed(2));
        $('#cart-net').text('PKR ' + net.toFixed(2));
    }

    // Qty change in cart
    $(document).on('change', '.cart-qty', function () {
        var idx = $(this).closest('.msp-cart-item').data('index');
        var qty = parseInt($(this).val(), 10);
        if (qty < 1) qty = 1;
        cart[idx].quantity = qty;
        renderCart();
    });

    // Remove from cart
    $(document).on('click', '.cart-remove', function () {
        var idx = $(this).data('index');
        cart.splice(idx, 1);
        renderCart();
    });

    // Discount change
    $('#pos-discount').on('input', function () {
        var subtotal = 0;
        $.each(cart, function (i, item) { subtotal += item.price * item.quantity; });
        updateCartTotals(subtotal, parseFloat($(this).val()) || 0);
    });

    // ── Checkout ─────────────────────────────────────────────────────────────
    $('#btn-pos-checkout').on('click', function () {
        if (!cart.length) { toast('Cart is empty.', 'error'); return; }

        var cartItems = [];
        $.each(cart, function (i, item) {
            cartItems.push({
                product_id: item.product_id,
                imei_id:    item.imei_id || '',
                quantity:   item.quantity,
                price:      item.price,
            });
        });

        var $btn = $(this);
        $btn.html('<span class="msp-spinner"></span> Processing…').prop('disabled', true);

        ajax('msp_pos_checkout', {
            cart_items:     JSON.stringify(cartItems),
            customer_id:    $('#pos-customer').val(),
            discount:       $('#pos-discount').val() || 0,
            payment_status: $('#pos-payment-status').val(),
        }, function (data) {
            $btn.html('💳 Checkout').prop('disabled', false);
            toast('Sale #' + data.sale_id + ' completed!', 'success');

            // Show receipt in modal
            var $frame = $('#msp-receipt-frame');
            $frame[0].srcdoc = data.receipt;
            openModal('modal-receipt');

            // Reset cart
            cart = [];
            renderCart();
            $('#pos-discount').val(0);
            $('#msp-barcode-input').focus();
        }, function () {
            $btn.html('💳 Checkout').prop('disabled', false);
        });
    });

    $('#btn-print-receipt').on('click', function () {
        $('#msp-receipt-frame')[0].contentWindow.print();
    });

    /* ════════════════════════════════════════════════════════════════════════ */
    /* SECTION: INVENTORY                                                       */
    /* ════════════════════════════════════════════════════════════════════════ */

    function loadInventory() {
        var search   = $('#inv-search').val() || '';
        var category = $('#inv-category-filter').val() || '';
        ajax('msp_get_inventory', { search: search, category: category }, function (data) {
            var html = '';
            if (!data.length) {
                html = '<tr><td colspan="8"><div class="msp-empty"><div class="empty-icon">📦</div><p>No products found.</p></div></td></tr>';
            } else {
                $.each(data, function (i, p) {
                    html += '<tr>' +
                        '<td>' + esc(String(p.id)) + '</td>' +
                        '<td>' + esc(p.product_name) + '</td>' +
                        '<td>' + statusPill(p.category) + '</td>' +
                        '<td>' + esc(p.variant || '–') + '</td>' +
                        '<td>PKR ' + esc(p.cost_price) + '</td>' +
                        '<td>PKR ' + esc(p.selling_price) + '</td>' +
                        '<td>' + (parseInt(p.stock_quantity, 10) <= 5
                            ? '<span class="pill pill-red">' + p.stock_quantity + '</span>'
                            : '<span class="pill pill-green">' + p.stock_quantity + '</span>') + '</td>' +
                        '<td><div class="actions">' +
                            '<button class="msp-btn msp-btn-ghost msp-btn-sm inv-edit" ' +
                                'data-id="' + p.id + '" data-name="' + esc(p.product_name) + '" ' +
                                'data-cat="' + esc(p.category) + '" data-variant="' + esc(p.variant || '') + '" ' +
                                'data-cost="' + p.cost_price + '" data-sell="' + p.selling_price + '" ' +
                                'data-stock="' + p.stock_quantity + '">✏️</button>' +
                            '<button class="msp-btn msp-btn-danger msp-btn-sm inv-delete" data-id="' + p.id + '">🗑️</button>' +
                            '<button class="msp-btn msp-btn-ghost msp-btn-sm inv-imei" data-id="' + p.id + '" data-name="' + esc(p.product_name) + '">📱 IMEI</button>' +
                        '</div></td>' +
                        '</tr>';
                });
            }
            $('#inventory-tbody').html(html);
        });
    }

    // Search & filter
    $('#inv-search, #inv-category-filter').on('change input', function () {
        clearTimeout(window._invTimer);
        window._invTimer = setTimeout(loadInventory, 400);
    });

    // ── Add Product Modal ─────────────────────────────────────────────────────
    $('#btn-add-product').on('click', function () {
        $('#form-product')[0].reset();
        $('#product-form-id').val('');
        $('#modal-product-title').text('Add New Product');
        openModal('modal-product');
    });

    $(document).on('click', '.inv-edit', function () {
        var $el = $(this);
        $('#product-form-id').val($el.data('id'));
        $('#product-name').val($el.data('name'));
        $('#product-category').val($el.data('cat'));
        $('#product-variant').val($el.data('variant'));
        $('#product-cost').val($el.data('cost'));
        $('#product-sell').val($el.data('sell'));
        $('#product-stock').val($el.data('stock'));
        $('#modal-product-title').text('Edit Product');
        openModal('modal-product');
    });

    $('#form-product').on('submit', function (e) {
        e.preventDefault();
        var id     = $('#product-form-id').val();
        var action = id ? 'msp_update_product' : 'msp_add_product';
        var data   = {
            id:             id,
            product_name:   $('#product-name').val(),
            category:       $('#product-category').val(),
            variant:        $('#product-variant').val(),
            cost_price:     $('#product-cost').val(),
            selling_price:  $('#product-sell').val(),
            stock_quantity: $('#product-stock').val(),
        };
        ajax(action, data, function () {
            closeModal('modal-product');
            toast('Product saved!', 'success');
            loadInventory();
        });
    });

    $(document).on('click', '.inv-delete', function () {
        var id = $(this).data('id');
        confirmAction('Delete this product? This cannot be undone.', function () {
            ajax('msp_delete_product', { id: id }, function () {
                toast('Product deleted.', 'success');
                loadInventory();
            });
        });
    });

    // ── IMEI Management Modal ─────────────────────────────────────────────────
    $(document).on('click', '.inv-imei', function () {
        var id   = $(this).data('id');
        var name = $(this).data('name');
        $('#imei-product-id').val(id);
        $('#modal-imei-title').text('IMEI / Serials: ' + name);
        loadIMEIList(id);
        openModal('modal-imei');
    });

    function loadIMEIList(productId) {
        ajax('msp_get_imei_list', { product_id: productId }, function (data) {
            var html = '';
            if (!data.length) {
                html = '<tr><td colspan="3"><p style="text-align:center;color:var(--text-muted);padding:12px">No IMEI/Serials added yet.</p></td></tr>';
            } else {
                $.each(data, function (i, row) {
                    html += '<tr><td>' + esc(String(row.id)) + '</td><td>' + esc(row.imei_serial) + '</td><td>' + statusPill(row.status) + '</td></tr>';
                });
            }
            $('#imei-list-tbody').html(html);
        });
    }

    $('#form-add-imei').on('submit', function (e) {
        e.preventDefault();
        var productId = $('#imei-product-id').val();
        ajax('msp_add_imei', {
            product_id:  productId,
            imei_serial: $('#new-imei-serial').val(),
        }, function () {
            toast('IMEI/Serial added!', 'success');
            $('#new-imei-serial').val('');
            loadIMEIList(productId);
            loadInventory();
        });
    });

    /* ════════════════════════════════════════════════════════════════════════ */
    /* SECTION: REPAIR LAB                                                      */
    /* ════════════════════════════════════════════════════════════════════════ */

    function loadRepairs() {
        var status = $('#repair-status-filter').val() || '';
        ajax('msp_get_repairs', { status: status }, function (data) {
            var html = '';
            if (!data.length) {
                html = '<tr><td colspan="8"><div class="msp-empty"><div class="empty-icon">🔧</div><p>No repair jobs found.</p></div></td></tr>';
            } else {
                $.each(data, function (i, r) {
                    html += '<tr>' +
                        '<td>' + esc(r.job_card_number) + '</td>' +
                        '<td>' + esc(r.customer_name) + '</td>' +
                        '<td>' + esc(r.device_model) + '</td>' +
                        '<td>' + esc(r.issue_desc || '–') + '</td>' +
                        '<td>PKR ' + esc(r.est_cost) + '</td>' +
                        '<td>' + statusPill(r.status) + '</td>' +
                        '<td>' + esc(r.received_date) + '</td>' +
                        '<td><div class="actions">' +
                            '<button class="msp-btn msp-btn-ghost msp-btn-sm repair-edit" ' +
                                'data-id="' + r.id + '" data-model="' + esc(r.device_model) + '" ' +
                                'data-issue="' + esc(r.issue_desc || '') + '" ' +
                                'data-cost="' + r.est_cost + '" data-status="' + r.status + '">✏️ Update</button>' +
                            '<button class="msp-btn msp-btn-danger msp-btn-sm repair-delete" data-id="' + r.id + '">🗑️</button>' +
                        '</div></td>' +
                        '</tr>';
                });
            }
            $('#repair-tbody').html(html);
        });
    }

    $('#repair-status-filter').on('change', loadRepairs);

    $('#btn-add-repair').on('click', function () {
        $('#form-repair')[0].reset();
        $('#repair-form-id').val('');
        $('#modal-repair-title').text('New Repair Job');
        loadRepairCustomers();
        openModal('modal-repair');
    });

    function loadRepairCustomers() {
        ajax('msp_get_customers', {}, function (data) {
            var opts = '<option value="">No Customer</option>';
            $.each(data, function (i, u) {
                opts += '<option value="' + u.id + '">' + esc(u.name) + '</option>';
            });
            $('#repair-customer').html(opts);
        });
    }

    $(document).on('click', '.repair-edit', function () {
        var $el = $(this);
        $('#repair-form-id').val($el.data('id'));
        $('#repair-device').val($el.data('model'));
        $('#repair-issue').val($el.data('issue'));
        $('#repair-cost').val($el.data('cost'));
        $('#repair-status').val($el.data('status'));
        $('#modal-repair-title').text('Update Repair Job');
        loadRepairCustomers();
        openModal('modal-repair');
    });

    $('#form-repair').on('submit', function (e) {
        e.preventDefault();
        var id     = $('#repair-form-id').val();
        var action = id ? 'msp_update_repair' : 'msp_add_repair';
        ajax(action, {
            id:           id,
            customer_id:  $('#repair-customer').val(),
            device_model: $('#repair-device').val(),
            issue_desc:   $('#repair-issue').val(),
            est_cost:     $('#repair-cost').val(),
            status:       $('#repair-status').val(),
        }, function () {
            closeModal('modal-repair');
            toast('Repair job saved!', 'success');
            loadRepairs();
        });
    });

    $(document).on('click', '.repair-delete', function () {
        var id = $(this).data('id');
        confirmAction('Delete this repair job?', function () {
            ajax('msp_delete_repair', { id: id }, function () {
                toast('Repair job deleted.', 'success');
                loadRepairs();
            });
        });
    });

    /* ════════════════════════════════════════════════════════════════════════ */
    /* SECTION: CRM / LEDGER                                                    */
    /* ════════════════════════════════════════════════════════════════════════ */

    function loadLedger() {
        loadLedgerCustomers();
        loadLedgerEntries();
    }

    function loadLedgerCustomers() {
        ajax('msp_get_customers', {}, function (data) {
            var opts = '<option value="">All Customers/Suppliers</option>';
            $.each(data, function (i, u) {
                opts += '<option value="' + u.id + '">' + esc(u.name) + ' (' + esc(u.email) + ')</option>';
            });
            $('#ledger-user-filter, #ledger-entry-user').html(opts);
        });
    }

    function loadLedgerEntries() {
        var userId = $('#ledger-user-filter').val() || '';
        ajax('msp_get_ledger', { user_id: userId }, function (data) {
            var balance = data.balance;
            var rows    = data.rows;

            $('#ledger-balance').text('Balance: PKR ' + balance)
                .css('color', parseFloat(balance) > 0 ? 'var(--danger)' : 'var(--success)');

            var html = '';
            if (!rows.length) {
                html = '<tr><td colspan="6"><div class="msp-empty"><div class="empty-icon">📒</div><p>No ledger entries found.</p></div></td></tr>';
            } else {
                $.each(rows, function (i, r) {
                    var typeClass = r.transaction_type === 'debit' ? 'pill-red' : 'pill-green';
                    html += '<tr>' +
                        '<td>' + esc(String(r.id)) + '</td>' +
                        '<td>' + esc(r.user_name) + '</td>' +
                        '<td><span class="pill ' + typeClass + '">' + esc(r.transaction_type) + '</span></td>' +
                        '<td>PKR ' + esc(r.amount) + '</td>' +
                        '<td>' + esc(r.description || '–') + '</td>' +
                        '<td>' + esc(r.transaction_date) + '</td>' +
                        '</tr>';
                });
            }
            $('#ledger-tbody').html(html);
        });
    }

    $('#ledger-user-filter').on('change', loadLedgerEntries);

    $('#btn-add-ledger').on('click', function () {
        openModal('modal-ledger');
    });

    $('#form-ledger').on('submit', function (e) {
        e.preventDefault();
        ajax('msp_add_ledger', {
            user_id:          $('#ledger-entry-user').val(),
            transaction_type: $('#ledger-entry-type').val(),
            amount:           $('#ledger-entry-amount').val(),
            description:      $('#ledger-entry-desc').val(),
        }, function () {
            closeModal('modal-ledger');
            toast('Ledger entry added!', 'success');
            loadLedgerEntries();
        });
    });

    /* ════════════════════════════════════════════════════════════════════════ */
    /* SECTION: EXPENSES                                                        */
    /* ════════════════════════════════════════════════════════════════════════ */

    function loadExpenses() {
        ajax('msp_get_expenses', {}, function (data) {
            var html = '';
            var total = 0;
            if (!data.length) {
                html = '<tr><td colspan="5"><div class="msp-empty"><div class="empty-icon">💸</div><p>No expenses logged yet.</p></div></td></tr>';
            } else {
                $.each(data, function (i, ex) {
                    total += parseFloat(ex.amount);
                    html += '<tr>' +
                        '<td>' + esc(String(ex.id)) + '</td>' +
                        '<td>' + statusPill(ex.expense_type) + '</td>' +
                        '<td>PKR ' + esc(ex.amount) + '</td>' +
                        '<td>' + esc(ex.description || '–') + '</td>' +
                        '<td>' + esc(ex.expense_date) + '</td>' +
                        '<td><button class="msp-btn msp-btn-danger msp-btn-sm exp-delete" data-id="' + ex.id + '">🗑️</button></td>' +
                        '</tr>';
                });
            }
            $('#expenses-tbody').html(html);
            $('#expense-total').text('Total: PKR ' + total.toFixed(2));
        });
    }

    $('#btn-add-expense').on('click', function () {
        $('#form-expense')[0].reset();
        openModal('modal-expense');
    });

    $('#form-expense').on('submit', function (e) {
        e.preventDefault();
        ajax('msp_add_expense', {
            expense_type: $('#expense-type').val(),
            amount:       $('#expense-amount').val(),
            description:  $('#expense-desc').val(),
        }, function () {
            closeModal('modal-expense');
            toast('Expense logged!', 'success');
            loadExpenses();
        });
    });

    $(document).on('click', '.exp-delete', function () {
        var id = $(this).data('id');
        confirmAction('Delete this expense?', function () {
            ajax('msp_delete_expense', { id: id }, function () {
                toast('Expense deleted.', 'success');
                loadExpenses();
            });
        });
    });

    /* ════════════════════════════════════════════════════════════════════════ */
    /* SECTION: REPORTS                                                         */
    /* ════════════════════════════════════════════════════════════════════════ */

    function loadReports() {
        var dateFrom = $('#report-date-from').val();
        var dateTo   = $('#report-date-to').val();
        ajax('msp_get_report', { date_from: dateFrom, date_to: dateTo }, function (data) {
            // Summary
            $('#report-revenue').text('PKR ' + parseFloat(data.sales_summary.total_revenue).toFixed(2));
            $('#report-sales-count').text(data.sales_summary.total_sales);
            $('#report-gross-profit').text('PKR ' + data.gross_profit);
            $('#report-expenses').text('PKR ' + data.total_expenses);
            $('#report-net-profit').text('PKR ' + data.net_profit)
                .css('color', parseFloat(data.net_profit) >= 0 ? 'var(--success)' : 'var(--danger)');

            // Daily chart (simple bar chart)
            renderBarChart('#report-daily-chart', data.daily_chart, 'day', 'revenue', 'PKR ');

            // Category breakdown
            var catHtml = '';
            $.each(data.category_breakdown, function (i, c) {
                catHtml += '<tr><td>' + statusPill(c.category) + '</td><td>' + esc(String(c.items_sold)) + '</td><td>PKR ' + parseFloat(c.revenue).toFixed(2) + '</td></tr>';
            });
            $('#report-category-tbody').html(catHtml || '<tr><td colspan="3" style="color:var(--text-muted);text-align:center">No data</td></tr>');

            // Expense breakdown
            var expHtml = '';
            $.each(data.expense_breakdown, function (i, ex) {
                expHtml += '<tr><td>' + statusPill(ex.expense_type) + '</td><td>PKR ' + parseFloat(ex.total).toFixed(2) + '</td></tr>';
            });
            $('#report-expense-tbody').html(expHtml || '<tr><td colspan="2" style="color:var(--text-muted);text-align:center">No expenses</td></tr>');
        });
    }

    function renderBarChart(selector, data, labelKey, valueKey, prefix) {
        if (!data || !data.length) {
            $(selector).html('<p style="color:var(--text-muted);text-align:center;padding:20px">No data for selected period.</p>');
            return;
        }
        var maxVal = 0;
        $.each(data, function (i, d) { if (parseFloat(d[valueKey]) > maxVal) maxVal = parseFloat(d[valueKey]); });
        var html = '<div class="msp-chart-bar-wrap">';
        $.each(data, function (i, d) {
            var pct = maxVal > 0 ? (parseFloat(d[valueKey]) / maxVal * 100) : 0;
            html += '<div class="msp-chart-bar-row">' +
                '<div class="msp-chart-bar-label">' + esc(String(d[labelKey])) + '</div>' +
                '<div class="msp-chart-bar-track"><div class="msp-chart-bar-fill" style="width:' + pct + '%"></div></div>' +
                '<div class="msp-chart-bar-val">' + (prefix || '') + parseFloat(d[valueKey]).toFixed(0) + '</div>' +
                '</div>';
        });
        html += '</div>';
        $(selector).html(html);
    }

    // Set default date range to current month
    (function () {
        var now  = new Date();
        var yyyy = now.getFullYear();
        var mm   = String(now.getMonth() + 1).padStart(2, '0');
        var dd   = String(now.getDate()).padStart(2, '0');
        $('#report-date-from').val(yyyy + '-' + mm + '-01');
        $('#report-date-to').val(yyyy + '-' + mm + '-' + dd);
    }());

    $('#btn-run-report').on('click', loadReports);

    /* ── Global Modal Close Handlers ─────────────────────────────────────── */

    $(document).on('click', '.msp-modal-overlay', function (e) {
        if ($(e.target).hasClass('msp-modal-overlay')) {
            $(this).hide();
        }
    });
    $(document).on('click', '.msp-modal-close', function () {
        $(this).closest('.msp-modal-overlay').hide();
    });

    /* ── Init: load dashboard on page load ─────────────────────────────── */
    navigate('dashboard');

}(jQuery));
