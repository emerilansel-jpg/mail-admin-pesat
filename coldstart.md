# Coldstart — Mail Admin (mail.pesat.ai/mailadmin/)

> Sesi baru / agent baru? Baca ini dulu. Berisi semua konteks yang dibutuhkan untuk lanjut tanpa start from zero.

## Apa Ini?

Admin panel untuk Mailcow mail server. Manage email accounts dari 25+ domain Cloudflare dalam satu UI.

- **URL**: `https://mail.pesat.ai/mailadmin/`
- **Login**: password `jdp123` (no username)
- **Stack**: PHP 8.3-FPM + Nginx + MySQL (Mailcow) + Cloudflare Tunnel

## Arsitektur

```
User → mail.pesat.ai → Cloudflare Tunnel → Nginx :80 → /mailadmin/ → PHP-FPM → MySQL (Mailcow)
                                                    ↘ / (lainnya) → Mailcow :8080
```

## Server

- **VPS**: SSDNodes `94.100.26.189`
- **SSH**: `ssh root@94.100.26.189` (password: `ymif5avvYc`)
- **OS**: Ubuntu 24.04 LTS
- **Docker**: Mailcow (`/opt/mailcow-dockerized/`)

## File Locations (di VPS)

| File | Path |
|------|------|
| Admin page | `/var/www/mailadmin/index.php` |
| Nginx config | `/etc/nginx/sites-available/mail.pesat.ai` |
| Mailcow config | `/opt/mailcow-dockerized/mailcow.conf` |
| PHP-FPM socket | `/var/run/php/php8.3-fpm.sock` |
| Landing page | `/var/www/client/jetdigipro/content-writing/index.html` |

## Credentials

### Mailcow
- **Admin**: `admin` / `jdp123` (web UI di `https://mail.pesat.ai/`)
- **MySQL** (Mailcow): user `mailcow`, pass `VDAs9CgVobI7GBspUMwfb2aeZtng`, port `127.0.0.1:13306`
- **MySQL root**: `N5pas4iqop2VMNYzPHc0vxmtGF8O`

### Cloudflare
- **API Token**: `cfut_EHtHzEEY192kPtdciXYjiczHJ54TJmXDnl4999Vw4e49e895`
- **Account**: `99dd60debc042e9b615dd44472645e71`
- **Zone pesat.ai**: `63fe5089ec50acfd1df25bf09ff38a9d`
- **Zone jetdigitalpro.com**: `e66b7417a2262e52f3bada18766a1b83`
- **Tunnel ID**: `d55dadca-0c49-4fc3-8f8b-17dd5ec2197c`
- **Tunnel CNAME**: `d55dadca-0c49-4fc3-8f8b-17dd5ec2197c.cfargotunnel.com`

### Active Mailboxes
- `seo@jetdigitalpro.com` / `jdp123`
- `gamma@toohumid.com` / `jdp123`

## Domain di Mailcow

Sudah added: `jetdigitalpro.com`, `toohumid.com`, `jasa-seo.id`, `jdp.industries`

Available di Cloudflare (tinggal +Add dari admin): 25 domain total.

## Fitur Admin Page

1. **Login** — password `jdp123` aja
2. **Domain list** — semua domain CF, blue = aktif di Mailcow, gray = siap add
3. **+Add domain** — sekali klik, add ke Mailcow
4. **Create email** — pilih domain, isi local part, auto-create mailbox + sender_acl + DKIM-ready attributes
5. **Inbox view** — klik 📥 icon, lihat semua email di mailbox tersebut
6. **Delete mailbox** — klik 🗑️

## Mailcow Quirks (Important!)

Saat create mailbox baru via MySQL direct insert, WAJIB set attributes:

```json
{"sender_acl": "*@domain.com", "mailbox_format": "maildir:"}
```

Tanpa `mailbox_format: "maildir:"`, Postfix akan return:
`550 5.1.1 Recipient address rejected: User unknown in virtual mailbox table`

Dan wajib insert ke `sender_acl` table juga:
```sql
INSERT INTO sender_acl (logged_in_as, send_as) VALUES ('user@domain', '@domain');
```

## Nginx Config Pattern

```nginx
server {
    listen 80;
    server_name mail.pesat.ai;

    location /mailadmin {
        rewrite ^ /mailadmin/ permanent;
    }
    location /mailadmin/ {
        alias /var/www/mailadmin/;
        index index.php;
        location ~ \.php$ {
            fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
            fastcgi_param SCRIPT_FILENAME $request_filename;
            include fastcgi_params;
        }
    }

    location / {
        proxy_pass http://127.0.0.1:8080;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_buffering off;
    }
}
```

## Deploy Update

```bash
# Transfer admin.php ke VPS
powershell.exe -Command "[Convert]::ToBase64String([IO.File]::ReadAllBytes('D:\Claude Cowork\Para-sites\admin.php')) | Set-Content -Path 'D:\temp\admin_b64.txt' -NoNewline -Encoding Ascii"
scp -q "D:\temp\admin_b64.txt" root@94.100.26.189:/tmp/
ssh root@94.100.26.189 "base64 -d < /tmp/admin_b64.txt > /var/www/mailadmin/index.php && chmod 644 /var/www/mailadmin/index.php"
```

## DNS Setup untuk Domain Baru

```bash
# Add MX record
curl -X POST "https://api.cloudflare.com/client/v4/zones/{ZONE_ID}/dns_records" \
  -H "Authorization: Bearer cfut_EHtHzEEY192kPtdciXYjiczHJ54TJmXDnl4999Vw4e49e895" \
  -H "Content-Type: application/json" \
  -d '{"type":"MX","name":"@","content":"mail.pesat.ai","priority":10,"ttl":300,"proxied":false}'

# Add SPF
curl -X POST "https://api.cloudflare.com/client/v4/zones/{ZONE_ID}/dns_records" \
  -H "Authorization: Bearer cfut_EHtHzEEY192kPtdciXYjiczHJ54TJmXDnl4999Vw4e49e895" \
  -H "Content-Type: application/json" \
  -d '{"type":"TXT","name":"@","content":"v=spf1 mx a:mail.pesat.ai ~all","ttl":300,"proxied":false}'
```

## Quick Commands

```bash
# Cek inbox mailbox
ssh root@94.100.26.189 "docker exec mailcowdockerized-dovecot-mailcow-1 doveadm search -u 'email@domain' mailbox INBOX ALL"

# Baca email
ssh root@94.100.26.189 "docker exec mailcowdockerized-dovecot-mailcow-1 doveadm fetch -u 'email@domain' 'hdr.From hdr.Subject date.received uid' mailbox INBOX all"

# Restart Mailcow
ssh root@94.100.26.189 "cd /opt/mailcow-dockerized && docker compose restart"

# Reload nginx
ssh root@94.100.26.189 "nginx -t && nginx -s reload"

# Restart PHP-FPM
ssh root@94.100.26.189 "systemctl restart php8.3-fpm"
```

## Related URLs

| Service | URL |
|---------|-----|
| Admin Panel | `https://mail.pesat.ai/mailadmin/` |
| Mailcow UI | `https://mail.pesat.ai/` |
| SOGo Webmail | `https://mail.pesat.ai/SOGo/` |
| Mailcow Admin (original) | `https://mail.pesat.ai/admin/` (admin/jdp123) |
