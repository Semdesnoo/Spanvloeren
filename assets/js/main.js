/* ==========================================================
   SPANVLOEREN.NL – MAIN JS
   Nav + Footer injection, interactions, i18n init
   ========================================================== */

/* ── NAV HTML ─────────────────────────────────────────────── */
const NAV_HTML = `
<header class="site-header at-top" id="site-header">

  <div class="nav-topbar">
    <div class="wrap">
      <div class="nav-topbar-inner">

        <!-- Left: contact links -->
        <div class="nav-topbar-left">
          <a href="tel:+31683296802" class="nav-topbar-link" aria-label="Bel ons">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 13a19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 3.6 2.18h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
            06 832 968 02
          </a>
          <span class="nav-topbar-sep"></span>
          <a href="mailto:info@spanvloeren.nl" class="nav-topbar-link" aria-label="E-mail ons">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
            info@spanvloeren.nl
          </a>
        </div>

        <!-- Right: lang switcher -->
        <div class="lang-switcher" role="group" aria-label="Taal kiezen">
          <button class="lang-btn" data-lang="nl" onclick="setLang('nl')">NL</button>
          <span class="lang-sep">|</span>
          <button class="lang-btn" data-lang="de" onclick="setLang('de')">DE</button>
          <span class="lang-sep">|</span>
          <button class="lang-btn" data-lang="en" onclick="setLang('en')">EN</button>
        </div>

      </div>
    </div>
  </div>

  <nav class="nav" id="nav">
    <div class="wrap">
      <div class="nav-inner">
        <a href="/" class="logo" aria-label="Spanvloeren">Span<span>vloeren</span></a>

        <ul class="nav-links" role="list">
          <li class="has-drop">
            <button class="drop-btn" aria-expanded="false" aria-haspopup="true">
              <span data-i18n="nav.sportvloeren">Sportvloeren</span>
              <svg width="10" height="6" viewBox="0 0 10 6" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 1l4 4 4-4"/></svg>
            </button>
            <ul class="dropdown" role="list">
              <li><a href="/sportvloeren/spanvloeren.html" data-i18n="nav.spanvloeren">Spanvloeren</a></li>
              <li><a href="/sportvloeren/tatami.html" data-i18n="nav.tatami">Tatami puzzelmatten</a></li>
              <li><a href="/sportvloeren/caster-pavisport.html" data-i18n="nav.caster">Caster Pavisport</a></li>
              <li><a href="/sportvloeren/foam.html" data-i18n="nav.foam">Foam puzzelmatten</a></li>
              <li><a href="/sportvloeren/polyester-canvas.html" data-i18n="nav.polyester">Polyester canvas</a></li>
              <li><a href="/sportvloeren/rubber.html" data-i18n="nav.rubber">Rubber vloeren</a></li>
            </ul>
          </li>

          <li><a href="/boksringen.html" data-i18n="nav.boksringen">Boksringen</a></li>
          <li><a href="/producten.html" data-i18n="nav.producten">Producten</a></li>

          <li class="has-drop">
            <button class="drop-btn" aria-expanded="false" aria-haspopup="true">
              <span data-i18n="nav.referenties">Referenties</span>
              <svg width="10" height="6" viewBox="0 0 10 6" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 1l4 4 4-4"/></svg>
            </button>
            <ul class="dropdown" role="list">
              <li><a href="/referenties/nederland.html" data-i18n="nav.nederland">Nederland</a></li>
              <li><a href="/referenties/belgie.html" data-i18n="nav.belgie">België</a></li>
              <li><a href="/referenties/duitsland.html" data-i18n="nav.duitsland">Duitsland</a></li>
              <li><a href="/referenties/curacao.html" data-i18n="nav.curacao">Curaçao</a></li>
            </ul>
          </li>

          <li><a href="/contact.html" data-i18n="nav.contact">Contact</a></li>
        </ul>

        <div class="nav-right">
          <a href="/contact.html" class="btn btn-red nav-cta" data-i18n="nav.cta">Offerte Aanvragen</a>
        </div>

        <button class="hamburger" id="hamburger" aria-label="Menu" aria-expanded="false" aria-controls="mobile-menu">
          <span></span><span></span><span></span>
        </button>
      </div>
    </div>
  </nav>

</header>

<div class="mobile-menu" id="mobile-menu" role="navigation" aria-label="Mobiel menu">
  <div class="mob-drop">
    <button class="mob-drop-btn" aria-expanded="false">
      <span data-i18n="nav.sportvloeren">Sportvloeren</span>
      <svg width="10" height="6" viewBox="0 0 10 6" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 1l4 4 4-4"/></svg>
    </button>
    <div class="mob-drop-menu">
      <a href="/sportvloeren/spanvloeren.html" data-i18n="nav.spanvloeren">Spanvloeren</a>
      <a href="/sportvloeren/tatami.html" data-i18n="nav.tatami">Tatami puzzelmatten</a>
      <a href="/sportvloeren/caster-pavisport.html" data-i18n="nav.caster">Caster Pavisport</a>
      <a href="/sportvloeren/foam.html" data-i18n="nav.foam">Foam puzzelmatten</a>
      <a href="/sportvloeren/polyester-canvas.html" data-i18n="nav.polyester">Polyester canvas</a>
      <a href="/sportvloeren/rubber.html" data-i18n="nav.rubber">Rubber vloeren</a>
    </div>
  </div>

  <a href="/boksringen.html" data-i18n="nav.boksringen">Boksringen</a>
  <a href="/producten.html" data-i18n="nav.producten">Producten</a>

  <div class="mob-drop">
    <button class="mob-drop-btn" aria-expanded="false">
      <span data-i18n="nav.referenties">Referenties</span>
      <svg width="10" height="6" viewBox="0 0 10 6" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 1l4 4 4-4"/></svg>
    </button>
    <div class="mob-drop-menu">
      <a href="/referenties/nederland.html" data-i18n="nav.nederland">Nederland</a>
      <a href="/referenties/belgie.html" data-i18n="nav.belgie">België</a>
      <a href="/referenties/duitsland.html" data-i18n="nav.duitsland">Duitsland</a>
      <a href="/referenties/curacao.html" data-i18n="nav.curacao">Curaçao</a>
    </div>
  </div>

  <a href="/contact.html" data-i18n="nav.contact">Contact</a>

  <div class="mob-lang">
    <button class="lang-btn" data-lang="nl" onclick="setLang('nl')">NL</button>
    <button class="lang-btn" data-lang="de" onclick="setLang('de')">DE</button>
    <button class="lang-btn" data-lang="en" onclick="setLang('en')">EN</button>
  </div>

  <a href="/contact.html" class="btn btn-red" data-i18n="nav.cta">Offerte Aanvragen</a>
</div>
`;

