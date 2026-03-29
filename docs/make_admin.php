<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Создать администратора</title>
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: 'Segoe UI', sans-serif;
      background: #080d12;
      color: #dde8f3;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 20px;
    }
    .card {
      background: rgba(12,22,34,.95);
      border: 1px solid rgba(255,255,255,.1);
      border-radius: 20px;
      padding: 36px 32px;
      max-width: 440px;
      width: 100%;
    }
    h1 { font-size: 20px; font-weight: 600; margin-bottom: 6px; color: #fff; }
    .sub { font-size: 13px; color: #7a99b8; margin-bottom: 28px; line-height: 1.5; }
    label { display: block; font-size: 12px; font-weight: 500; color: #a8c0d6; margin-bottom: 6px; }
    input {
      width: 100%; padding: 11px 14px;
      border: 1px solid rgba(255,255,255,.12);
      border-radius: 10px;
      background: rgba(255,255,255,.06);
      color: #fff; font-size: 14px;
      margin-bottom: 16px;
      outline: none; transition: border-color .2s;
    }
    input:focus { border-color: #3d8fd4; }
    button {
      width: 100%; padding: 13px;
      border-radius: 10px; border: none;
      background: #3d8fd4; color: #fff;
      font-size: 14px; font-weight: 600;
      cursor: pointer; transition: filter .2s;
    }
    button:hover { filter: brightness(1.1); }
    button:disabled { opacity: .5; cursor: not-allowed; filter: none; }
    .result {
      margin-top: 20px; padding: 16px;
      border-radius: 10px; font-size: 13px;
      line-height: 1.7; display: none;
    }
    .result.ok  { background: rgba(41,168,106,.12); border: 1px solid rgba(41,168,106,.3); color: #6ee8a0; }
    .result.err { background: rgba(201,75,59,.12);  border: 1px solid rgba(201,75,59,.3);  color: #ff8a80; }
    .warn {
      background: rgba(212,138,26,.1);
      border: 1px solid rgba(212,138,26,.25);
      border-radius: 10px; padding: 12px 14px;
      font-size: 12px; color: #d48a1a;
      margin-bottom: 20px; line-height: 1.6;
    }
    code {
      background: rgba(255,255,255,.08);
      padding: 2px 7px; border-radius: 4px;
      font-family: monospace; font-size: 12px;
    }
  </style>
</head>
<body>
<div class="card">
  <h1>⚙️ Создать администратора</h1>
  <p class="sub">Одноразовая утилита. Удали этот файл сразу после создания аккаунта.</p>

  <div class="warn">
    ⚠️ После создания аккаунта <strong>удали этот файл</strong> с сервера.<br>
    Или он автоматически заблокируется после первого использования.
  </div>

  <?php
  require_once __DIR__ . '/db.php';
  require_once __DIR__ . '/init_db.php';

  $done    = false;
  $error   = '';
  $success = '';

  // Проверка: файл уже использован (lock-файл)
  $lockFile = __DIR__ . '/.make_admin_used';
  if (file_exists($lockFile)) {
      $error = 'Этот файл уже был использован и заблокирован. Удали make_admin.php с сервера.';
  }

  if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error) {
      $nickname = trim($_POST['nickname'] ?? '');
      $email    = trim($_POST['email']    ?? '');
      $password = $_POST['password']      ?? '';
      $confirm  = $_POST['confirm']       ?? '';
      $secret   = $_POST['secret']        ?? '';

      // Простая защита — секретное слово
      $expectedSecret = 'makeadmin2026'; // можно поменять

      if ($secret !== $expectedSecret) {
          $error = 'Неверное секретное слово';
      } elseif (strlen($nickname) < 2) {
          $error = 'Псевдоним слишком короткий';
      } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
          $error = 'Некорректный email';
      } elseif (strlen($password) < 6) {
          $error = 'Пароль минимум 6 символов';
      } elseif ($password !== $confirm) {
          $error = 'Пароли не совпадают';
      } else {
          try {
              initDatabase();
              $pdo = getDB();

              // Проверка что такой пользователь не существует
              $check = $pdo->prepare('SELECT id FROM users WHERE nickname = ? OR email = ?');
              $check->execute([$nickname, $email]);
              if ($check->fetch()) {
                  $error = 'Пользователь с таким псевдонимом или email уже существует';
              } else {
                  $hash  = password_hash($password, PASSWORD_BCRYPT);
                  $token = bin2hex(random_bytes(32));

                  $stmt = $pdo->prepare(
                      "INSERT INTO users (session_token, nickname, email, password_hash, role)
                       VALUES (?, ?, ?, ?, 'admin')"
                  );
                  $stmt->execute([$token, $nickname, $email, $hash]);
                  $id = $pdo->lastInsertId();

                  // Создаём lock-файл чтобы заблокировать повторное использование
                  file_put_contents($lockFile, date('Y-m-d H:i:s'));

                  $success = "Администратор создан!\n"
                           . "ID: $id\n"
                           . "Псевдоним: $nickname\n"
                           . "Email: $email\n"
                           . "Роль: admin\n\n"
                           . "Теперь удали файл make_admin.php с сервера!";
                  $done = true;
              }
          } catch (Exception $e) {
              $error = 'Ошибка БД: ' . $e->getMessage();
          }
      }
  }
  ?>

  <?php if (!$done): ?>
  <form method="POST">
    <label for="nickname">Псевдоним (для входа)</label>
    <input type="text" id="nickname" name="nickname"
           value="<?= htmlspecialchars($_POST['nickname'] ?? '') ?>"
           placeholder="Алексей" maxlength="50" required>

    <label for="email">Email</label>
    <input type="email" id="email" name="email"
           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
           placeholder="admin@example.com" required>

    <label for="password">Пароль</label>
    <input type="password" id="password" name="password"
           placeholder="Минимум 6 символов" required>

    <label for="confirm">Повтори пароль</label>
    <input type="password" id="confirm" name="confirm"
           placeholder="Повтори пароль" required>

    <label for="secret">Секретное слово</label>
    <input type="password" id="secret" name="secret"
           placeholder="Введи секретное слово" required>
    <p style="font-size:11px;color:#7a99b8;margin-top:-10px;margin-bottom:16px;">
      По умолчанию: <code>makeadmin2026</code> — смени в коде файла перед загрузкой
    </p>

    <button type="submit">Создать администратора</button>
  </form>
  <?php endif; ?>

  <?php if ($error): ?>
  <div class="result err" style="display:block">❌ <?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <?php if ($success): ?>
  <div class="result ok" style="display:block">
    ✅ <?= nl2br(htmlspecialchars($success)) ?>
    <br><br>
    <strong style="color:#ff8a80">🚨 Удали make_admin.php с сервера прямо сейчас!</strong>
  </div>
  <?php endif; ?>

</div>
</body>
</html>
