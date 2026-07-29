/* AIESEC in Deutschland — main.js */

/* ── Smooth scroll for ALL anchor links ── */
document.querySelectorAll('a[href^="#"]').forEach(a => {
  a.addEventListener('click', function (e) {
    const id = this.getAttribute('href');
    if (id.length < 2) return;
    const target = document.querySelector(id);
    if (target) {
      e.preventDefault();
      target.scrollIntoView({ behavior: 'smooth', block: 'start' });
      // Close mobile menu if open
      hamburger.classList.remove('open');
      navLinks.classList.remove('open');
      hamburger.setAttribute('aria-expanded', 'false');
      document.body.style.overflow = '';
    }
  });
});

/* ── Navbar scroll effect + scroll progress bar ── */
const navbar = document.getElementById('navbar');
const progress = document.getElementById('scrollProgress');
function onScroll() {
  const y = window.scrollY;
  navbar.classList.toggle('scrolled', y > 20);
  if (progress) {
    const h = document.documentElement.scrollHeight - window.innerHeight;
    progress.style.width = (h > 0 ? (y / h) * 100 : 0) + '%';
  }
}
window.addEventListener('scroll', onScroll, { passive: true });
onScroll();

/* ── Hamburger menu ── */
const hamburger = document.getElementById('hamburger');
const navLinks = document.getElementById('navLinks');
hamburger.addEventListener('click', () => {
  const open = hamburger.classList.toggle('open');
  navLinks.classList.toggle('open', open);
  hamburger.setAttribute('aria-expanded', open ? 'true' : 'false');
  document.body.style.overflow = open ? 'hidden' : '';
});

/* ── Scroll reveal animations ── */
const observer = new IntersectionObserver((entries) => {
  entries.forEach(e => {
    if (e.isIntersecting) {
      const delay = e.target.style.animationDelay || '0s';
      setTimeout(() => e.target.classList.add('in-view'), parseFloat(delay) * 1000);
      observer.unobserve(e.target);
    }
  });
}, { threshold: 0.1, rootMargin: '0px 0px -40px 0px' });
document.querySelectorAll('.animate-up, .animate-left, .animate-right')
  .forEach(el => observer.observe(el));

/* ── Stats counter animation ── */
function formatNum(n) {
  if (n >= 1000000) return (n / 1000000).toFixed(n % 1000000 === 0 ? 0 : 1) + 'M';
  if (n >= 1000) return Math.round(n / 1000) + 'K';
  return Math.round(n).toString();
}
function animateCounter(el) {
  const target = +el.dataset.target;
  const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  if (reduce) { el.textContent = formatNum(target); return; }
  const duration = 1800;
  const start = performance.now();
  const easeOut = t => 1 - Math.pow(1 - t, 3);
  const tick = (now) => {
    const p = Math.min((now - start) / duration, 1);
    el.textContent = formatNum(target * easeOut(p));
    if (p < 1) requestAnimationFrame(tick);
    else {
      el.textContent = formatNum(target);
      el.classList.add('counted');
      const card = el.closest('.stat-card');
      if (card) { card.classList.add('glow'); setTimeout(() => card.classList.remove('glow'), 900); }
    }
  };
  requestAnimationFrame(tick);
}
const statsObserver = new IntersectionObserver((entries) => {
  entries.forEach(e => {
    if (e.isIntersecting) {
      e.target.querySelectorAll('.stat-num').forEach(animateCounter);
      statsObserver.unobserve(e.target);
    }
  });
}, { threshold: 0.25 });
const statsGrid = document.querySelector('.stats-grid');
if (statsGrid) statsObserver.observe(statsGrid);

/* ── Event tabs (cosmetic) ── */
document.querySelectorAll('.tab-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
  });
});

/* ════════════ PREMIUM INTERACTION LAYER ════════════ */
const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const finePointer = window.matchMedia('(hover: hover) and (pointer: fine)').matches;