/* ── FOOTER HTML ──────────────────────────────────────────── */
const FOOTER_HTML = `
<footer class="footer">
  <div class="footer-top-bar"></div>

  <!-- Main grid -->
  <div class="wrap footer-main">
    <div class="footer-grid">

      <!-- Brand -->
      <div class="footer-brand">
        <a href="/" class="logo footer-logo">Span<span>vloeren</span></a>
        <p class="footer-tagline" data-i18n="footer.tagline">De specialist in professionele sportvloeren voor vechtsport. Al 45+ jaar de betrouwbare keuze voor gyms en clubs.</p>
        <a href="/contact.html" class="footer-offerte-btn" data-i18n="footer.ctaBtn">Gratis offerte aanvragen →</a>
        <div class="footer-badges">
          <span class="footer-badge">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
            <span data-i18n="footer.badge1">Levering door heel Nederland</span>
          </span>
          <span class="footer-badge">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
            <span data-i18n="footer.badge2">Vakkundige montage</span>
          </span>
          <span class="footer-badge">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
            <span data-i18n="footer.badge3">Eigen productie sinds 1978</span>
          </span>
        </div>
      </div>

      <!-- Producten -->
      <nav class="footer-col" aria-label="Producten">
        <h5 data-i18n="footer.colProducts">Producten</h5>
        <ul>
          <li><a href="/sportvloeren/spanvloeren.html" data-i18n="nav.spanvloeren">Spanvloeren</a></li>
          <li><a href="/sportvloeren/tatami.html" data-i18n="nav.tatami">Tatami matten</a></li>
          <li><a href="/sportvloeren/caster-pavisport.html" data-i18n="nav.caster">Caster Pavisport</a></li>
          <li><a href="/sportvloeren/foam.html" data-i18n="nav.foam">Foam matten</a></li>
          <li><a href="/sportvloeren/rubber.html" data-i18n="nav.rubber">Rubber vloeren</a></li>
          <li><a href="/boksringen.html" data-i18n="nav.boksringen">Boksringen</a></li>
          <li><a href="/producten.html" data-i18n="nav.producten">Miral producten</a></li>
        </ul>
      </nav>

      <!-- Informatie -->
      <nav class="footer-col" aria-label="Informatie">
        <h5 data-i18n="footer.colInfo">Informatie</h5>
        <ul>
          <li><a href="/" data-i18n="nav.home">Home</a></li>
          <li><a href="/referenties/nederland.html" data-i18n="nav.referenties">Referenties</a></li>
          <li><a href="/contact.html" data-i18n="nav.contact">Contact</a></li>
        </ul>
      </nav>

      <!-- Contact -->
      <div class="footer-col footer-contact-col">
        <h5 data-i18n="footer.colContact">Neem contact op</h5>
        <div class="footer-contact-items">
          <a href="tel:+31683296802" class="footer-contact-item">
            <span class="footer-contact-icon">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12 19.79 19.79 0 0 1 1.61 3.4 2 2 0 0 1 3.6 1.22h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.91 8.82a16 16 0 0 0 6.29 6.29l.96-.96a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
            </span>
            <span class="footer-contact-text">
              <strong data-i18n="footer.callUs">Bel ons</strong>
              06 832 968 02
            </span>
          </a>
          <a href="mailto:info@spanvloeren.nl" class="footer-contact-item">
            <span class="footer-contact-icon">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
            </span>
            <span class="footer-contact-text">
              <strong data-i18n="footer.mailUs">Mail ons</strong>
              info@spanvloeren.nl
            </span>
          </a>
        </div>
      </div>

    </div>
  </div>

  <!-- Bottom bar -->
  <div class="footer-bottom-wrap">
    <div class="wrap footer-bottom">
      <p data-i18n="footer.copy">© 2026 Alle rechten voorbehouden. Spanvloeren.nl</p>
      <div class="footer-kvk">
        <span>KvK Pro Budo 2000: <strong>63875535</strong></span>
        <span class="footer-kvk-sep">·</span>
        <span>KvK Miral: <strong>42007192</strong></span>
      </div>
    </div>
  </div>

</footer>
<div class="float-cta" id="float-cta" aria-hidden="true">
  <a href="/contact.html" class="btn btn-red" data-i18n="nav.cta">Offerte Aanvragen</a>
</div>
`;

