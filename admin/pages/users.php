<?php
$pageTitle  = 'All Users';
$breadcrumb = 'All Users';
require_once __DIR__ . '/../includes/sidebar.php';

$db = Database::connect();

// Handle toggle active
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf($_POST['csrf_token'] ?? '')) {
    $uid    = (int)($_POST['user_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    if ($action === 'toggle_active' && $uid !== $_SESSION['user_id']) {
        $curr = (int)$db->query("SELECT is_active FROM users WHERE id=$uid")->fetchColumn();
        $db->prepare("UPDATE users SET is_active=? WHERE id=?")->execute([!$curr, $uid]);
        setFlash('success', 'User status updated.');
    }
    header('Location: users.php'); exit;
}

$search   = sanitize($_GET['q'] ?? '');
$roleFilter = sanitize($_GET['role'] ?? '');
$page     = max(1,(int)($_GET['page'] ?? 1));
$perPage  = 15;

$where  = ["u.role != 'admin'"];
$params = [];
if ($search) {
    $where[]  = '(u.full_name LIKE ? OR u.email LIKE ?)';
    $params   = array_merge($params, ["%$search%", "%$search%"]);
}
if ($roleFilter) {
    $where[]  = 'u.role = ?';
    $params[] = $roleFilter;
}
$whereStr = implode(' AND ', $where);

$countStmt = $db->prepare("SELECT COUNT(*) FROM users u WHERE $whereStr");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$pag   = paginate($total, $perPage, $page);

$stmt = $db->prepare("
    SELECT u.* FROM users u WHERE $whereStr
    ORDER BY u.created_at DESC
    LIMIT {$pag['per_page']} OFFSET {$pag['offset']}
");
$stmt->execute($params);
$users = $stmt->fetchAll();
?>

<div class="page-title">
  <h2>All Users</h2>
  <p><?= $total ?> users (excluding admins)</p>
</div>

<!-- Search/filter -->
<div class="card" style="margin-bottom:1.25rem;">
  <div class="card-body" style="padding:1rem;">
    <form method="GET" style="display:flex;gap:0.75rem;flex-wrap:wrap;align-items:flex-end;">
      <div style="flex:1;min-width:200px;">
        <label class="form-label">Search</label>
        <div class="input-icon-wrapper">
          <i class="fa-solid fa-magnifying-glass input-icon"></i>
          <input type="text" name="q" class="form-control" placeholder="Name or email…" value="<?= htmlspecialchars($search) ?>">
        </div>
      </div>
      <div>
        <label class="form-label">Role</label>
        <select name="role" class="form-control form-select" style="min-width:140px;">
          <option value="">All Roles</option>
          <option value="patient"   <?= $roleFilter==='patient'?'selected':'' ?>>Patients</option>
          <option value="herbalist" <?= $roleFilter==='herbalist'?'selected':'' ?>>Herbalists</option>
        </select>
      </div>
      <button type="submit" class="btn btn-primary">Search</button>
      <?php if ($search || $roleFilter): ?><a href="users.php" class="btn btn-ghost">Clear</a><?php endif; ?>
    </form>
  </div>
</div>

<div class="card">
  <div class="table-wrapper">
    <table>
      <thead>
        <tr><th>#</th><th>Name</th><th>Email</th><th>Role</th><th>Phone</th><th>Status</th><th>Joined</th><th>Actions</th></tr>
      </thead>
      <tbody>
        <?php if (empty($users)): ?>
          <tr><td colspan="8" style="text-align:center;padding:2rem;color:var(--text-light);">No users found.</td></tr>
        <?php else: ?>
          <?php foreach ($users as $i => $u): ?>
          <tr>
            <td style="color:var(--text-light);font-size:0.82rem;"><?= $pag['offset']+$i+1 ?></td>
            <td>
              <div style="display:flex;align-items:center;gap:0.5rem;">
                <div style="width:32px;height:32px;border-radius:50%;background:var(--green-pale);display:flex;align-items:center;justify-content:center;color:var(--green-dark);font-weight:700;font-size:0.82rem;flex-shrink:0;">
                  <?= strtoupper(substr($u['full_name'],0,1)) ?>
                </div>
                <strong style="font-size:0.9rem;"><?= htmlspecialchars($u['full_name']) ?></strong>
              </div>
            </td>
            <td style="font-size:0.85rem;"><?= htmlspecialchars($u['email']) ?></td>
            <td><span class="badge <?= $u['role']==='herbalist'?'badge-earth':'badge-green' ?>"><?= ucfirst($u['role']) ?></span></td>
            <td style="font-size:0.85rem;"><?= htmlspecialchars($u['phone'] ?? '—') ?></td>
            <td>
              <?php if ($u['role']==='herbalist' && !$u['is_approved']): ?>
                <span class="badge badge-gold">Pending</span>
              <?php elseif ($u['is_active']): ?>
                <span class="badge badge-green">Active</span>
              <?php else: ?>
                <span class="badge badge-red">Inactive</span>
              <?php endif; ?>
            </td>
            <td style="font-size:0.78rem;color:var(--text-light);"><?= date('d M Y', strtotime($u['created_at'])) ?></td>
            <td>
              <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                <input type="hidden" name="action"    value="toggle_active">
                <input type="hidden" name="user_id"   value="<?= $u['id'] ?>">
                <button type="submit" class="btn btn-ghost btn-sm">
                  <?= $u['is_active'] ? '<i class="fa-solid fa-ban"></i> Disable' : '<i class="fa-solid fa-check"></i> Enable' ?>
                </button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php if ($pag['total_pages'] > 1): ?>
  <div class="card-footer" style="display:flex;justify-content:space-between;align-items:center;">
    <span style="font-size:0.85rem;color:var(--text-light);">Showing <?= $pag['offset']+1 ?>–<?= min($pag['offset']+$pag['per_page'],$total) ?> of <?= $total ?></span>
    <div style="display:flex;gap:0.4rem;">
      <?php if ($pag['has_prev']): ?><a href="?page=<?= $page-1 ?>&q=<?= urlencode($search) ?>&role=<?= $roleFilter ?>" class="btn btn-ghost btn-sm">← Prev</a><?php endif; ?>
      <?php for ($p=1;$p<=$pag['total_pages'];$p++): ?>
        <a href="?page=<?= $p ?>&q=<?= urlencode($search) ?>&role=<?= $roleFilter ?>" class="btn btn-sm <?= $p==$page?'btn-primary':'btn-ghost' ?>"><?= $p ?></a>
      <?php endfor; ?>
      <?php if ($pag['has_next']): ?><a href="?page=<?= $page+1 ?>&q=<?= urlencode($search) ?>&role=<?= $roleFilter ?>" class="btn btn-ghost btn-sm">Next →</a><?php endif; ?>
    </div>
  </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