/* Cursor-follow spotlight + subtle 3D tilt on cards */
(() => {
  const sel = '.stat-card,.feature-card,.event-card,.package-card,.project-card,.story-card,.partner-logo-card';
  const cards = document.querySelectorAll(sel);
  cards.forEach(c => c.classList.add('fx-card'));
  if (reduceMotion || !finePointer) return;
  const MAX = 6; // max tilt in degrees
  cards.forEach(card => {
    let raf = null, px = .5, py = .5;
    card.addEventListener('pointermove', e => {
      const r = card.getBoundingClientRect();
      px = (e.clientX - r.left) / r.width;
      py = (e.clientY - r.top) / r.height;
      card.style.setProperty('--mx', (px * 100) + '%');
      card.style.setProperty('--my', (py * 100) + '%');
      if (raf) return;
      raf = requestAnimationFrame(() => {
        const rx = (0.5 - py) * MAX;
        const ry = (px - 0.5) * MAX;
        card.classList.add('is-fx');
        card.style.transform = `perspective(900px) rotateX(${rx}deg) rotateY(${ry}deg) translateY(-6px)`;
        raf = null;
      });
    });
    card.addEventListener('pointerleave', () => {
      card.classList.remove('is-fx');
      card.style.transform = '';
    });
  });
})();

/* Magnetic buttons */
if (!reduceMotion && finePointer) {
  document.querySelectorAll('.btn-primary, .btn-white, .btn-outline-white, .btn-white-outline').forEach(btn => {
    const STRENGTH = 0.3, CAP = 7;
    btn.addEventListener('pointermove', e => {
      const r = btn.getBoundingClientRect();
      let x = (e.clientX - r.left - r.width / 2) * STRENGTH;
      let y = (e.clientY - r.top - r.height / 2) * STRENGTH;
      x = Math.max(-CAP, Math.min(CAP, x));
      y = Math.max(-CAP, Math.min(CAP, y));
      btn.style.transform = `translate(${x}px, ${y - 2}px)`;
    });
    btn.addEventListener('pointerleave', () => { btn.style.transform = ''; });
  });
}

/* Subtle hero pointer-parallax (stays within the bg overscan) */
if (!reduceMotion && finePointer) {
  const hero = document.querySelector('.hero');
  const heroBg = document.querySelector('.hero-bg');
  if (hero && heroBg) {
    let raf = null;
    hero.addEventListener('pointermove', e => {
      if (raf) return;
      raf = requestAnimationFrame(() => {
        const r = hero.getBoundingClientRect();
        const dx = ((e.clientX - r.left) / r.width - 0.5) * -22;
        const dy = ((e.clientY - r.top) / r.height - 0.5) * -22;
        heroBg.style.translate = `${dx}px ${dy}px`;
        raf = null;
      });
    });
    hero.addEventListener('pointerleave', () => { heroBg.style.translate = ''; });
  }
}

/* ── Contact form (Web3Forms) ── */
const form = document.getElementById('contactForm');
const submitBtn = document.getElementById('submitBtn');
const formSuccess = document.getElementById('formSuccess');
if (form) {
  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const original = submitBtn.innerHTML;
    submitBtn.textContent = 'Wird gesendet...';
    submitBtn.disabled = true;
    try {
      const res = await fetch('https://api.web3forms.com/submit', {
        method: 'POST',
        body: new FormData(form)
      });
      const data = await res.json();
      if (data.success) {
        formSuccess.classList.add('visible');
        form.reset();
        submitBtn.textContent = '✓ Erfolgreich gesendet!';
        submitBtn.style.background = '#00C49A';
      } else throw new Error('Failed');
    } catch {
      submitBtn.textContent = 'Fehler – bitte erneut versuchen';
      submitBtn.disabled = false;
      setTimeout(() => {
        submitBtn.innerHTML = original;
        submitBtn.disabled = false;
      }, 3000);
    }
  });
}

