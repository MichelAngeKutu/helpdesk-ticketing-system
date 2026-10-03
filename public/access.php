<?php
require __DIR__ . '/../src/bootstrap.php';
$user = require_login();
$isAdmin = $user['role'] === 'admin';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    // Any user can ask for a permission
    if ($action === 'request') {
        $permissionId = (int) ($_POST['permission_id'] ?? 0);
        $reason = trim($_POST['reason'] ?? '');

        $perm = db()->prepare('SELECT code FROM permissions WHERE id = ?');
        $perm->execute([$permissionId]);
        $code = $perm->fetchColumn();

        $pending = db()->prepare("SELECT 1 FROM access_requests WHERE user_id = ? AND permission_id = ? AND status = 'pending'");
        $pending->execute([$user['id'], $permissionId]);

        if ($code === false) {
            $errors[] = 'Choose a permission.';
        } elseif ($reason === '') {
            $errors[] = 'Explain why you need this access.';
        } elseif (has_permission($user, $code)) {
            $errors[] = 'You already have this permission.';
        } elseif ($pending->fetchColumn()) {
            $errors[] = 'You already have a pending request for this permission.';
        } else {
            db()->prepare('INSERT INTO access_requests (user_id, permission_id, reason, status, created_at) VALUES (?, ?, ?, \'pending\', ?)')
                ->execute([$user['id'], $permissionId, $reason, now()]);
            $requestId = (int) db()->lastInsertId();
            audit('access_requested', 'access_request', $requestId, 'Permission: ' . $code);
            flash('Request sent. An administrator will review it.');
            redirect('access.php');
        }
    }

    // Only admins can approve or deny, and never their own request
    if (in_array($action, ['approve', 'deny'], true) && $isAdmin) {
        $requestId = (int) ($_POST['request_id'] ?? 0);
        $note = trim($_POST['review_note'] ?? '');

        $req = db()->prepare(
            "SELECT ar.*, p.code FROM access_requests ar JOIN permissions p ON p.id = ar.permission_id
             WHERE ar.id = ? AND ar.status = 'pending'"
        );
        $req->execute([$requestId]);
        $request = $req->fetch();

        if (!$request) {
            $errors[] = 'That request has already been reviewed.';
        } elseif ((int) $request['user_id'] === (int) $user['id']) {
            $errors[] = 'You cannot review your own request.';
        } else {
            $status = $action === 'approve' ? 'approved' : 'denied';
            $pdo = db();
            $pdo->beginTransaction();   // the decision and the grant succeed or fail together
            try {
                $pdo->prepare('UPDATE access_requests SET status = ?, reviewed_by = ?, review_note = ?, reviewed_at = ? WHERE id = ?')
                    ->execute([$status, $user['id'], $note ?: null, now(), $requestId]);
                if ($status === 'approved') {
                    $pdo->prepare('INSERT INTO user_permissions (user_id, permission_id, granted_by, granted_at) VALUES (?, ?, ?, ?)')
                        ->execute([$request['user_id'], $request['permission_id'], $user['id'], now()]);
                }
                $pdo->commit();
            } catch (Throwable $ex) {
                $pdo->rollBack();
                throw $ex;
            }
            audit('access_' . $status, 'access_request', $requestId, 'Permission: ' . $request['code'] . ' for user #' . $request['user_id']);
            flash('Request ' . $status . '.');
            redirect('access.php');
        }
    }
}

$permissions = db()->query('SELECT id, code, description FROM permissions ORDER BY code')->fetchAll();

$mine = db()->prepare(
    'SELECT ar.status, ar.reason, ar.review_note, ar.created_at, p.code
     FROM access_requests ar JOIN permissions p ON p.id = ar.permission_id
     WHERE ar.user_id = ? ORDER BY ar.created_at DESC'
);
$mine->execute([$user['id']]);
$mine = $mine->fetchAll();

$queue = $isAdmin ? db()->query(
    "SELECT ar.id, ar.user_id, ar.reason, ar.created_at, p.code, u.full_name
     FROM access_requests ar
     JOIN permissions p ON p.id = ar.permission_id
     JOIN users u ON u.id = ar.user_id
     WHERE ar.status = 'pending' ORDER BY ar.created_at"
)->fetchAll() : [];

$title = $isAdmin ? 'Access requests' : 'Request access';
require __DIR__ . '/../src/views/header.php';
?>
<h1><?= e($title) ?></h1>
<?php foreach ($errors as $err): ?><p class="error"><?= e($err) ?></p><?php endforeach; ?>

<?php if ($isAdmin): ?>
    <h2>Waiting for review</h2>
    <?php if (!$queue): ?><p class="empty">No pending requests.</p><?php endif; ?>
    <?php foreach ($queue as $r): ?>
        <article class="request">
            <p><strong><?= e($r['full_name']) ?></strong> requests <code><?= e($r['code']) ?></code>
               <span class="meta"><?= e(substr((string) $r['created_at'], 0, 16)) ?></span></p>
            <p><?= e($r['reason']) ?></p>
            <?php if ((int) $r['user_id'] === (int) $user['id']): ?>
                <p class="meta">Another administrator must review your own request.</p>
            <?php else: ?>
                <form method="post" class="inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="request_id" value="<?= (int) $r['id'] ?>">
                    <input name="review_note" placeholder="Note (optional)" aria-label="Review note">
                    <button type="submit" name="action" value="approve">Approve</button>
                    <button type="submit" name="action" value="deny" class="secondary">Deny</button>
                </form>
            <?php endif; ?>
        </article>
    <?php endforeach; ?>
<?php endif; ?>

<h2>Ask for a permission</h2>
<form method="post" class="stack narrow" data-validate>
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="request">
    <label>Permission
        <select name="permission_id" required>
            <option value="">Choose one</option>
            <?php foreach ($permissions as $p): ?>
                <option value="<?= (int) $p['id'] ?>"><?= e($p['code']) ?>: <?= e($p['description']) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label>Why do you need it? <textarea name="reason" rows="3" maxlength="500" required></textarea></label>
    <button type="submit">Send request</button>
</form>

<h2>My requests</h2>
<?php if (!$mine): ?><p class="empty">You haven't requested any access.</p><?php else: ?>
<div class="table-wrap">
<table>
    <thead><tr><th>Permission</th><th>Status</th><th>Reason</th><th>Reviewer note</th><th>Requested</th></tr></thead>
    <tbody>
    <?php foreach ($mine as $m): ?>
        <tr>
            <td><code><?= e($m['code']) ?></code></td>
            <td><?= badge($m['status']) ?></td>
            <td><?= e($m['reason']) ?></td>
            <td><?= e($m['review_note'] ?? '') ?></td>
            <td><?= e(substr((string) $m['created_at'], 0, 16)) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php endif; ?>
<?php require __DIR__ . '/../src/views/footer.php'; ?>
