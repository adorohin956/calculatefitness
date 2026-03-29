<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Дневник замеров — Calculate Fitness</title>
  <meta name="description" content="Дневник замеров тела — отслеживайте прогресс">
  <link rel="icon" href="/favicon.ico">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,300&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/style.css">
  <style>
    .stat-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:8px;margin-bottom:20px;}
    .chart-wrap{background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius-md);padding:20px;margin-bottom:16px;}
    .chart-title{font-size:11px;font-weight:500;letter-spacing:1.5px;text-transform:uppercase;color:var(--text-muted);margin-bottom:12px;}
    .measure-table{width:100%;border-collapse:collapse;font-size:13px;}
    .measure-table th{text-align:left;padding:10px 12px;border-bottom:1px solid var(--border);font-size:11px;font-weight:500;letter-spacing:1px;text-transform:uppercase;color:var(--text-muted);}
    .measure-table td{padding:10px 12px;border-bottom:1px solid var(--border);color:var(--text-mid);}
    .measure-table tr:last-child td{border-bottom:none;}
    .measure-table tr:hover td{background:var(--bg-card-h);}
    .del-row{padding:4px 8px;border:none;background:transparent;color:var(--text-muted);cursor:pointer;border-radius:4px;font-size:12px;}
    .del-row:hover{color:#ff8a80;background:rgba(201,75,59,.1);}
    .period-btn{padding:6px 14px;border-radius:50px;border:1px solid var(--border);background:var(--bg-card);color:var(--text-muted);font-size:12px;font-family:var(--font);cursor:pointer;transition:all var(--transition);}
    .period-btn.active,.period-btn:hover{border-color:var(--accent);background:var(--accent-dim);color:var(--text);}
    .metric-btn{padding:5px 12px;border-radius:6px;border:1px solid var(--border);background:var(--bg-card);color:var(--text-muted);font-size:11.5px;font-family:var(--font);cursor:pointer;transition:all var(--transition);}
    .metric-btn.active{border-color:var(--accent);background:var(--accent-dim);color:var(--text);}
    .change-pos{color:#29a86a;font-size:11px;}
    .change-neg{color:#c94b3b;font-size:11px;}
  </style>
</head>
<body>
  <canvas id="bg-canvas"></canvas>
  <div class="page"><div class="card">

    <header class="calc-header">
      <h1>📏 Дневник замеров</h1>
      <p>Отслеживайте изменения тела в динамике</p>
    </header>

    <div id="auth-guard" style="display:none;text-align:center;padding:40px 0;">
      <p style="color:var(--text-muted);margin-bottom:16px;">Войдите в аккаунт чтобы вести дневник</p>
      <button class="btn-calculate" style="background:var(--accent);max-width:220px;" onclick="App.showAuthModal()">Войти / Создать аккаунт</button>
    </div>

    <div id="diary-content">

      <!-- Форма добавления замера -->
      <div class="info-block" style="margin-bottom:20px;">
        <h3>➕ Добавить замер</h3>
        <div class="results-grid" style="grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:8px;margin-top:12px;">
          <div class="form-group" style="margin:0">
            <label for="m-date">Дата</label>
            <input type="date" id="m-date" style="width:100%">
          </div>
          <div class="form-group" style="margin:0">
            <label for="m-weight">Вес (кг)</label>
            <input type="number" id="m-weight" placeholder="75.5" step="0.1" min="30" max="300">
          </div>
          <div class="form-group" style="margin:0">
            <label for="m-fat">% жира</label>
            <input type="number" id="m-fat" placeholder="20.0" step="0.1" min="3" max="60">
          </div>
          <div class="form-group" style="margin:0">
            <label for="m-waist">Талия (см)</label>
            <input type="number" id="m-waist" placeholder="82" step="0.5">
          </div>
          <div class="form-group" style="margin:0">
            <label for="m-hips">Бёдра (см)</label>
            <input type="number" id="m-hips" placeholder="98" step="0.5">
          </div>
          <div class="form-group" style="margin:0">
            <label for="m-chest">Грудь (см)</label>
            <input type="number" id="m-chest" placeholder="95" step="0.5">
          </div>
        </div>
        <div class="form-group" style="margin-top:8px;">
          <label for="m-notes">Заметка</label>
          <input type="text" id="m-notes" placeholder="Например: после тренировки" maxlength="200" style="width:100%">
        </div>
        <button class="btn-calculate" style="background:var(--accent);margin-top:12px;" onclick="addMeasurement()">Сохранить замер</button>
        <div class="field-error" id="measure-err"></div>
      </div>

      <!-- Статистика -->
      <div id="stats-row" class="stat-row" style="display:none"></div>

      <!-- График -->
      <div class="chart-wrap" id="chart-section" style="display:none">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;flex-wrap:wrap;gap:8px;">
          <p class="chart-title" style="margin:0">Динамика</p>
          <div style="display:flex;gap:6px;flex-wrap:wrap;">
            <div style="display:flex;gap:4px;" id="metric-btns">
              <button class="metric-btn active" data-metric="weight" onclick="setMetric('weight',this)">Вес</button>
              <button class="metric-btn" data-metric="body_fat" onclick="setMetric('body_fat',this)">% жира</button>
              <button class="metric-btn" data-metric="waist" onclick="setMetric('waist',this)">Талия</button>
            </div>
            <div style="display:flex;gap:4px;" id="period-btns">
              <button class="period-btn active" onclick="setPeriod(30,this)">30 дней</button>
              <button class="period-btn" onclick="setPeriod(90,this)">3 мес</button>
              <button class="period-btn" onclick="setPeriod(365,this)">Год</button>
              <button class="period-btn" onclick="setPeriod(0,this)">Всё</button>
            </div>
          </div>
        </div>
        <canvas id="measureChart" height="200"></canvas>
      </div>

      <!-- Таблица -->
      <div id="table-section" style="display:none">
        <p class="result-title">Все замеры</p>
        <div style="overflow-x:auto;">
          <table class="measure-table" id="measure-table">
            <thead>
              <tr>
                <th>Дата</th><th>Вес</th><th>% жира</th>
                <th>Талия</th><th>Бёдра</th><th>Грудь</th>
                <th>Заметка</th><th></th>
              </tr>
            </thead>
            <tbody id="measure-tbody"></tbody>
          </table>
        </div>
      </div>

      <div id="empty-diary" style="display:none" class="empty-state">
        <div class="empty-icon">📏</div>
        <p>Добавьте первый замер чтобы начать отслеживать прогресс</p>
      </div>

    </div><!-- /diary-content -->

    <a href="/index.html" class="back-link">← На главную</a>
    <footer><p>© 2026 Дорохин А.О.</p></footer>
  </div></div>

  <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>
  <script src="/app.js"></script>
  <script src="/bg.js"></script>
  <script>
    let allData = [];
    let chart   = null;
    let currentMetric = 'weight';
    let currentDays   = 30;

    const METRIC_LABELS = {
      weight:'Вес (кг)', body_fat:'% жира', waist:'Талия (см)',
      hips:'Бёдра (см)', chest:'Грудь (см)'
    };
    const METRIC_COLORS = {
      weight:'#3d8fd4', body_fat:'#c94b3b', waist:'#d48a1a',
      hips:'#a06bc8', chest:'#29a86a'
    };

    // Установить сегодняшнюю дату по умолчанию
    document.getElementById('m-date').value = new Date().toISOString().split('T')[0];

    async function load() {
      const user = App.getUser();
      if (!user) {
        setTimeout(load, 400);
        return;
      }
      if (!App.getToken()) {
        document.getElementById('auth-guard').style.display = 'block';
        document.getElementById('diary-content').style.display = 'none';
        return;
      }

      try {
        const data = await App.getMeasurements({ limit: 365 });
        allData = data.items;
        renderStats(data.stats);
        renderTable(allData);
        renderChart();
      } catch(e) {
        if (e.message.includes('авторизац')) {
          document.getElementById('auth-guard').style.display = 'block';
          document.getElementById('diary-content').style.display = 'none';
        }
      }
    }

    function renderStats(stats) {
      if (!stats.total || stats.total === '0') {
        document.getElementById('empty-diary').style.display = 'block';
        return;
      }
      document.getElementById('empty-diary').style.display = 'none';
      document.getElementById('chart-section').style.display = 'block';
      document.getElementById('table-section').style.display = 'block';
      document.getElementById('stats-row').style.display = 'grid';

      const change = stats.weight_change;
      const changeHtml = change !== null
        ? `<span class="${change < 0 ? 'change-pos' : change > 0 ? 'change-neg' : ''}">${change > 0 ? '+' : ''}${change} кг</span>`
        : '';

      document.getElementById('stats-row').innerHTML = `
        <div class="result-card"><h4>Замеров</h4><span class="result-value">${stats.total}</span></div>
        <div class="result-card"><h4>Вес мин</h4><span class="result-value" style="color:#29a86a">${stats.weight_min || '—'}</span><span class="unit">кг</span></div>
        <div class="result-card"><h4>Вес макс</h4><span class="result-value" style="color:#c94b3b">${stats.weight_max || '—'}</span><span class="unit">кг</span></div>
        <div class="result-card"><h4>Изменение</h4><span class="result-value" style="font-size:18px">${changeHtml || '—'}</span></div>`;
    }

    function getFilteredData() {
      if (!currentDays) return allData;
      const cutoff = new Date();
      cutoff.setDate(cutoff.getDate() - currentDays);
      return allData.filter(r => new Date(r.measured_at) >= cutoff);
    }

    function renderChart() {
      const filtered = getFilteredData();
      const points   = filtered.filter(r => r[currentMetric] !== null);
      if (!points.length) return;

      const labels = points.map(r => {
        const d = new Date(r.measured_at);
        return d.toLocaleDateString('ru-RU', {day:'2-digit',month:'2-digit'});
      });
      const values = points.map(r => parseFloat(r[currentMetric]));
      const color  = METRIC_COLORS[currentMetric];

      if (chart) chart.destroy();
      chart = new Chart(document.getElementById('measureChart'), {
        type: 'line',
        data: {
          labels,
          datasets: [{
            label: METRIC_LABELS[currentMetric],
            data: values,
            borderColor: color,
            backgroundColor: color + '18',
            borderWidth: 2,
            pointBackgroundColor: color,
            pointRadius: 4,
            tension: 0.3,
            fill: true,
          }]
        },
        options: {
          responsive: true,
          plugins: {
            legend: { display: false },
            tooltip: {
              backgroundColor: '#0e1c2a',
              borderColor: color,
              borderWidth: 1,
              titleColor: '#dde8f3',
              bodyColor: color,
            }
          },
          scales: {
            x: { ticks: { color: '#7a99b8', font: { size: 11 } }, grid: { color: '#ffffff0a' } },
            y: { ticks: { color: '#7a99b8', font: { size: 11 } }, grid: { color: '#ffffff0a' } }
          }
        }
      });
    }

    function renderTable(data) {
      const tbody = document.getElementById('measure-tbody');
      const reversed = [...data].reverse();
      tbody.innerHTML = reversed.map(r => `
        <tr id="mr-${r.id}">
          <td style="font-family:var(--font-mono);font-size:12px">${r.measured_at}</td>
          <td>${r.weight ?? '—'}</td>
          <td>${r.body_fat ?? '—'}</td>
          <td>${r.waist ?? '—'}</td>
          <td>${r.hips ?? '—'}</td>
          <td>${r.chest ?? '—'}</td>
          <td style="font-size:12px;color:var(--text-muted)">${r.notes ?? ''}</td>
          <td><button class="del-row" onclick="delRow(${r.id})">✕</button></td>
        </tr>`).join('');
    }

    async function addMeasurement() {
      const data = {
        measured_at: document.getElementById('m-date').value,
        weight:      document.getElementById('m-weight').value || null,
        body_fat:    document.getElementById('m-fat').value    || null,
        waist:       document.getElementById('m-waist').value  || null,
        hips:        document.getElementById('m-hips').value   || null,
        chest:       document.getElementById('m-chest').value  || null,
        notes:       document.getElementById('m-notes').value  || null,
      };

      const errEl = document.getElementById('measure-err');
      errEl.classList.remove('visible');

      if (!data.measured_at) {
        errEl.innerHTML = '⚠️ Выберите дату'; errEl.classList.add('visible'); return;
      }
      if (!data.weight && !data.body_fat && !data.waist && !data.hips && !data.chest) {
        errEl.innerHTML = '⚠️ Заполните хотя бы одно поле'; errEl.classList.add('visible'); return;
      }

      try {
        await App.addMeasurement(data);
        App.showToast('✓ Замер сохранён');
        // Очищаем числовые поля
        ['m-weight','m-fat','m-waist','m-hips','m-chest','m-notes'].forEach(id => {
          document.getElementById(id).value = '';
        });
        load();
      } catch(e) {
        errEl.innerHTML = '⚠️ ' + e.message; errEl.classList.add('visible');
      }
    }

    async function delRow(id) {
      try {
        await App.deleteMeasurement(id);
        document.getElementById('mr-'+id)?.remove();
        allData = allData.filter(r => r.id !== id);
        renderChart();
        App.showToast('Замер удалён');
      } catch(e) { App.showToast('Ошибка: ' + e.message); }
    }

    function setMetric(metric, btn) {
      currentMetric = metric;
      document.querySelectorAll('.metric-btn').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      renderChart();
    }

    function setPeriod(days, btn) {
      currentDays = days;
      document.querySelectorAll('.period-btn').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      renderChart();
    }

    document.addEventListener('DOMContentLoaded', () => {
      setTimeout(load, 300);
    });
  </script>
</body>
</html>