/* ── INJECT ───────────────────────────────────────────────── */
function injectNav() {
  const ph = document.getElementById('nav-placeholder');
  if (ph) { ph.outerHTML = NAV_HTML; }
  else { document.body.insertAdjacentHTML('afterbegin', NAV_HTML); }
}

function injectFooter() {
  const ph = document.getElementById('footer-placeholder');
  if (ph) { ph.outerHTML = FOOTER_HTML; }
  else { document.body.insertAdjacentHTML('beforeend', FOOTER_HTML); }
}

/* ── NAV SCROLL & ACTIVE ─────────────────────────────────── */
function initNav() {
  const header   = document.getElementById('site-header');
  const nav      = document.getElementById('nav');
  const floatCta = document.getElementById('float-cta');
  const isSubpage = document.body.classList.contains('subpage');

  function setScrollState(scrolled) {
    // Toggle on both elements so CSS selectors work regardless
    header?.classList.toggle('at-top', !scrolled);
    header?.classList.toggle('scrolled', scrolled);
    nav?.classList.toggle('at-top', !scrolled);
    nav?.classList.toggle('scrolled', scrolled);
  }

  // Set initial scroll state on all pages
  setScrollState(window.scrollY >= 60);

  window.addEventListener('scroll', () => {
    const y = window.scrollY;
    setScrollState(y >= 60);
    if (floatCta) {
      const show = y > window.innerHeight * 0.5;
      floatCta.classList.toggle('show', show);
      floatCta.setAttribute('aria-hidden', String(!show));
    }
  }, { passive: true });

  // Active nav link highlight
  const path = window.location.pathname;
  document.querySelectorAll('.nav-links a, .dropdown a').forEach(a => {
    try {
      const url = new URL(a.href, window.location.origin);
      if (url.pathname === path) a.style.color = 'var(--red)';
    } catch (_) {}
  });

  // Hamburger
  const burger = document.getElementById('hamburger');
  const menu   = document.getElementById('mobile-menu');
  if (burger && menu) {
    burger.addEventListener('click', () => {
      const open = menu.classList.toggle('open');
      burger.classList.toggle('open', open);
      burger.setAttribute('aria-expanded', String(open));
      document.body.style.overflow = open ? 'hidden' : '';
    });
    menu.querySelectorAll('a').forEach(a => {
      a.addEventListener('click', () => {
        menu.classList.remove('open');
        burger.classList.remove('open');
        burger.setAttribute('aria-expanded', 'false');
        document.body.style.overflow = '';
      });
    });
    document.addEventListener('keydown', e => {
      if (e.key === 'Escape' && menu.classList.contains('open')) {
        menu.classList.remove('open');
        burger.classList.remove('open');
        burger.setAttribute('aria-expanded', 'false');
        document.body.style.overflow = '';
        burger.focus();
      }
    });
  }

  // Mobile sub-dropdowns
  document.querySelectorAll('.mob-drop-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      const drop = btn.closest('.mob-drop');
      const open = drop.classList.toggle('open');
      btn.setAttribute('aria-expanded', String(open));
    });
  });
}

