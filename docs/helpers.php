<?php
/**
 * api/helpers.php — общие утилиты для всех API-эндпоинтов.
 */

// Буферизуем вывод — любые PHP warnings/notices не сломают JSON-ответ
ob_start();

require_once __DIR__ . '/../db.php';

// ── JSON-ответы ────────────────────────────────────────────────────────────

function jsonSuccess(mixed $data = null, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
    exit;
}

function jsonError(string $message, int $code = 400): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

// ── CORS для локальной разработки ──────────────────────────────────────────
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Session-Token');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ── Получить тело запроса как JSON ─────────────────────────────────────────
function getBody(): array {
    $raw = file_get_contents('php://input');
    if (empty($raw)) return [];
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

// ── Идентификация пользователя по session_token ───────────────────────────
function getCurrentUser(): ?array {
    $token = $_SERVER['HTTP_X_SESSION_TOKEN']
          ?? getBody()['session_token']
          ?? ($_GET['session_token'] ?? null);

    if (!$token) return null;

    $pdo  = getDB();
    $stmt = $pdo->prepare('SELECT * FROM users WHERE session_token = ? LIMIT 1');
    $stmt->execute([$token]);
    return $stmt->fetch() ?: null;
}

function requireUser(): array {
    $user = getCurrentUser();
    if (!$user) jsonError('Требуется авторизация', 401);
    return $user;
}

function requireAdmin(): array {
    $user = requireUser();
    if ($user['role'] !== 'admin') jsonError('Доступ запрещён', 403);
    return $user;
}

// ── Санитизация ввода ──────────────────────────────────────────────────────
function clean(string $val): string {
    return trim(htmlspecialchars($val, ENT_QUOTES, 'UTF-8'));
}

function getMethod(): string {
    return $_SERVER['REQUEST_METHOD'];
}
