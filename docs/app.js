/**
 * app.js — клиентский SDK для работы с API
 * Подключается на всех страницах сайта.
 * Управляет: сессией, историей, замерами, отзывами.
 */

const App = (() => {

  const API = '/api';
  const LS  = {
    TOKEN:   'cf_session_token',
    USER:    'cf_user',
  };

  // ── Сессия ───────────────────────────────────────────────────────────────

  function getToken() {
    return localStorage.getItem(LS.TOKEN);
  }

  function getUser() {
    const u = localStorage.getItem(LS.USER);
    return u ? JSON.parse(u) : null;
  }

  function setSession(token, user) {
    localStorage.setItem(LS.TOKEN, token);
    localStorage.setItem(LS.USER, JSON.stringify(user));
  }

  function clearSession() {
    localStorage.removeItem(LS.TOKEN);
    localStorage.removeItem(LS.USER);
  }

  // ── HTTP-запросы ──────────────────────────────────────────────────────────

  async function req(method, path, body = null) {
    const opts = {
      method,
      headers: { 'Content-Type': 'application/json' },
    };
    const token = getToken();
    if (token) opts.headers['X-Session-Token'] = token;
    if (body)  opts.body = JSON.stringify(body);

    try {
      const res  = await fetch(API + path, opts);
      const data = await res.json();
      if (!data.ok) throw new Error(data.error || 'Ошибка запроса');
      return data.data;
    } catch (e) {
      console.warn('API error:', e.message);
      throw e;
    }
  }

  const get    = (path)        => req('GET',    path);
  const post   = (path, body)  => req('POST',   path, body);
  const put    = (path, body)  => req('PUT',    path, body);
  const del    = (path)        => req('DELETE', path);

  // ── Инициализация сессии ──────────────────────────────────────────────────

  async function initSession() {
    if (getToken()) {
      // Проверяем что токен ещё валиден
      try {
        const user = await get('/user.php');
        localStorage.setItem(LS.USER, JSON.stringify(user));
        return user;
      } catch {
        clearSession();
      }
    }
    // Создаём гостевую сессию
    const res = await post('/user.php?action=guest');
    setSession(res.session_token, res.user);
    return res.user;
  }

  // ── Аутентификация ────────────────────────────────────────────────────────

  async function register(nickname, password, email = '') {
    const res = await post('/user.php?action=register', { nickname, password, email });
    if (res.session_token) {
      setSession(res.session_token, res);
    } else {
      localStorage.setItem(LS.USER, JSON.stringify(res));
    }
    renderUserWidget();
    return res;
  }

  async function login(login, password) {
    const res = await post('/user.php?action=login', { login, password });
    setSession(res.session_token, res.user);
    renderUserWidget();
    return res.user;
  }

  async function logout() {
    await post('/user.php?action=logout').catch(() => {});
    clearSession();
    await initSession(); // создаём новую гостевую сессию
    renderUserWidget();
  }

  // ── Расчёты ───────────────────────────────────────────────────────────────

  async function saveCalc(calcType, params, result, resultLabel) {
    try {
      await post('/calculations.php', { calc_type: calcType, params, result, result_label: resultLabel });
    } catch (e) {
      console.warn('Не удалось сохранить расчёт:', e.message);
    }
  }

  async function getHistory(options = {}) {
    const params = new URLSearchParams(options).toString();
    return get('/calculations.php' + (params ? '?' + params : ''));
  }

  async function deleteCalc(id) {
    return del(`/calculations.php?id=${id}`);
  }

  async function clearHistory() {
    return del('/calculations.php?all=1');
  }

  // ── Замеры ────────────────────────────────────────────────────────────────

  async function getMeasurements(options = {}) {
    const params = new URLSearchParams(options).toString();
    return get('/measurements.php' + (params ? '?' + params : ''));
  }

  async function addMeasurement(data) {
    return post('/measurements.php', data);
  }

  async function updateMeasurement(id, data) {
    return put(`/measurements.php?id=${id}`, data);
  }

  async function deleteMeasurement(id) {
    return del(`/measurements.php?id=${id}`);
  }

  // ── Отзывы ────────────────────────────────────────────────────────────────

  async function getReviews(calcType) {
    return get(`/reviews.php?calc_type=${calcType}`);
  }

  async function saveReview(calcType, rating, comment = '') {
    return post('/reviews.php', { calc_type: calcType, rating, comment });
  }

  async function getReviewsSummary() {
    return get('/reviews.php?summary=1');
  }

  // ── Заявки ────────────────────────────────────────────────────────────────

  async function sendContact(name, phone, goal, message) {
    return post('/contacts.php', { name, phone, goal, message });
  }

  // ── Виджет пользователя ───────────────────────────────────────────────────

  function renderUserWidget() {
    const container = document.getElementById('user-widget');
    if (!container) return;

    const user = getUser();
    const isGuest = !user?.nickname;

    if (isGuest) {
      container.innerHTML = `
        <div class="uw-guest" id="uw-guest">
          <button class="uw-btn" onclick="App.showAuthModal()">
            👤 Войти / Создать аккаунт
          </button>
        </div>`;
    } else {
      container.innerHTML = `
        <div class="uw-logged">
          <span class="uw-name">👤 ${escHtml(user.nickname)}</span>
          <a href="/history.php" class="uw-link">📊 История</a>
          <a href="/diary.php"   class="uw-link">📏 Дневник</a>
          <button class="uw-btn-sm" onclick="App.logout()">Выйти</button>
        </div>`;
    }
  }

  // ── Модальное окно авторизации ────────────────────────────────────────────

  function showAuthModal(mode = 'login') {
    // Удаляем существующий модал
    document.getElementById('auth-modal')?.remove();

    const modal = document.createElement('div');
    modal.id = 'auth-modal';
    modal.className = 'auth-modal-backdrop';
    modal.innerHTML = `
      <div class="auth-modal-card" role="dialog" aria-modal="true" onclick="event.stopPropagation()">
        <button class="auth-modal-close" onclick="document.getElementById('auth-modal').remove()">✕</button>

        <div class="auth-tabs">
          <button class="auth-tab ${mode==='login'?'active':''}"    onclick="App.showAuthModal('login')">Войти</button>
          <button class="auth-tab ${mode==='register'?'active':''}" onclick="App.showAuthModal('register')">Создать аккаунт</button>
        </div>

        <div id="auth-error" class="auth-error" style="display:none"></div>

        ${mode === 'login' ? `
          <div class="form-group">
            <label for="auth-login">Псевдоним или Email</label>
            <input type="text" id="auth-login" placeholder="Алексей или alex@mail.ru" autocomplete="username">
          </div>
          <div class="form-group">
            <label for="auth-pass">Пароль</label>
            <input type="password" id="auth-pass" placeholder="Пароль" autocomplete="current-password">
          </div>
          <button class="auth-submit" onclick="App._doLogin()">Войти</button>
        ` : `
          <div class="form-group">
            <label for="auth-nick">Псевдоним <span style="color:#ff6b6b">*</span></label>
            <input type="text" id="auth-nick" placeholder="Алексей" maxlength="50" autocomplete="username">
          </div>
          <div class="form-group">
            <label for="auth-pass">Пароль <span style="color:#ff6b6b">*</span></label>
            <input type="password" id="auth-pass" placeholder="Минимум 6 символов" autocomplete="new-password">
          </div>
          <div class="form-group">
            <label for="auth-email">Email <span style="color:var(--text-muted)">(не обязательно)</span></label>
            <input type="email" id="auth-email" placeholder="alex@mail.ru" autocomplete="email">
          </div>
          <button class="auth-submit" onclick="App._doRegister()">Создать аккаунт</button>
        `}
      </div>`;

    modal.addEventListener('click', e => {
      if (e.target === modal) modal.remove();
    });

    document.body.appendChild(modal);
    requestAnimationFrame(() => modal.classList.add('open'));

    // Focus trap — Tab/Shift+Tab cycle within modal
    function trapFocus(e) {
      if (e.key !== 'Tab') return;
      const focusable = modal.querySelectorAll(
        'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
      );
      if (!focusable.length) return;
      const first = focusable[0];
      const last  = focusable[focusable.length - 1];
      if (e.shiftKey) {
        if (document.activeElement === first) { e.preventDefault(); last.focus(); }
      } else {
        if (document.activeElement === last) { e.preventDefault(); first.focus(); }
      }
    }
    modal.addEventListener('keydown', trapFocus);

    // Release trap when modal is removed from DOM
    const observer = new MutationObserver(() => {
      if (!document.contains(modal)) {
        modal.removeEventListener('keydown', trapFocus);
        observer.disconnect();
      }
    });
    observer.observe(document.body, { childList: true, subtree: true });

    // Enter для отправки формы
    modal.addEventListener('keydown', e => {
      if (e.key === 'Enter') {
        mode === 'login' ? _doLogin() : _doRegister();
      }
    });

    // Фокус на первое поле
    setTimeout(() => {
      modal.querySelector('input')?.focus();
    }, 100);
  }

  async function _doLogin() {
    const loginVal = document.getElementById('auth-login')?.value.trim();
    const passVal  = document.getElementById('auth-pass')?.value;
    if (!loginVal || !passVal) return showAuthError('Заполните все поля');

    try {
      setAuthLoading(true);
      await login(loginVal, passVal);
      document.getElementById('auth-modal')?.remove();
      showToast('✓ Добро пожаловать, ' + (getUser()?.nickname || '') + '!');
    } catch (e) {
      showAuthError(e.message);
    } finally {
      setAuthLoading(false);
    }
  }

  async function _doRegister() {
    const nick  = document.getElementById('auth-nick')?.value.trim();
    const pass  = document.getElementById('auth-pass')?.value;
    const email = document.getElementById('auth-email')?.value.trim();
    if (!nick || !pass) return showAuthError('Псевдоним и пароль обязательны');

    try {
      setAuthLoading(true);
      await register(nick, pass, email);
      document.getElementById('auth-modal')?.remove();
      showToast('✓ Аккаунт создан! Добро пожаловать, ' + nick + '!');
    } catch (e) {
      showAuthError(e.message);
    } finally {
      setAuthLoading(false);
    }
  }

  function showAuthError(msg) {
    const el = document.getElementById('auth-error');
    if (el) { el.textContent = '⚠️ ' + msg; el.style.display = 'block'; }
  }

  function setAuthLoading(on) {
    const btn = document.querySelector('.auth-submit');
    if (btn) { btn.disabled = on; btn.textContent = on ? 'Подождите...' : btn.dataset.label || btn.textContent; }
  }

  // ── Toast-уведомление ────────────────────────────────────────────────────

  function showToast(msg, duration = 3000) {
    document.getElementById('app-toast')?.remove();
    const t = document.createElement('div');
    t.id = 'app-toast';
    t.textContent = msg;
    t.style.cssText = `
      position:fixed;bottom:24px;left:50%;transform:translateX(-50%) translateY(80px);
      background:rgba(20,35,52,.96);border:1px solid rgba(41,168,106,.4);
      color:#a8d8ff;padding:12px 20px;border-radius:50px;font-size:13px;font-weight:600;
      z-index:9999;box-shadow:0 8px 32px rgba(0,0,0,.5);backdrop-filter:blur(12px);
      transition:transform .4s cubic-bezier(.36,1.56,.64,1),opacity .4s;opacity:0;
      font-family:var(--font,'DM Sans',sans-serif);white-space:nowrap;
    `;
    document.body.appendChild(t);
    requestAnimationFrame(() => {
      t.style.transform = 'translateX(-50%) translateY(0)';
      t.style.opacity = '1';
    });
    setTimeout(() => {
      t.style.opacity = '0';
      t.style.transform = 'translateX(-50%) translateY(80px)';
      setTimeout(() => t.remove(), 400);
    }, duration);
  }

  // ── Утилиты ───────────────────────────────────────────────────────────────

  function escHtml(str) {
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }

  // ── Валидация полей ────────────────────────────────────────────────────────

  function validateRequiredFields() {
    const inputs = document.querySelectorAll('.card input[type="number"]');
    let valid = true;

    inputs.forEach(input => {
      const empty = input.value.trim() === '';
      let errEl = input.parentElement.querySelector('.field-error');
      if (empty) {
        if (!errEl) {
          errEl = document.createElement('span');
          errEl.className = 'field-error';
          errEl.textContent = 'Обязательное поле';
          input.parentElement.appendChild(errEl);
        }
        errEl.classList.add('visible');
        valid = false;
      } else if (errEl) {
        errEl.classList.remove('visible');
      }
    });

    return valid;
  }

  // Live removal of field errors on input
  document.addEventListener('input', e => {
    if (
      e.target.matches('.card input[type="number"]') &&
      e.target.value.trim() !== ''
    ) {
      const errEl = e.target.parentElement.querySelector('.field-error.visible');
      if (errEl) errEl.classList.remove('visible');
    }
  });

  // ── Автоинициализация ─────────────────────────────────────────────────────

  document.addEventListener('DOMContentLoaded', async () => {
    await initSession();
    renderUserWidget();
  });

  // ── Публичный API ─────────────────────────────────────────────────────────

  return {
    getUser, getToken, initSession,
    register, login, logout,
    saveCalc, getHistory, deleteCalc, clearHistory,
    getMeasurements, addMeasurement, updateMeasurement, deleteMeasurement,
    getReviews, saveReview, getReviewsSummary,
    sendContact,
    showAuthModal, showToast, renderUserWidget,
    validateFields: validateRequiredFields,
    _doLogin, _doRegister,
  };

})();
