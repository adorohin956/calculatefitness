<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>История расчётов — Calculate Fitness</title>
  <meta name="description" content="Ваша история расчётов на calculatefitness.pro">
  <link rel="icon" href="/favicon.ico">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,300&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/style.css">
  <style>
    .hist-filters{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px;align-items:center;}
    .hist-filter-btn{padding:6px 14px;border-radius:50px;border:1px solid var(--border);background:var(--bg-card);color:var(--text-muted);font-size:12px;font-family:var(--font);cursor:pointer;transition:all var(--transition);}
    .hist-filter-btn:hover,.hist-filter-btn.active{border-color:var(--accent);background:var(--accent-dim);color:var(--text);}
    .hist-item{display:flex;align-items:center;gap:12px;padding:13px 16px;border-radius:var(--radius-sm);border:1px solid var(--border);background:var(--bg-card);margin-bottom:7px;transition:all var(--transition);}
    .hist-item:hover{background:var(--bg-card-h);}
    .hist-icon{font-size:18px;flex-shrink:0;width:24px;text-align:center;}
    .hist-body{flex:1;min-width:0;}
    .hist-name{font-size:13px;font-weight:500;color:var(--text);}
    .hist-result{font-size:12px;color:var(--accent);margin-top:2px;}
    .hist-date{font-size:11px;color:var(--text-muted);font-family:var(--font-mono);flex-shrink:0;}
    .hist-del{padding:5px 9px;border-radius:6px;border:none;background:transparent;color:var(--text-muted);cursor:pointer;font-size:13px;transition:all var(--transition);}
    .hist-del:hover{color:#ff8a80;background:rgba(201,75,59,.1);}
    .hist-open{padding:5px 10px;border-radius:6px;border:1px solid var(--border);background:transparent;color:var(--text-muted);cursor:pointer;font-size:11.5px;font-family:var(--font);text-decoration:none;transition:all var(--transition);}
    .hist-open:hover{border-color:var(--accent);color:var(--accent);}
    .empty-state{text-align:center;padding:48px 20px;color:var(--text-muted);}
    .empty-icon{font-size:40px;margin-bottom:12px;}
    .sort-bar{display:flex;gap:8px;align-items:center;margin-bottom:12px;font-size:12px;color:var(--text-muted);}
    .sort-btn{background:none;border:none;color:var(--text-muted);font-size:12px;font-family:var(--font);cursor:pointer;display:flex;align-items:center;gap:3px;}
    .sort-btn.active{color:var(--accent);}
    #clear-all-btn{padding:8px 16px;border-radius:8px;border:1px solid rgba(201,75,59,.3);background:rgba(201,75,59,.08);color:#ff8a80;font-size:12.5px;font-family:var(--font);cursor:pointer;transition:all var(--transition);}
    #clear-all-btn:hover{background:rgba(201,75,59,.18);}
  </style>
</head>
<body>
  <canvas id="bg-canvas"></canvas>
  <div class="page"><div class="card">

    <header class="calc-header">
      <h1>📊 История расчётов</h1>
      <p>Все ваши сохранённые расчёты на этом аккаунте</p>
    </header>

    <div id="auth-guard" style="display:none;text-align:center;padding:40px 0;">
      <p style="color:var(--text-muted);margin-bottom:16px;">Войдите в аккаунт чтобы увидеть историю</p>
      <button class="btn-calculate" style="background:var(--accent);max-width:200px;" onclick="App.showAuthModal()">Войти</button>
    </div>

    <div id="hist-content">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
        <div class="hist-filters" id="type-filters">
          <button class="hist-filter-btn active" onclick="filterType(null, this)">Все</button>
        </div>
        <button id="clear-all-btn" onclick="clearAll()">🗑 Очистить всё</button>
      </div>

      <div class="sort-bar">
        <span>Сортировка:</span>
        <button class="sort-btn active" id="sort-date" onclick="setSort('created_at')">По дате ↓</button>
        <button class="sort-btn" id="sort-type" onclick="setSort('calc_type')">По типу</button>
        <span style="margin-left:auto;font-family:var(--font-mono)" id="total-count"></span>
      </div>

      <div id="hist-list">
        <div class="empty-state"><div class="empty-icon">⏳</div><p>Загружаем...</p></div>
      </div>

      <div style="display:flex;justify-content:center;gap:8px;margin-top:16px;" id="pagination"></div>
    </div>

    <a href="/index.html" class="back-link">← На главную</a>
    <footer><p>© 2026 Дорохин А.О.</p></footer>
  </div></div>

  <script src="/app.js"></script>
  <script src="/bg.js"></script>
  <script>
    const ICONS = {
      calories:'🔥',bju:'🥗',bmr:'⚡',deficit:'📅',water:'💧',
      imt:'📊',bodyfat:'💪',whr:'⚖️',ideal_weight:'🎯',leanbody:'🦴',
      anthro:'📐',pulse:'❤️',maxpulse:'🫁',bio_age:'🧬',
      pro_karvonen:'❤️',pro_rufye:'🫀',pro_ortostatic:'🧍',pro_martine:'🏃',
      pro_mifflin_pro:'⚡',pro_bju_pro:'🥩',pro_weight_loss:'📉',pro_zones5:'🔥',
    };
    const NAMES = {
      calories:'Калькулятор калорий',bju:'БЖУ',bmr:'Метаболизм',
      deficit:'Дефицит калорий',water:'Норма воды',imt:'ИМТ',
      bodyfat:'% жира',whr:'Талия/Бёдра',ideal_weight:'Идеальный вес',
      leanbody:'Сухая масса',anthro:'Антропометрия',pulse:'Пульс',
      maxpulse:'Макс. пульс',bio_age:'Биологический возраст',
      pro_karvonen:'Карвонен PRO',pro_rufye:'Тест Руфье',
      pro_ortostatic:'Ортостатика',pro_martine:'Мартинэ',
      pro_mifflin_pro:'Миффлин PRO',pro_bju_pro:'БЖУ PRO',
      pro_weight_loss:'Дефицит PRO',pro_zones5:'5 зон PRO',
    };

    let currentType = null;
    let currentSort = 'created_at';
    let currentDir  = 'DESC';
    let currentOffset = 0;
    const LIMIT = 15;

    async function load() {
      const user = App.getUser();
      if (!user?.nickname && !App.getToken()) {
        document.getElementById('auth-guard').style.display = 'block';
        document.getElementById('hist-content').style.display = 'none';
        return;
      }

      try {
        const params = { limit: LIMIT, offset: currentOffset, sort: currentSort, dir: currentDir };
        if (currentType) params.type = currentType;
        const data = await App.getHistory(params);

        document.getElementById('total-count').textContent = `${data.total} записей`;
        renderFilters(data.types);
        renderList(data.items);
        renderPagination(data.total);
      } catch(e) {
        if (e.message.includes('авторизац')) {
          document.getElementById('auth-guard').style.display = 'block';
          document.getElementById('hist-content').style.display = 'none';
        }
      }
    }

    function renderFilters(types) {
      const bar = document.getElementById('type-filters');
      const existing = bar.querySelectorAll('[data-type]');
      existing.forEach(e => e.remove());
      types.forEach(t => {
        const btn = document.createElement('button');
        btn.className = 'hist-filter-btn' + (t === currentType ? ' active' : '');
        btn.textContent = (ICONS[t] || '📊') + ' ' + (NAMES[t] || t);
        btn.dataset.type = t;
        btn.onclick = () => filterType(t, btn);
        bar.appendChild(btn);
      });
    }

    function renderList(items) {
      const list = document.getElementById('hist-list');
      if (!items.length) {
        list.innerHTML = `<div class="empty-state"><div class="empty-icon">📭</div><p>Нет записей</p></div>`;
        return;
      }
      list.innerHTML = items.map(item => `
        <div class="hist-item" id="hi-${item.id}">
          <span class="hist-icon">${ICONS[item.calc_type] || '📊'}</span>
          <div class="hist-body">
            <div class="hist-name">${NAMES[item.calc_type] || item.calc_type}</div>
            <div class="hist-result">${item.result_label}</div>
          </div>
          <span class="hist-date">${formatDate(item.created_at)}</span>
          <a href="/${item.calc_type.replace('_','-').replace('pro-','pro_')}.html" class="hist-open">Открыть</a>
          <button class="hist-del" onclick="delItem(${item.id})" title="Удалить">✕</button>
        </div>`).join('');
    }

    function renderPagination(total) {
      const pages = Math.ceil(total / LIMIT);
      const cur   = Math.floor(currentOffset / LIMIT);
      const pg    = document.getElementById('pagination');
      if (pages <= 1) { pg.innerHTML = ''; return; }
      let html = '';
      for (let i = 0; i < pages; i++) {
        html += `<button class="hist-filter-btn${i===cur?' active':''}" onclick="goPage(${i})">${i+1}</button>`;
      }
      pg.innerHTML = html;
    }

    function filterType(type, btn) {
      currentType = type; currentOffset = 0;
      document.querySelectorAll('.hist-filter-btn').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      load();
    }

    function setSort(field) {
      if (currentSort === field) {
        currentDir = currentDir === 'DESC' ? 'ASC' : 'DESC';
      } else {
        currentSort = field; currentDir = 'DESC';
      }
      document.querySelectorAll('.sort-btn').forEach(b => b.classList.remove('active'));
      document.getElementById('sort-' + (field === 'created_at' ? 'date' : 'type')).classList.add('active');
      load();
    }

    function goPage(n) { currentOffset = n * LIMIT; load(); }

    async function delItem(id) {
      try {
        await App.deleteCalc(id);
        document.getElementById('hi-'+id)?.remove();
        App.showToast('Запись удалена');
        load();
      } catch(e) { App.showToast('Ошибка: ' + e.message); }
    }

    async function clearAll() {
      if (!confirm('Удалить всю историю расчётов?')) return;
      await App.clearHistory();
      App.showToast('История очищена');
      load();
    }

    function formatDate(dt) {
      const d = new Date(dt);
      return d.toLocaleDateString('ru-RU', {day:'2-digit',month:'2-digit',year:'2-digit'})
           + ' ' + d.toLocaleTimeString('ru-RU', {hour:'2-digit',minute:'2-digit'});
    }

    document.addEventListener('DOMContentLoaded', () => {
      // Ждём инициализации сессии
      setTimeout(load, 300);
    });
  </script>
</body>
</html>
