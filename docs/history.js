/* history.js — сохраняет последние 5 расчётов в localStorage */
(function () {
  const KEY = 'calc_history';
  const MAX = 5;

  function getHistory() {
    try {
      return JSON.parse(localStorage.getItem(KEY) || '[]');
    } catch (e) { return []; }
  }

  function saveToHistory(entry) {
    // entry: { calc, label, result, date, params, href }
    try {
      const history = getHistory().filter(h => h.calc !== entry.calc);
      history.unshift(entry);
      localStorage.setItem(KEY, JSON.stringify(history.slice(0, MAX)));
    } catch (e) {}
  }

  function clearHistory() {
    try { localStorage.removeItem(KEY); } catch (e) {}
  }

  function formatDate() {
    const d = new Date();
    return d.getDate().toString().padStart(2,'0') + '.' + (d.getMonth()+1).toString().padStart(2,'0');
  }

  // Auto-save when results appear on calc pages
  // Called from each calc page after calculation
  window.HistoryModule = { getHistory, saveToHistory, clearHistory, formatDate };

  // Render history block on main page
  window.renderHistory = function(containerId) {
    const container = document.getElementById(containerId);
    if (!container) return;
    const history = getHistory();
    if (!history.length) {
      container.style.display = 'none';
      return;
    }
    container.style.display = 'block';

    const PAGE_ICONS = {
      calories:'🔥', bju:'🥗', bmr:'⚡', deficit:'📅', water:'💧',
      imt:'📊', bodyfat:'💪', whr:'⚖️', ideal_weight:'🎯', leanbody:'🦴',
      anthro:'📐', pulse:'❤️', maxpulse:'🫁',
      pro_karvonen:'❤️', pro_rufye:'🫀', pro_ortostatic:'🧍',
      pro_martine:'🏃', pro_mifflin_pro:'⚡', pro_bju_pro:'🥩',
      pro_whr_pro:'📏', pro_weight_loss:'📉', pro_zones5:'🔥',
    };

    container.innerHTML = `
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;">
        <p style="font-size:11px;font-weight:500;letter-spacing:2px;text-transform:uppercase;color:var(--text-muted);">Последние расчёты</p>
        <button onclick="clearHistoryUI()" style="font-size:11px;color:var(--text-muted);background:none;border:none;cursor:pointer;font-family:var(--font);padding:2px 6px;border-radius:4px;transition:color .2s;" onmouseover="this.style.color='#ff8a80'" onmouseout="this.style.color=''">Очистить</button>
      </div>
      <div style="display:flex;flex-direction:column;gap:7px;">
        ${history.map(h => `
          <a href="${h.href || h.calc + '.html'}${h.params ? '?' + new URLSearchParams(h.params).toString() : ''}"
             style="display:flex;align-items:center;gap:12px;padding:12px 15px;border-radius:var(--radius-sm);border:1px solid var(--border);background:var(--bg-card);text-decoration:none;color:var(--text-mid);transition:all .2s;"
             onmouseover="this.style.background='var(--bg-card-h)';this.style.borderColor='var(--border-h)';this.style.color='var(--text)'"
             onmouseout="this.style.background='var(--bg-card)';this.style.borderColor='var(--border)';this.style.color='var(--text-mid)'">
            <span style="font-size:16px;flex-shrink:0;">${PAGE_ICONS[h.calc] || '📊'}</span>
            <span style="flex:1;min-width:0;">
              <span style="font-size:12.5px;font-weight:500;display:block;margin-bottom:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${h.label}</span>
              <span style="font-size:11px;color:var(--text-muted);">${h.result}</span>
            </span>
            <span style="font-size:10.5px;color:var(--text-muted);flex-shrink:0;font-family:var(--font-mono);">${h.date}</span>
          </a>`).join('')}
      </div>`;
  };

  window.clearHistoryUI = function() {
    clearHistory();
    const container = document.getElementById('history-block');
    if (container) container.style.display = 'none';
  };
})();