/* ── YouTube facade: load the real player only on click (no file:// embed errors) ── */
document.querySelectorAll('.video-facade').forEach((el) => {
  const load = (e) => {
    // let the Datenschutz link in the consent notice work without loading the player
    if (e && e.target.closest && e.target.closest('a')) return;
    if (el.dataset.loaded) return;
    el.dataset.loaded = '1';
    const id = el.dataset.video;
    const start = (el.dataset.start && el.dataset.start !== '0') ? '&start=' + el.dataset.start : '';
    const iframe = document.createElement('iframe');
    iframe.src = 'https://www.youtube-nocookie.com/embed/' + id + '?autoplay=1&rel=0&controls=1&playsinline=1' + start;
    iframe.title = el.getAttribute('aria-label') || 'Video';
    iframe.allow = 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share';
    iframe.allowFullscreen = true;
    iframe.setAttribute('referrerpolicy', 'strict-origin-when-cross-origin');
    el.innerHTML = '';
    el.appendChild(iframe);
  };
  el.addEventListener('click', load);
  el.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); load(); }
  });
});

/* ════════════ HIGH-END POLISH LAYER (additive) ════════════ */
/* Film grain overlay (skipped for reduced-motion via CSS display:none too) */
(() => {
  if (reduceMotion) return;
  const grain = document.createElement('div');
  grain.className = 'grain-overlay';
  grain.setAttribute('aria-hidden', 'true');
  document.body.appendChild(grain);
})();

/* Back-to-top button */
(() => {
  const btn = document.createElement('button');
  btn.className = 'to-top';
  btn.type = 'button';
  btn.setAttribute('aria-label', 'Nach oben scrollen');
  btn.innerHTML = '<svg viewBox="0 0 24 24"><path d="M12 19V5"/><path d="m5 12 7-7 7 7"/></svg>';
  document.body.appendChild(btn);
  btn.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));
  let ticking = false;
  window.addEventListener('scroll', () => {
    if (ticking) return;
    ticking = true;
    requestAnimationFrame(() => {
      btn.classList.toggle('show', window.scrollY > 600);
      ticking = false;
    });
  }, { passive: true });
})();

/* Partner logo wall: staggered reveal on scroll */
(() => {
  const cards = document.querySelectorAll('.partners-logos .partner-logo-card');
  if (!cards.length) return;
  cards.forEach((c, i) => {
    c.classList.add('animate-up');
    c.style.animationDelay = (i * 0.05) + 's';
  });
  const obs = new IntersectionObserver((entries) => {
    entries.forEach(e => {
      if (e.isIntersecting) {
        const d = parseFloat(e.target.style.animationDelay) || 0;
        setTimeout(() => e.target.classList.add('in-view'), d * 1000);
        obs.unobserve(e.target);
      }
    });
  }, { threshold: 0.1, rootMargin: '0px 0px -30px 0px' });
  cards.forEach(c => obs.observe(c));
})();

/* ── Text shine: one-pass light sweep when a heading first enters view ── */
/* (heading text-shine removed — moved the effect to the hero headline only) */

/* ── Hero content scroll-fade (cinematic depart on scroll) ── */
(() => {
  if (reduceMotion) return;
  const hc = document.querySelector('.hero .hero-content');
  if (!hc) return;
  let raf = null;
  window.addEventListener('scroll', () => {
    if (raf) return;
    raf = requestAnimationFrame(() => {
      const y = window.scrollY, vh = window.innerHeight;
      if (y < vh) {
        hc.style.opacity = String(Math.max(0, 1 - y / (vh * 0.72)));
        hc.style.transform = 'translateY(' + (y * 0.22) + 'px)';
      }
      raf = null;
    });
  }, { passive: true });
})();

/* ── Standorte pills: staggered reveal ── */
(() => {
  const pills = document.querySelectorAll('.locations-grid span');
  if (!pills.length) return;
  pills.forEach((s, i) => { s.classList.add('animate-up'); s.style.animationDelay = (i * 0.028) + 's'; });
  const obs = new IntersectionObserver((entries) => {
    entries.forEach(e => {
      if (e.isIntersecting) {
        const d = parseFloat(e.target.style.animationDelay) || 0;
        setTimeout(() => e.target.classList.add('in-view'), d * 1000);
        obs.unobserve(e.target);
      }
    });
  }, { threshold: 0.1, rootMargin: '0px 0px -30px 0px' });
  pills.forEach(s => obs.observe(s));
})();
