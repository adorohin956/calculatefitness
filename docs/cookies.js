/* cookies.js — автосохранение полей форм через localStorage
   Работает локально (file://) и на хостинге одинаково.
   Публичный API не изменился — все остальные файлы совместимы. */
(function () {

  /* ── Утилиты localStorage ── */
  const Store = {
    set(key, value) {
      try { localStorage.setItem(key, value); } catch(e) {}
    },
    get(key) {
      try { return localStorage.getItem(key); } catch(e) { return null; }
    },
    remove(key) {
      try { localStorage.removeItem(key); } catch(e) {}
    },
    keys() {
      try { return Object.keys(localStorage); } catch(e) { return []; }
    }
  };

  const page = location.pathname.split('/').pop().replace('.html', '') || 'main';

  function saveField(el) {
    if (!el.id && !el.name) return;
    if (el.type === 'radio') {
      if (el.checked) Store.set('fz_' + page + '_' + el.name, el.value);
    } else if (el.type === 'checkbox') {
      Store.set('fz_' + page + '_' + el.id, el.checked ? '1' : '0');
    } else {
      if (el.id) Store.set('fz_' + page + '_' + el.id, el.value);
    }
  }

  function restoreFields() {
    document.querySelectorAll('input[type="number"], select').forEach(el => {
      if (!el.id) return;
      const val = Store.get('fz_' + page + '_' + el.id);
      if (val !== null && val !== '') el.value = val;
    });
    const radioNames = new Set();
    document.querySelectorAll('input[type="radio"]').forEach(el => radioNames.add(el.name));
    radioNames.forEach(name => {
      const saved = Store.get('fz_' + page + '_' + name);
      if (saved !== null) {
        const target = document.querySelector('input[type="radio"][name="' + name + '"][value="' + saved + '"]');
        if (target) {
          target.checked = true;
          target.dispatchEvent(new Event('change', { bubbles: true }));
        }
      }
    });
  }

  function attachListeners() {
    document.querySelectorAll('input[type="number"], select, input[type="radio"], input[type="checkbox"]')
      .forEach(el => {
        el.addEventListener('change', () => saveField(el));
        if (el.type === 'number') el.addEventListener('input', () => saveField(el));
      });
  }

  function showToast() {
    const hasSaved = Store.keys().some(k => k.startsWith('fz_' + page + '_'));
    if (!hasSaved) return;
    const toast = document.createElement('div');
    toast.innerHTML = '<span>💾 Данные восстановлены</span><button id="st-clear" title="Очистить">✕</button>';
    toast.style.cssText = 'position:fixed;bottom:24px;left:50%;transform:translateX(-50%) translateY(80px);background:rgba(20,35,52,.96);border:1px solid rgba(52,152,219,.4);color:#a8d8ff;padding:12px 18px;border-radius:50px;font-size:.82rem;font-weight:600;display:flex;align-items:center;gap:12px;z-index:9999;box-shadow:0 8px 32px rgba(0,0,0,.5);backdrop-filter:blur(12px);transition:transform .4s cubic-bezier(.36,1.56,.64,1),opacity .4s;opacity:0;';
    document.body.appendChild(toast);
    requestAnimationFrame(() => { toast.style.transform = 'translateX(-50%) translateY(0)'; toast.style.opacity = '1'; });
    document.getElementById('st-clear').addEventListener('click', () => {
      clearPageData();
      toast.style.opacity = '0'; toast.style.transform = 'translateX(-50%) translateY(80px)';
      setTimeout(() => toast.remove(), 400);
      document.querySelectorAll('input[type="number"]').forEach(el => el.value = '');
      document.querySelectorAll('select').forEach(el => el.selectedIndex = 0);
    });
    setTimeout(() => {
      if (toast.parentNode) { toast.style.opacity = '0'; toast.style.transform = 'translateX(-50%) translateY(80px)'; setTimeout(() => toast.remove(), 400); }
    }, 4000);
  }

  function clearPageData() {
    Store.keys().filter(k => k.startsWith('fz_' + page + '_')).forEach(k => Store.remove(k));
  }

  function clearAllData() {
    Store.keys().filter(k => k.startsWith('fz_')).forEach(k => Store.remove(k));
  }

  function init() { restoreFields(); attachListeners(); showToast(); }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else { init(); }

  window.HealthStorage = { Store, clearPageData, clearAllData };
  window.HealthCookies = window.HealthStorage; // обратная совместимость

})();
