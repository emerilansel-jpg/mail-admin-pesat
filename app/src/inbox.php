<?php
/**
 * inbox.php — inbox provisioning: validation, domain pool, credits, rate limit.
 */
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/credits.php';
require_once __DIR__ . '/mailcow.php';
require_once __DIR__ . '/abuse.php';
require_once __DIR__ . '/redeem.php';

const CREDIT_EMAIL_RECEIVED = 1;
const CREDIT_INBOX_ACTIVE_DAY = 2;
const CREDIT_API_25_CALLS = 1;

/**
 * Buat inbox baru.
 * @return array [ok, error?, inbox?, email?, password?]
 */
function inbox_create(int $uid, string $domain, string $local_part, int $tier): array {
    $lp = strtolower(trim($local_part));
    if (!preg_match('/^[a-z0-9][a-z0-9._-]{0,62}[a-z0-9]$/', $lp)) {
        return ['error' => 'Invalid local part. Use letters, numbers, dots, hyphens.'];
    }
    if (str_contains($lp, '..')) {
        return ['error' => 'Local part cannot contain consecutive dots'];
    }

    // Honeypot guard
    if (honeypot_hit("$lp@$domain")) {
        audit($uid, 'honeypot_attempt', '', $lp);
        return ['error' => 'Invalid local part'];
    }

    // Domain must be from pool and active in Mailcow
    if (!in_array($domain, cfg()['pool_domains'], true) || !mailcow_domain_active($domain)) {
        return ['error' => 'Domain not available in pool'];
    }

    // Rate limit create per jam (berdasarkan trust tier)
    $limits = trust_limits($uid);
    if (!rate_limit_check("inbox:$uid", $limits['max_inboxes_per_hour'])) {
        return ['error' => 'Rate limit: too many inboxes created this hour'];
    }

    // Batas slot inbox sesuai tier
    $slot_limit = (int)tier_info($tier)['inbox_slots'];
    $cnt = inbox_count_active($uid);
    if ($cnt >= $slot_limit) {
        return ['error' => "Inbox slot limit reached ($slot_limit). Upgrade tier to add more."];
    }

    // Buat di Mailcow
    $email = "$lp@$domain";
    $pw = bin2hex(random_bytes(6));
    $res = mailcow_create_mailbox($email, $pw, 'CodeInbox');
    if (isset($res['error'])) return $res;

    // Register di ia_inboxes + konsumsi credits
    $retention = (int)tier_info($tier)['retention'];
    $st = db()->prepare(
        'INSERT INTO ia_inboxes (user_id, email_address, local_part, domain, retention_days, expires_at)
         VALUES (?,?,?,?,?, DATE_ADD(NOW(), INTERVAL ? DAY))'
    );
    $st->execute([$uid, $email, $lp, $domain, $retention, $retention]);

    // Debit rent inbox aktif (bulan pertama sekaligus, pro-rata)
    credit_mutate($uid, -(CREDIT_INBOX_ACTIVE_DAY * 30), 'inbox_create', $email);

    audit($uid, 'inbox_create', '', $email);
    return ['ok' => true, 'email' => $email, 'password' => $pw];
}

function inbox_count_active(int $uid): int {
    $st = db()->prepare("SELECT COUNT(*) FROM ia_inboxes WHERE user_id = ? AND status = 'active'");
    $st->execute([$uid]);
    return (int)$st->fetchColumn();
}

function inbox_list(int $uid): array {
    $st = db()->prepare(
        'SELECT * FROM ia_inboxes WHERE user_id = ? AND status = \'active\' ORDER BY id DESC'
    );
    $st->execute([$uid]);
    return $st->fetchAll();
}

/** Cek kepemilikan inbox. */
function inbox_owned(int $uid, string $email_address): ?array {
    $st = db()->prepare(
        'SELECT * FROM ia_inboxes WHERE user_id = ? AND email_address = ? AND status = \'active\''
    );
    $st->execute([$uid, $email_address]);
    return $st->fetch() ?: null;
}

/** List email di inbox (dengan konsumsi credits API). */
function inbox_emails(int $uid, string $email_address, bool $charge = true): array {
    $inbox = inbox_owned($uid, $email_address);
    if (!$inbox) return ['error' => 'Inbox not found'];
    if ($charge) {
        $res = credit_mutate($uid, -1, 'api_call', 'list_emails');
        if (!$res['ok']) return ['error' => $res['error']];
    }
    return ['ok' => true, 'inbox' => $inbox, 'emails' => mailcow_fetch_inbox($email_address)];
}

/** Baca email + OTP (konsumsi credits). */
function inbox_read(int $uid, string $email_address, int $uid_num): array {
    $inbox = inbox_owned($uid, $email_address);
    if (!$inbox) return ['error' => 'Inbox not found'];
    $res = credit_mutate($uid, -1, 'api_call', 'read_email');
    if (!$res['ok']) return ['error' => $res['error']];
    $raw = mailcow_fetch_message($email_address, $uid_num);
    if ($raw === null) return ['error' => 'Email not found'];
    return ['ok' => true, 'raw' => $raw];
}

/** Ekstrak OTP dari email (konsumsi credits). */
function inbox_otp(int $uid, string $email_address, int $uid_num): array {
    require_once __DIR__ . '/otp.php';
    $res = inbox_read($uid, $email_address, $uid_num);
    if (!isset($res['ok'])) return $res;
    $parsed = extract_otp($res['raw']);
    return [
        'ok' => true,
        'email' => $email_address,
        'uid' => $uid_num,
        'otp' => $parsed['otp'],
        'text' => mb_substr($parsed['text'], 0, 4000),
    ];
}

/** Delete inbox (soft delete + remove Mailcow mailbox). */
function inbox_delete(int $uid, string $email_address): array {
    $inbox = inbox_owned($uid, $email_address);
    if (!$inbox) return ['error' => 'Inbox not found'];
    db()->prepare("UPDATE ia_inboxes SET status = 'deleted' WHERE id = ?")->execute([$inbox['id']]);
    mailcow_delete_mailbox($email_address);
    audit($uid, 'inbox_delete', '', $email_address);
    return ['ok' => true];
}

/** Daily rent & retention cleanup — dijalankan cron. */
function inbox_daily_rent(): void {
    $users = db()->query('SELECT DISTINCT user_id FROM ia_inboxes WHERE status = \'active\'')->fetchAll();
    foreach ($users as $u) {
        $uid = (int)$u['user_id'];
        $cnt = inbox_count_active($uid);
        $delta = -(CREDIT_INBOX_ACTIVE_DAY * $cnt);
        credit_mutate($uid, $delta, 'daily_rent', 'daily');
    }
    // Expire inbox lewat retention
    db()->exec("UPDATE ia_inboxes SET status='deleted' WHERE status='active' AND expires_at < NOW()");
    // Hapus mailbox Mailcow yang expired (lewat email list — simpel: ambil deleted yg baru)
    $expired = db()->query(
        "SELECT email_address FROM ia_inboxes WHERE status='deleted' AND expires_at < NOW() - INTERVAL 7 DAY"
    )->fetchAll();
    foreach ($expired as $e) {
        mailcow_delete_mailbox($e['email_address']);
    }
}
