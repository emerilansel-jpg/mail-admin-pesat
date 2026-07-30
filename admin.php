<?php
session_start();
$password = 'jdp123';
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
    if ($_POST['password'] === $password) { $_SESSION['logged_in'] = true; }
    else { $error = 'Wrong password'; }
}
if (isset($_GET['logout'])) { session_destroy(); header('Location: /'); exit; }

$dsn = 'mysql:host=127.0.0.1;port=13306;dbname=mailcow';
$dbu = 'mailcow';
$dbp = 'VDAs9CgVobI7GBspUMwfb2aeZtng';
$cf_token = 'cfut_EHtHzEEY192kPtdciXYjiczHJ54TJmXDnl4999Vw4e49e895';

if (isset($_SESSION['logged_in'])) {
    if (isset($_POST['add_domain']) && isset($_POST['domain_name'])) {
        try {
            $pdo = new PDO($dsn, $dbu, $dbp);
            $pdo->exec("INSERT IGNORE INTO domain (domain, description, aliases, mailboxes, defquota, maxquota, quota, backupmx, active) VALUES ('{$_POST['domain_name']}', 'Auto-added', 100, 100, 10240, 102400, 1024000, 0, 1)");
            $success = "Domain added!";
        } catch (Exception $e) { $error = $e->getMessage(); }
    }
    if (isset($_GET['delete'])) {
        try {
            $pdo = new PDO($dsn, $dbu, $dbp);
            $pdo->prepare("DELETE FROM mailbox WHERE username=?")->execute([$_GET['delete']]);
            $pdo->prepare("DELETE FROM sender_acl WHERE logged_in_as=?")->execute([$_GET['delete']]);
            $success = 'Deleted: ' . $_GET['delete'];
        } catch (Exception $e) { $error = $e->getMessage(); }
    }
    if (isset($_POST['action']) && $_POST['action'] === 'create') {
        $lp = $_POST['local_part']; $dom = $_POST['domain']; $user = "$lp@$dom";
        $pw = $_POST['password'] ?: bin2hex(random_bytes(6));
        try {
            $pdo = new PDO($dsn, $dbu, $dbp);
            $hash = password_hash($pw, PASSWORD_BCRYPT);
            $pdo->prepare("INSERT INTO mailbox (username,password,name,local_part,domain,quota,active,attributes) VALUES (?,?,?,?,?,102400,1,'{\"sender_acl\":\"*@$dom\",\"mailbox_format\":\"maildir:\"}')")->execute([$user, "{BLF-CRYPT}$hash", $_POST['name'], $lp, $dom]);
            $pdo->prepare("INSERT IGNORE INTO sender_acl VALUES (NULL,?,'@$dom',0),(NULL,?,?,0)")->execute([$user, $user, $user]);
            $success = "Created: $user | Password: $pw";
        } catch (Exception $e) { $error = "Error: " . $e->getMessage(); }
    }
}

// Fetch inbox
$inbox = []; $inbox_user = isset($_GET['inbox']) ? $_GET['inbox'] : '';
$read_msg = isset($_GET['msg']) ? intval($_GET['msg']) : 0;
if ($inbox_user && isset($_SESSION['logged_in'])) {
    $safe_user = escapeshellarg($inbox_user);
    if ($read_msg > 0) {
        $output = shell_exec("docker exec mailcowdockerized-dovecot-mailcow-1 doveadm fetch -u $safe_user 'hdr.From hdr.Subject hdr.Date text.utf8' mailbox INBOX uid $read_msg 2>/dev/null");
        if ($output) {
            $lines = explode("\n", $output);
            $inbox['full'] = $output;
        }
    } else {
        $output = shell_exec("docker exec mailcowdockerized-dovecot-mailcow-1 doveadm fetch -u $safe_user 'hdr.From hdr.Subject date.received uid' mailbox INBOX all 2>/dev/null");
        if ($output) {
            $blocks = explode("\f", $output);
            foreach ($blocks as $block) {
                $block = trim($block);
                if (empty($block)) continue;
                $lines = explode("\n", $block);
                $msg = [];
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (strpos($line, 'hdr.from:') === 0) $msg['from'] = trim(substr($line, 9));
                    elseif (strpos($line, 'hdr.subject:') === 0) $msg['subject'] = trim(substr($line, 12));
                    elseif (strpos($line, 'date.received:') === 0) $msg['date'] = trim(substr($line, 14));
                    elseif (strpos($line, 'uid:') === 0) $msg['uid'] = trim(substr($line, 4));
                }
                if (!empty($msg)) $inbox[] = $msg;
            }
        }
    }
}

