<?php
// Loaded at the top of every page: starts the session, connects to the
// database, and defines the helper functions the pages share.

declare(strict_types=1);

session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
session_start();

$configFile = __DIR__ . '/../config/config.php';
if (!file_exists($configFile)) {
    exit('Missing config/config.php. Copy config/config.example.php to config/config.php and fill in your database details.');
}
$config = require $configFile;

/** Returns one shared PDO connection to SQL Server. */
function db(): PDO
{
    static $pdo = null;
    global $config;
    if ($pdo === null) {
        $pdo = new PDO($config['db']['dsn'], $config['db']['user'], $config['db']['pass'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }
    return $pdo;
}

function now(): string
{
    return date('Y-m-d H:i:s');
}

/** Character count that still works if the mbstring extension is disabled. */
function text_length(string $value): int
{
    return function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
}

/** Escapes text before printing it into HTML (prevents XSS). */
function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function redirect(string $path): never
{
    header('Location: ' . $path);
    exit;
}

/** One-time messages shown on the next page load. */
function flash(?string $message = null): ?string
{
    if ($message !== null) {
        $_SESSION['flash'] = $message;
        return null;
    }
    $msg = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $msg;
}

/* ---------- CSRF protection ---------- */

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . csrf_token() . '">';
}

function csrf_check(): void
{
    if (!hash_equals(csrf_token(), $_POST['csrf'] ?? '')) {
        http_response_code(400);
        exit('Invalid form token. Go back, refresh the page and try again.');
    }
}

/* ---------- Authentication and authorization ---------- */

function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    static $user = null;
    if ($user === null) {
        $stmt = db()->prepare('SELECT id, username, full_name, role FROM users WHERE id = ?');
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch() ?: null;
    }
    return $user;
}

function require_login(): array
{
    $user = current_user();
    if ($user === null) {
        redirect('login.php');
    }
    return $user;
}

/** Stops the request unless the user has one of the given roles. */
function require_role(array $user, string ...$roles): void
{
    if (!in_array($user['role'], $roles, true)) {
        http_response_code(403);
        exit('You do not have access to this page.');
    }
}

function is_staff(array $user): bool
{
    return in_array($user['role'], ['technician', 'admin'], true);
}

/** True if the user was granted a permission through an approved access request. */
function has_permission(array $user, string $code): bool
{
    $stmt = db()->prepare(
        'SELECT 1 FROM user_permissions up
         JOIN permissions p ON p.id = up.permission_id
         WHERE up.user_id = ? AND p.code = ?'
    );
    $stmt->execute([$user['id'], $code]);
    return (bool) $stmt->fetchColumn();
}

/* ---------- Audit log ---------- */

/** Records an event (login, status change, access decision...) in audit_log. */
function audit(string $action, ?string $entityType = null, ?int $entityId = null, ?string $details = null, ?int $userId = null): void
{
    $userId ??= $_SESSION['user_id'] ?? null;
    $stmt = db()->prepare(
        'INSERT INTO audit_log (user_id, action, entity_type, entity_id, details, ip_address, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([$userId, $action, $entityType, $entityId, $details, $_SERVER['REMOTE_ADDR'] ?? 'cli', now()]);
}

/* ---------- Display helpers ---------- */

function label(string $value): string
{
    return ucfirst(str_replace('_', ' ', $value));
}

function badge(string $value): string
{
    return '<span class="badge badge-' . e($value) . '">' . e(label($value)) . '</span>';
}
