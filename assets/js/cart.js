(function () {
  'use strict';

  var KEY = 'miral-cart';

  var PRODUCTS = {
    dagelijks: { price: 12.49, bundlePrice: 49.95 },
    diepte:    { price: 14.95, bundlePrice: 59.95 }
  };
  var SHIP = 4.95, FREE_THRESHOLD = 50;

  /* ── i18n helper ── */
  function ct(key) {
    return (typeof t === 'function') ? t('cart.' + key) : key;
  }
  function prodName(key) {
    var map = { dagelijks: 'prodDagelijks', diepte: 'prodDiepte' };
    return map[key] ? ct(map[key]) : key;
  }

  /* ── Storage ── */
  function getCart() {
    try { return JSON.parse(localStorage.getItem(KEY)) || []; } catch (e) { return []; }
  }
  function saveCart(c) { localStorage.setItem(KEY, JSON.stringify(c)); }

  /* ── Helpers ── */
  function fmt(n) { return '€ ' + n.toFixed(2).replace('.', ','); }

  function calcTotals(cart) {
    var sub = cart.reduce(function (s, i) { return s + i.linePrice; }, 0);
    var hasBundle = cart.some(function (i) { return i.bundle; });
    var ship = (hasBundle || sub >= FREE_THRESHOLD) ? 0 : (cart.length ? SHIP : 0);
    return { sub: sub, ship: ship, grand: sub + ship };
  }

  function totalQty(cart) {
    return cart.reduce(function (s, i) { return s + i.qty; }, 0);
  }

  /* ── Badge ── */
  function updateBadge() {
    var b = document.getElementById('cart-badge');
    if (!b) return;
    var q = totalQty(getCart());
    b.textContent = q;
    b.style.display = q > 0 ? 'flex' : 'none';
  }

  /* ── Render sidebar ── */
  function renderSidebar() {
    var list = document.getElementById('cart-items-list');
    if (!list) return;
    var cart = getCart();

    if (!cart.length) {
      list.innerHTML = '<p class="cart-empty">' + ct('empty') + '</p>';
    } else {
      list.innerHTML = cart.map(function (item, i) {
        var name = item.key ? prodName(item.key) : item.name;
        var typeLabel = item.bundle
          ? ct('bundle') + ' · ' + fmt(item.unitPrice) + ' ' + ct('perUnit')
          : ct('single') + ' · ' + fmt(item.unitPrice) + ' ' + ct('perUnit');
        return '<div class="cart-item">' +
          '<div class="cart-item-info">' +
            '<div class="cart-item-name">' + name + '</div>' +
            '<div class="cart-item-qty-row">' +
              '<span class="cart-item-type">' + typeLabel + '</span>' +
              '<div class="cart-qty-stepper">' +
                '<button type="button" class="cart-qty-btn" data-idx="' + i + '" data-delta="-1" aria-label="−">−</button>' +
                '<span class="cart-qty-val">' + item.qty + '</span>' +
                '<button type="button" class="cart-qty-btn" data-idx="' + i + '" data-delta="1" aria-label="+">+</button>' +
              '</div>' +
            '</div>' +
          '</div>' +
          '<div class="cart-item-right">' +
            '<div class="cart-item-price">' + fmt(item.linePrice) + '</div>' +
            '<button type="button" class="cart-item-remove" data-idx="' + i + '" aria-label="×">' +
              '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>' +
            '</button>' +
          '</div>' +
        '</div>';
      }).join('');

      list.querySelectorAll('.cart-item-remove').forEach(function (btn) {
        btn.addEventListener('click', function () { removeItem(parseInt(this.dataset.idx, 10)); });
      });

      list.querySelectorAll('.cart-qty-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
          changeQty(parseInt(this.dataset.idx, 10), parseInt(this.dataset.delta, 10));
        });
      });
    }

    var tots = calcTotals(cart);
    var subEl   = document.getElementById('cart-sub');
    var shipEl  = document.getElementById('cart-ship');
    var grandEl = document.getElementById('cart-grand');
    var freeEl  = document.getElementById('cart-free-note');
    var freeNoteEl = document.getElementById('cart-free-note-text');
    var subLblEl   = document.getElementById('cart-sub-lbl');
    var shipLblEl  = document.getElementById('cart-ship-lbl');
    var grandLblEl = document.getElementById('cart-grand-lbl');
    var checkoutEl = document.getElementById('cart-checkout-btn');
    var titleEl    = document.getElementById('cart-title');

    if (subEl)     subEl.textContent   = fmt(tots.sub);
    if (shipEl)    shipEl.textContent  = tots.ship === 0 && cart.length ? ct('free') : fmt(tots.ship);
    if (grandEl)   grandEl.textContent = fmt(tots.grand);
    if (freeEl)    freeEl.style.display = (tots.ship === 0 && cart.length) ? 'flex' : 'none';
    if (freeNoteEl) freeNoteEl.textContent = ct('freeNote');
    if (subLblEl)   subLblEl.textContent   = ct('subtotal');
    if (shipLblEl)  shipLblEl.textContent  = ct('shipping');
    if (grandLblEl) grandLblEl.textContent = ct('total');
    if (checkoutEl) checkoutEl.textContent = ct('checkout');
    if (titleEl)    titleEl.textContent    = ct('title');
  }

  /* ── CRUD ── */
  function applyBundleUpgrade(cart, idx) {
    var item = cart[idx];
    var prod = PRODUCTS[item.key];
    if (!item.bundle && item.qty >= 6 && prod) {
      item.bundle    = true;
      item.qty       = Math.floor(item.qty / 6);
      item.unitPrice = prod.bundlePrice;
      item.linePrice = prod.bundlePrice * item.qty;
    }
  }

  function addItem(item) {
    var cart = getCart();
    var found = -1;
    cart.forEach(function (c, i) { if (c.key === item.key && c.bundle === item.bundle) found = i; });
    if (found >= 0) {
      cart[found].qty += item.qty;
      cart[found].linePrice = cart[found].unitPrice * cart[found].qty;
      applyBundleUpgrade(cart, found);
    } else {
      cart.push(item);
      applyBundleUpgrade(cart, cart.length - 1);
    }
    saveCart(cart);
    renderSidebar();
    updateBadge();
    openCart();
  }

  function removeItem(idx) {
    var cart = getCart();
    cart.splice(idx, 1);
    saveCart(cart);
    renderSidebar();
    updateBadge();
  }

  function changeQty(idx, delta) {
    var cart = getCart();
    if (!cart[idx]) return;
    var item = cart[idx];
    var prod = PRODUCTS[item.key];

    if (!item.bundle) {
      var newQty = Math.max(1, item.qty + delta);
      if (newQty >= 6 && prod) {
        item.bundle = true;
        item.qty = 1;
        item.unitPrice = prod.bundlePrice;
        item.linePrice = prod.bundlePrice;
      } else {
        item.qty = newQty;
        item.linePrice = item.unitPrice * newQty;
      }
    } else {
      if (delta < 0 && prod) {
        item.bundle = false;
        item.qty = 5;
        item.unitPrice = prod.price;
        item.linePrice = prod.price * 5;
      } else {
        item.qty = item.qty + delta;
        if (item.qty < 1) item.qty = 1;
        item.linePrice = item.unitPrice * item.qty;
      }
    }

    saveCart(cart);
    renderSidebar();
    updateBadge();
  }

  /* ── Sidebar open/close ── */
  function openCart() {
    var s = document.getElementById('cart-sidebar');
    var o = document.getElementById('cart-overlay');
    if (s) s.classList.add('open');
    if (o) o.classList.add('open');
  }

  function closeCart() {
    var s = document.getElementById('cart-sidebar');
    var o = document.getElementById('cart-overlay');
    if (s) s.classList.remove('open');
    if (o) o.classList.remove('open');
  }

  /* ── Inject HTML ── */
  function inject() {
    var wrap = document.createElement('div');
    wrap.innerHTML =
      '<button id="cart-float-btn" class="cart-float-btn" aria-label="Cart">' +
        '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>' +
        '<span id="cart-badge" class="cart-badge">0</span>' +
      '</button>' +
      '<div id="cart-overlay" class="cart-overlay"></div>' +
      '<aside id="cart-sidebar" class="cart-sidebar" aria-label="Cart">' +
        '<div class="cart-head">' +
          '<h3 id="cart-title">Winkelwagen</h3>' +
          '<button type="button" id="cart-close" class="cart-close-btn" aria-label="Close">' +
            '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>' +
          '</button>' +
        '</div>' +
        '<div id="cart-items-list" class="cart-items-list"><p class="cart-empty">Je winkelwagen is leeg.</p></div>' +
        '<div class="cart-footer">' +
          '<div class="cart-row"><span id="cart-sub-lbl">Subtotaal</span><span id="cart-sub">€ 0,00</span></div>' +
          '<div class="cart-row"><span id="cart-ship-lbl">Verzending</span><span id="cart-ship">€ 4,95</span></div>' +
          '<div class="cart-free-note" id="cart-free-note" style="display:none">' +
            '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>' +
            '<span id="cart-free-note-text">Gratis verzending toegepast!</span>' +
          '</div>' +
          '<div class="cart-grand"><span id="cart-grand-lbl">Totaal</span><span id="cart-grand">€ 0,00</span></div>' +
          '<a href="/checkout.html" id="cart-checkout-btn" class="btn btn-red cart-afrekenen-btn">Afrekenen →</a>' +
        '</div>' +
      '</aside>';

    document.body.appendChild(wrap);

    document.getElementById('cart-float-btn').addEventListener('click', openCart);
    document.getElementById('cart-close').addEventListener('click', closeCart);
    document.getElementById('cart-overlay').addEventListener('click', closeCart);

    updateBadge();
    renderSidebar();
  }

  /* ── Public API ── */
  window.MiralCart = {
    add: addItem,
    open: openCart,
    close: closeCart,
    getCart: getCart,
    renderSidebar: renderSidebar,
    clearCart: function () { saveCart([]); renderSidebar(); updateBadge(); },
    PRODUCTS: PRODUCTS,
    fmt: fmt,
    calcTotals: calcTotals
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', inject);
  } else {
    inject();
  }
})();