$domains_mc = []; $mailboxes = [];
if (isset($_SESSION['logged_in']) && !$inbox_user) {
    try {
        $pdo = new PDO($dsn, $dbu, $dbp);
        $domains_mc = $pdo->query("SELECT domain FROM domain WHERE active=1 ORDER BY domain")->fetchAll(PDO::FETCH_COLUMN);
        $mailboxes = $pdo->query("SELECT username, name FROM mailbox ORDER BY username")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) { $error = 'DB: ' . $e->getMessage(); }
}
$cf_domains = [];
if (isset($_SESSION['logged_in'])) {
    $ch = curl_init("https://api.cloudflare.com/client/v4/zones?per_page=50");
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer $cf_token", "Content-Type: application/json"]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $resp = json_decode(curl_exec($ch), true);
    curl_close($ch);
    if ($resp && $resp['success']) foreach ($resp['result'] as $z) $cf_domains[] = $z['name'];
}
?>
<!DOCTYPE html><html lang="en"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Mail Admin — mail.pesat.ai</title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:-apple-system,system-ui,sans-serif;background:#0f172a;color:#e2e8f0;min-height:100vh}
.container{max-width:800px;margin:0 auto;padding:30px 20px}
h1{font-size:1.4rem}h2{font-size:1.1rem;margin-bottom:12px;color:#f1f5f9}
.sub{color:#94a3b8;font-size:.9rem;margin-bottom:24px}
.card{background:#1e293b;border-radius:12px;padding:20px;margin-bottom:16px;border:1px solid #334155}
label{display:block;font-size:.85rem;font-weight:600;margin-bottom:6px;color:#cbd5e1}
input,select{width:100%;padding:10px 14px;border-radius:8px;border:1px solid #475569;background:#0f172a;color:#e2e8f0;font-size:.95rem;margin-bottom:12px;outline:none}
input:focus,select:focus{border-color:#3b82f6}
.btn{padding:10px 20px;border-radius:8px;border:none;font-weight:600;font-size:.9rem;cursor:pointer;display:inline-block}
.btn-p{background:#3b82f6;color:#fff;width:100%}.btn-p:hover{background:#2563eb}
.btn-s{background:#1e40af;color:#fff;font-size:.8rem;padding:4px 10px;text-decoration:none;border-radius:6px}
.alert{padding:12px 16px;border-radius:8px;margin-bottom:12px;font-size:.9rem}
.alert-e{background:#7f1d1d;color:#fca5a5;border:1px solid #991b1b}
.alert-s{background:#14532d;color:#86efac;border:1px solid #166534}
.stats{display:flex;gap:12px;margin-bottom:16px}
.stat{background:#0f172a;padding:12px;border-radius:8px;flex:1;text-align:center}
.stat .n{font-size:1.4rem;font-weight:700;color:#3b82f6}
.stat .l{font-size:.75rem;color:#64748b}
.mb{display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid #334155;font-size:.9rem}
.mb:last-child{border-bottom:none}.mb .e{font-weight:500}.mb .m{color:#94a3b8;font-size:.8rem}
.grid2{display:grid;grid-template-columns:1fr 1fr;gap:12px}@media(max-width:600px){.grid2{grid-template-columns:1fr}}
.dc{display:inline-block;background:#0f172a;padding:4px 10px;border-radius:6px;font-size:.8rem;margin:3px;color:#94a3b8}
.dc.act{border:1px solid #3b82f6;color:#93c5fd}
.footer{text-align:center;color:#475569;font-size:.8rem;margin-top:40px}
/* Inbox styles */
.em{display:flex;align-items:center;padding:10px 12px;border-bottom:1px solid #334155;cursor:pointer;text-decoration:none;color:#e2e8f0;gap:12px}
.em:hover{background:#334155;border-radius:6px}
.em:last-child{border-bottom:none}
.em .efr{flex-shrink:0;width:140px;font-size:.8rem;color:#94a3b8;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.em .esub{flex:1;font-weight:500;font-size:.85rem}
.em .edate{flex-shrink:0;font-size:.75rem;color:#64748b;text-align:right;width:80px}
.em .euid{display:none}
.back{display:inline-block;color:#3b82f6;text-decoration:none;font-size:.9rem;margin-bottom:16px}
.msg-view{background:#0f172a;padding:20px;border-radius:8px;margin-top:12px;font-size:.85rem;line-height:1.6;white-space:pre-wrap;word-break:break-word;max-height:60vh;overflow-y:auto}
.msg-hdr{color:#94a3b8;font-size:.8rem;margin-bottom:4px}
.msg-hdr span{color:#e2e8f0}
.em-num{color:#64748b;font-size:.75rem;width:24px;flex-shrink:0}
</style></head><body>
<div class=container>
<h1>📧 Mail Admin</h1>
<p class=sub>mail.pesat.ai</p>
<?php if ($error): ?><div class="alert alert-e"><?=htmlspecialchars($error)?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-s"><?=htmlspecialchars($success)?></div><?php endif; ?>

<?php if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']): ?>
<div class=card style="max-width:400px;margin:40px auto">
<h2>🔑 Admin</h2>
<form method=post>
<input type=password name=password placeholder=Password required autofocus>
<button class="btn btn-p">Login</button>
</form>
</div>

<?php elseif ($inbox_user): ?>
<a href="/mailadmin/" class=back>← Back to Dashboard</a>
<h2>📥 <?=htmlspecialchars($inbox_user)?></h2>
<?php if ($read_msg > 0 && isset($inbox['full'])): ?>
<div class=card>
<a href="?inbox=<?=urlencode($inbox_user)?>" class=back>← Back to inbox</a>
<div class=msg-view><?=htmlspecialchars($inbox['full'])?></div>
</div>
<?php elseif (!empty($inbox)): ?>
<div class=card>
<?php $i = count($inbox); foreach ($inbox as $msg): ?>
<a href="?inbox=<?=urlencode($inbox_user)?>&msg=<?=$msg['uid']??0?>" class=em>
<span class=em-num><?=$i--?></span>
<span class=efr><?=htmlspecialchars(substr($msg['from']??'?',0,30))?></span>
<span class=esub><?=htmlspecialchars(substr($msg['subject']??'(no subject)',0,50))?></span>
<span class=edate><?=htmlspecialchars(substr($msg['date']??'',5,11))?></span>
</a>
<?php endforeach; ?>
</div>
<?php else: ?>
<div class=card><p style="color:#64748b">📭 No emails in INBOX</p></div>
<?php endif; ?>

<?php else: ?>
<div class=stats>
<div class=stat><div class=n><?=count($cf_domains)?></div><div class=l>Domains</div></div>
<div class=stat><div class=n><?=count($domains_mc)?></div><div class=l>Added</div></div>
<div class=stat><div class=n><?=count($mailboxes)?></div><div class=l>Mailboxes</div></div>
</div>

<div class=card><h2>🌐 Domains</h2>
<?php foreach ($cf_domains as $d): $a=in_array($d,$domains_mc); ?>
<span class="dc <?=$a?'act':''?>"><?=htmlspecialchars($d)?>
<?php if(!$a):?><form method=post style=display:inline><input type=hidden name=domain_name value="<?=htmlspecialchars($d)?>"><button type=submit name=add_domain style="background:none;border:none;color:#3b82f6;cursor:pointer;font-size:.7rem">+Add</button></form><?php endif;?></span>
<?php endforeach;?>
</div>

<div class=grid2>
<div class=card><h2>✉️ Create</h2>
<form method=post><input type=hidden name=action value=create>
<select name=domain required><?php foreach($domains_mc as $d):?><option><?=htmlspecialchars($d)?></option><?php endforeach;?></select>
<input type=text name=local_part placeholder="Email" required pattern="[a-z0-9._-]+">
<input type=text name=name placeholder="Name">
<input type=text name=password placeholder="Password" value=jdp123>
<button class="btn btn-p">Create ✨</button></form></div>

<div class=card><h2>📋 Mailboxes</h2>
<?php if(empty($mailboxes)):?><p style="color:#64748b;font-size:.9rem">No mailboxes</p>
<?php else:?><?php foreach($mailboxes as $mb):?>
<div class=mb>
<div class=e><?=htmlspecialchars($mb['username'])?> <span class=m><?=htmlspecialchars($mb['name']?:'')?></span></div>
<div style=display:flex;gap:6px;align-items:center>
<a href="?inbox=<?=urlencode($mb['username'])?>" class=btn-s>📥</a>
<a href="?delete=<?=urlencode($mb['username'])?>" class=btn-s style="background:#dc2626" onclick="return confirm('Delete?')">🗑️</a>
</div></div>
<?php endforeach;?><?php endif;?></div></div>

<p style=text-align:center;margin:20px>
<a href=?logout style=color:#64748b;font-size:.85rem>Logout</a> |
<a href=https://mail.pesat.ai style=color:#3b82f6;font-size:.85rem target=_blank>Webmail</a> |
<a href=https://mail.pesat.ai/SOGo/ style=color:#3b82f6;font-size:.85rem target=_blank>SOGo</a>
</p>
<?php endif;?>
<div class=footer>Mail Admin — Password: jdp123</div>
</div></body></html>
