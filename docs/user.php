<?php
/**
 * api/user.php — управление пользователями
 *
 * GET  /api/user.php              — получить текущего пользователя
 * POST /api/user.php?action=guest — создать гостевую сессию
 * POST /api/user.php?action=register — регистрация (nickname + password [+ email])
 * POST /api/user.php?action=login    — вход (nickname/email + password)
 * POST /api/user.php?action=logout   — выход (сброс сессии)
 * PUT  /api/user.php              — обновить профиль
 * DELETE /api/user.php            — удалить аккаунт
 */

require_once __DIR__ . '/helpers.php';

$action = $_GET['action'] ?? '';
$method = getMethod();

// ── GET: получить текущего пользователя ───────────────────────────────────
if ($method === 'GET') {
    $user = getCurrentUser();
    if (!$user) jsonError('Не авторизован', 401);
    jsonSuccess(safeUser($user));
}

// ── POST ──────────────────────────────────────────────────────────────────
if ($method === 'POST') {
    $body = getBody();

    // Создать гостевую сессию
    if ($action === 'guest') {
        $token = bin2hex(random_bytes(32));
        $pdo   = getDB();
        $stmt  = $pdo->prepare(
            'INSERT INTO users (session_token, role) VALUES (?, "user")'
        );
        $stmt->execute([$token]);
        $id = $pdo->lastInsertId();
        $user = $pdo->prepare('SELECT * FROM users WHERE id = ?');
        $user->execute([$id]);
        jsonSuccess(['session_token' => $token, 'user' => safeUser($user->fetch())], 201);
    }

    // Регистрация
    if ($action === 'register') {
        $nickname = clean($body['nickname'] ?? '');
        $password = $body['password'] ?? '';
        $email    = clean($body['email'] ?? '');

        // Валидация
        if (strlen($nickname) < 2 || strlen($nickname) > 50)
            jsonError('Псевдоним: от 2 до 50 символов');
        if (!preg_match('/^[a-zA-Zа-яёА-ЯЁ0-9_\-\.]+$/u', $nickname))
            jsonError('Псевдоним: только буквы, цифры, _ - .');
        if (strlen($password) < 6)
            jsonError('Пароль: минимум 6 символов');
        if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL))
            jsonError('Некорректный email');

        $pdo = getDB();

        // Проверка уникальности
        $check = $pdo->prepare(
            'SELECT id FROM users WHERE nickname = ? OR (email IS NOT NULL AND email = ?)'
        );
        $check->execute([$nickname, $email ?: null]);
        if ($check->fetch()) jsonError('Псевдоним или email уже заняты');

        $hash  = password_hash($password, PASSWORD_BCRYPT);
        $token = $_SERVER['HTTP_X_SESSION_TOKEN'] ?? null;

        if ($token) {
            // Гость регистрируется — обновляем существующего пользователя
            $stmt = $pdo->prepare(
                'UPDATE users SET nickname=?, email=?, password_hash=?
                 WHERE session_token=?'
            );
            $stmt->execute([$nickname, $email ?: null, $hash, $token]);
            $user = $pdo->prepare('SELECT * FROM users WHERE session_token=?');
            $user->execute([$token]);
            jsonSuccess(safeUser($user->fetch()));
        } else {
            // Новый пользователь
            $newToken = bin2hex(random_bytes(32));
            $stmt = $pdo->prepare(
                'INSERT INTO users (session_token, nickname, email, password_hash)
                 VALUES (?, ?, ?, ?)'
            );
            $stmt->execute([$newToken, $nickname, $email ?: null, $hash]);
            $id = $pdo->lastInsertId();
            $user = $pdo->prepare('SELECT * FROM users WHERE id=?');
            $user->execute([$id]);
            jsonSuccess(['session_token' => $newToken, 'user' => safeUser($user->fetch())], 201);
        }
    }

    // Вход
    if ($action === 'login') {
        $login    = clean($body['login'] ?? '');    // nickname или email
        $password = $body['password'] ?? '';
        $guestToken = $_SERVER['HTTP_X_SESSION_TOKEN'] ?? null;

        if (!$login || !$password) jsonError('Введите псевдоним/email и пароль');

        $pdo  = getDB();
        $stmt = $pdo->prepare(
            'SELECT * FROM users WHERE nickname = ? OR email = ? LIMIT 1'
        );
        $stmt->execute([$login, $login]);
        $user = $stmt->fetch();

        if (!$user || !$user['password_hash'])
            jsonError('Неверный псевдоним/email или пароль');
        if (!password_verify($password, $user['password_hash']))
            jsonError('Неверный псевдоним/email или пароль');

        // Перенос данных гостя на аккаунт
        if ($guestToken && $guestToken !== $user['session_token']) {
            $guest = $pdo->prepare('SELECT id FROM users WHERE session_token=?');
            $guest->execute([$guestToken]);
            $guestRow = $guest->fetch();
            if ($guestRow) {
                // Переносим расчёты и замеры гостя
                foreach (['calculations','measurements','reviews'] as $table) {
                    $pdo->prepare("UPDATE `$table` SET user_id=? WHERE user_id=?")
                        ->execute([$user['id'], $guestRow['id']]);
                }
                // Удаляем гостевой аккаунт
                $pdo->prepare('DELETE FROM users WHERE id=?')
                    ->execute([$guestRow['id']]);
            }
        }

        jsonSuccess(['session_token' => $user['session_token'], 'user' => safeUser($user)]);
    }

    // Выход — просто сообщаем фронту, реальное состояние в localStorage
    if ($action === 'logout') {
        jsonSuccess(['message' => 'Выход выполнен']);
    }
}

// ── PUT: обновить профиль ─────────────────────────────────────────────────
if ($method === 'PUT') {
    $user = requireUser();
    $body = getBody();

    $fields = [];
    $params = [];

    if (isset($body['nickname'])) {
        $nick = clean($body['nickname']);
        if (strlen($nick) < 2 || strlen($nick) > 50) jsonError('Псевдоним: от 2 до 50 символов');
        $fields[] = 'nickname = ?';
        $params[] = $nick;
    }
    if (isset($body['email'])) {
        $email = clean($body['email']);
        if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) jsonError('Некорректный email');
        $fields[] = 'email = ?';
        $params[] = $email ?: null;
    }
    if (isset($body['password']) && strlen($body['password']) >= 6) {
        $fields[] = 'password_hash = ?';
        $params[] = password_hash($body['password'], PASSWORD_BCRYPT);
    }

    if (empty($fields)) jsonError('Нечего обновлять');

    $params[] = $user['id'];
    $pdo = getDB();
    $pdo->prepare('UPDATE users SET ' . implode(', ', $fields) . ' WHERE id = ?')
        ->execute($params);

    $updated = $pdo->prepare('SELECT * FROM users WHERE id = ?');
    $updated->execute([$user['id']]);
    jsonSuccess(safeUser($updated->fetch()));
}

// ── DELETE: удалить аккаунт ───────────────────────────────────────────────
if ($method === 'DELETE') {
    $user = requireUser();
    $pdo  = getDB();
    // CASCADE удалит все связанные данные (calculations, measurements, reviews)
    $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$user['id']]);
    jsonSuccess(['message' => 'Аккаунт удалён']);
}

// ── Вспомогательная: убрать чувствительные поля ───────────────────────────
function safeUser(array $user): array {
    unset($user['password_hash']);
    return $user;
}
