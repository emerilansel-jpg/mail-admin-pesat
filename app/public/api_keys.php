<?php
/**
 * api_keys.php — generate & revoke API keys.
 */
declare(strict_types=1);
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/apikey.php';

$user = require_login();
$uid = (int)$user['id'];
$error = $success = '';
$new_key = null;

if (isset($_GET['revoke'])) {
    apikey_revoke($uid, (int)$_GET['revoke']);
    $success = 'Key revoked';
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $res = apikey_create($uid);
    $new_key = $res['key'];
    $success = 'New API key created — copy it now, it won\'t be shown again';
}
$keys = apikey_list($uid);

require_once __DIR__ . '/_layout.php';
page_header('API Keys', $user);
alert($error, $success);

if ($new_key):
?>
<div class="card" style="border-color:#f59e0b">
  <h2>🔑 New API Key</h2>
  <label>Key (copy now)</label>
  <div class="code"><?= htmlspecialchars($new_key) ?></div>
  <p style="font-size:.8rem;color:#64748b">Use as: <span class="mono">Authorization: Bearer &lt;key&gt;</span></p>
</div>
<?php endif; ?>

<div class="grid2">
  <div class="card">
    <h2>Generate Key</h2>
    <p class="sub">Create a key for REST API + MCP access.</p>
    <form method="post"><button class="btn" style="width:100%">+ Generate Key</button></form>
  </div>
  <div class="card">
    <h2>My Keys</h2>
    <?php if (empty($keys)): ?>
      <p style="color:#64748b;font-size:.9rem">No active keys</p>
    <?php else: ?>
      <?php foreach ($keys as $k): ?>
      <div class="mb">
        <div>
          <div class="mono"><?= htmlspecialchars($k['key_prefix']) ?>…</div>
          <div class="m">Created <?= htmlspecialchars($k['created_at']) ?> · Rate <?= (int)$k['rate_limit'] ?>/h</div>
        </div>
        <a href="?revoke=<?= (int)$k['id'] ?>" class="btn-danger" onclick="return confirm('Revoke this key?')">Revoke</a>
      </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>
<?php page_footer(); ?>