/* ── COUNTER ANIMATION ───────────────────────────────────── */
function initCounters() {
  const els = document.querySelectorAll('[data-count]');
  if (!els.length) return;
  let done = false;
  const obs = new IntersectionObserver(entries => {
    entries.forEach(e => {
      if (e.isIntersecting && !done) {
        done = true;
        els.forEach(el => {
          const target = parseInt(el.dataset.count);
          let val = 0;
          const step = target / (1600 / 16);
          const timer = setInterval(() => {
            val = Math.min(val + step, target);
            el.textContent = Math.floor(val);
            if (val >= target) clearInterval(timer);
          }, 16);
        });
      }
    });
  }, { threshold: 0.4 });
  obs.observe(els[0].closest('section, .hero, .impact') || els[0]);
}

/* ── SCROLL REVEAL ───────────────────────────────────────── */
function initReveal() {
  const obs = new IntersectionObserver(entries => {
    entries.forEach(e => {
      if (e.isIntersecting) { e.target.classList.add('visible'); obs.unobserve(e.target); }
    });
  }, { threshold: 0.08, rootMargin: '0px 0px -28px 0px' });
  document.querySelectorAll('.reveal').forEach(el => obs.observe(el));
}

/* ── FAQ ACCORDION ───────────────────────────────────────── */
function initFAQ() {
  document.querySelectorAll('.faq-item').forEach(item => {
    const btn  = item.querySelector('.faq-btn');
    const body = item.querySelector('.faq-body');
    if (!btn || !body) return;
    btn.addEventListener('click', () => {
      const open = item.classList.contains('open');
      document.querySelectorAll('.faq-item').forEach(i => {
        i.classList.remove('open');
        i.querySelector('.faq-btn')?.setAttribute('aria-expanded','false');
        const b = i.querySelector('.faq-body');
        if (b) b.style.maxHeight = '0';
      });
      if (!open) {
        item.classList.add('open');
        btn.setAttribute('aria-expanded','true');
        body.style.maxHeight = body.scrollHeight + 'px';
      }
    });
  });
}

/* ── CONTACT FORM ────────────────────────────────────────── */
function initForm() {
  const form = document.getElementById('sv-form');
  const ok   = document.getElementById('form-ok');
  if (!form || !ok) return;
  form.addEventListener('submit', e => {
    e.preventDefault();
    let valid = true;
    form.querySelectorAll('[required]').forEach(el => {
      if (!el.value.trim()) {
        el.style.borderColor = 'var(--red)';
        el.addEventListener('input', () => { el.style.borderColor = ''; }, { once: true });
        valid = false;
      }
    });
    if (!valid) return;
    const btn = form.querySelector('[type=submit]');
    btn.disabled = true;
    const orig = btn.innerHTML;
    btn.textContent = '...';
    setTimeout(() => {
      form.reset();
      btn.disabled = false;
      btn.innerHTML = orig;
      ok.style.display = 'block';
      ok.focus();
      setTimeout(() => { ok.style.display = 'none'; }, 6000);
    }, 1100);
  });
}

