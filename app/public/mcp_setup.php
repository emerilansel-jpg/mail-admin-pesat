<?php
/**
 * mcp_setup.php — Connect your AI agent to Teak Email.
 */
declare(strict_types=1);
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/apikey.php';

$user = require_login();
$uid = (int)$user['id'];
$keys = apikey_list($uid);
$app_url = cfg()['app_url'];
$mcp_url = $app_url . '/mcp';

require_once __DIR__ . '/_layout.php';
page_header('Connect AI Agent', $user);
?>
<div style="max-width:600px;margin:30px auto">

  <div style="text-align:center;margin-bottom:24px">
    <h1 style="font-size:1.5rem;margin-bottom:6px">🤖 Connect Your AI Agent</h1>
    <p class="sub" style="margin:0">One paste. Your agent reads emails and grabs OTPs automatically.</p>
  </div>

  <?php if (empty($keys)): ?>
  <!-- No API key yet -->
  <div class="card" style="border:1px solid #f59e0b;background:#1c1917">
    <p style="color:#fbbf24;font-size:.95rem;margin-bottom:12px">⚠️ You need an API key first.</p>
    <a href="/api_keys.php" class="btn" style="text-decoration:none;background:#f59e0b;color:#000">Generate API Key →</a>
  </div>

  <?php else: ?>

  <!-- Step 1: Copy this config -->
  <div class="card" style="border-left:4px solid #3b82f6">
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px">
      <div style="background:#3b82f6;color:#fff;width:24px;height:24px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.75rem">1</div>
      <h3 style="margin:0;font-size:1rem">Copy this config</h3>
    </div>
    <p style="font-size:.85rem;color:#94a3b8;margin-bottom:10px">Paste into your AI tool's MCP settings (Claude, Cursor, etc.)</p>

    <div style="position:relative">
      <div class="code" id="mcp-config" style="font-size:.78rem;line-height:1.5;padding-right:50px">{
  "mcpServers": {
    "teak-email": {
      "command": "npx",
      "args": ["-y", "codeinbox-mcp"],
      "env": {
        "CODEINBOX_API_KEY": "<?= htmlspecialchars($keys[0]['key_prefix']) ?>...",
        "CODEINBOX_API_URL": "<?= htmlspecialchars($app_url) ?>/api"
      }
    }
  }
}</div>
      <button onclick="navigator.clipboard.writeText(document.getElementById('mcp-config').textContent);this.textContent='Copied!';setTimeout(()=>this.textContent='Copy',1500)" style="position:absolute;top:8px;right:8px;background:#334155;border:none;color:#94a3b8;padding:6px 12px;border-radius:6px;font-size:.75rem;cursor:pointer">Copy</button>
    </div>

    <p style="font-size:.8rem;color:#64748b;margin-top:8px">
      Your API key: <span class="mono"><?= htmlspecialchars($keys[0]['key_prefix']) ?>…</span>
      <a href="/api_keys.php" style="color:#3b82f6;margin-left:8px">Manage keys</a>
    </p>
  </div>

  <!-- Step 2: Or use REST API directly -->
  <div class="card" style="border-left:4px solid #8b5cf6">
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px">
      <div style="background:#8b5cf6;color:#fff;width:24px;height:24px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.75rem">2</div>
      <h3 style="margin:0;font-size:1rem">Or use REST API directly</h3>
    </div>
    <p style="font-size:.85rem;color:#94a3b8;margin-bottom:10px">Works with curl, Python, Node.js, or any HTTP client.</p>

    <details style="background:#0f172a;border:1px solid #334155;border-radius:8px;margin-bottom:8px">
      <summary style="cursor:pointer;padding:10px 14px;font-weight:600;font-size:.85rem">📬 List inboxes</summary>
      <pre style="padding:10px 14px;font-size:.78rem;color:#93c5fd;overflow-x:auto;margin:0">curl -H "Authorization: Bearer YOUR_KEY" \
  <?= htmlspecialchars($app_url) ?>/api/inboxes</pre>
    </details>

    <details style="background:#0f172a;border:1px solid #334155;border-radius:8px;margin-bottom:8px">
      <summary style="cursor:pointer;padding:10px 14px;font-weight:600;font-size:.85rem">✨ Create inbox</summary>
      <pre style="padding:10px 14px;font-size:.78rem;color:#93c5fd;overflow-x:auto;margin:0">curl -X POST -H "Authorization: Bearer YOUR_KEY" \
  -H "Content-Type: application/json" \
  -d '{"domain":"jetdigitalpro.com","local_part":"my-inbox"}' \
  <?= htmlspecialchars($app_url) ?>/api/inboxes</pre>
    </details>

    <details style="background:#0f172a;border:1px solid #334155;border-radius:8px;margin-bottom:8px">
      <summary style="cursor:pointer;padding:10px 14px;font-weight:600;font-size:.85rem">🔑 Get OTP from latest email</summary>
      <pre style="padding:10px 14px;font-size:.78rem;color:#93c5fd;overflow-x:auto;margin:0">curl -H "Authorization: Bearer YOUR_KEY" \
  <?= htmlspecialchars($app_url) ?>/api/inboxes/my-inbox@jetdigitalpro.com/otp/1</pre>
    </details>

    <details style="background:#0f172a;border:1px solid #334155;border-radius:8px;margin-bottom:8px">
      <summary style="cursor:pointer;padding:10px 14px;font-weight:600;font-size:.85rem">📧 Read full email</summary>
      <pre style="padding:10px 14px;font-size:.78rem;color:#93c5fd;overflow-x:auto;margin:0">curl -H "Authorization: Bearer YOUR_KEY" \
  <?= htmlspecialchars($app_url) ?>/api/inboxes/my-inbox@jetdigitalpro.com/emails/1</pre>
    </details>
  </div>

  <!-- Step 3: Try it -->
  <div class="card" style="border-left:4px solid #10b981">
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px">
      <div style="background:#10b981;color:#fff;width:24px;height:24px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.75rem">3</div>
      <h3 style="margin:0;font-size:1rem">Try it now</h3>
    </div>
    <p style="font-size:.85rem;color:#94a3b8;margin-bottom:10px">Just tell your agent in plain English:</p>
    <div style="background:#0f172a;border:1px solid #334155;border-radius:8px;padding:14px;font-size:.9rem;color:#e2e8f0;font-style:italic">
      "Check my inbox at agent@jetdigitalpro.com and get the OTP from the latest email."
    </div>
    <p style="font-size:.8rem;color:#64748b;margin-top:8px">The agent will call the API automatically — no coding needed.</p>
  </div>

  <?php endif; ?>

  <!-- Help -->
  <div style="text-align:center;margin-top:20px">
    <p style="font-size:.8rem;color:#475569">Need help? <a href="mailto:support@teak.email" style="color:#3b82f6">support@teak.email</a></p>
  </div>

</div>
<?php page_footer(); ?>