/* ── TIME SLOTS ──────────────────────────────────────────── */
function initTimeSlots() {
  document.querySelectorAll('.time-slot').forEach(slot => {
    slot.addEventListener('click', () => {
      document.querySelectorAll('.time-slot').forEach(s => s.classList.remove('selected'));
      slot.classList.add('selected');
    });
  });
}

/* ── SMOOTH SCROLL ───────────────────────────────────────── */
function initSmoothScroll() {
  document.querySelectorAll('a[href^="#"]').forEach(a => {
    a.addEventListener('click', e => {
      const t = document.querySelector(a.getAttribute('href'));
      if (t) {
        e.preventDefault();
        window.scrollTo({ top: t.getBoundingClientRect().top + window.scrollY - 76, behavior: 'smooth' });
      }
    });
  });
}

/* ── REVIEW SLIDER ──────────────────────────────────────── */
function initReviewSlider() {
  var outer = document.querySelector('.review-slider-outer');
  if (!outer) return;
  var track = outer.querySelector('.review-track');
  var cards = Array.from(outer.querySelectorAll('.review-card'));
  var prevBtn = outer.querySelector('.review-arrow--prev');
  var nextBtn = outer.querySelector('.review-arrow--next');
  var dotsWrap = outer.querySelector('.review-dots');
  var GAP = 18;
  var current = 0;
  var autoTimer = null;
  var dots = [];

  function getVisible() {
    return window.innerWidth <= 480 ? 1 : window.innerWidth <= 768 ? 2 : 3;
  }
  function maxIdx() { return Math.max(0, cards.length - getVisible()); }
  function stepPx() { return cards[0].offsetWidth + GAP; }

  function buildDots() {
    dotsWrap.innerHTML = '';
    dots = [];
    for (var i = 0; i <= maxIdx(); i++) {
      var d = document.createElement('button');
      d.type = 'button';
      d.className = 'review-dot';
      d.setAttribute('aria-label', 'Slide ' + (i + 1));
      (function(idx) {
        d.addEventListener('click', function() { stopAuto(); goTo(idx); startAuto(); });
      })(i);
      dotsWrap.appendChild(d);
      dots.push(d);
    }
  }

  function goTo(n) {
    current = Math.max(0, Math.min(n, maxIdx()));
    track.style.transform = 'translateX(-' + (current * stepPx()) + 'px)';
    dots.forEach(function(d, i) { d.classList.toggle('active', i === current); });
    prevBtn.disabled = current === 0;
    nextBtn.disabled = current === maxIdx();
  }

  function next() { goTo(current >= maxIdx() ? 0 : current + 1); }
  function prev() { goTo(current - 1); }
  function startAuto() { stopAuto(); autoTimer = setInterval(next, 4000); }
  function stopAuto() { if (autoTimer) { clearInterval(autoTimer); autoTimer = null; } }

  prevBtn.addEventListener('click', function() { stopAuto(); prev(); startAuto(); });
  nextBtn.addEventListener('click', function() { stopAuto(); next(); startAuto(); });

  var touchStartX = 0;
  track.addEventListener('touchstart', function(e) { touchStartX = e.touches[0].clientX; stopAuto(); }, { passive: true });
  track.addEventListener('touchend', function(e) {
    var dx = e.changedTouches[0].clientX - touchStartX;
    if (Math.abs(dx) > 40) { dx < 0 ? next() : prev(); }
    startAuto();
  }, { passive: true });

  var resizeTimer;
  window.addEventListener('resize', function() {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(function() { buildDots(); goTo(Math.min(current, maxIdx())); }, 150);
  });

  buildDots();
  goTo(0);
  startAuto();
}

/* ── INIT ────────────────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', () => {
  injectNav();
  injectFooter();

  const lang = getLang();
  applyI18n(lang);
  document.documentElement.lang = lang;
  document.querySelectorAll('.lang-btn').forEach(b => b.classList.toggle('active', b.dataset.lang === lang));

  initNav();
  initCounters();
  initReveal();
  initFAQ();
  initForm();
  initTimeSlots();
  initSmoothScroll();
  initReviewSlider();
});
