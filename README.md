# Mail Admin — mail.pesat.ai/mailadmin/

Custom admin panel for Mailcow mail server. Manage email accounts from 25+ Cloudflare domains in a single UI.

## Quick Start

1. **Access**: `https://mail.pesat.ai/mailadmin/`
2. **Login**: password `jdp123` (no username needed)
3. Read [`coldstart.md`](./coldstart.md) for full context

## Features

- 🔑 Single password login (`jdp123`)
- 🌐 All Cloudflare domains listed (blue = active, gray = addable)
- ➕ One-click domain add to Mailcow
- ✉️ Create email with auto sender_acl + DKIM-ready attributes
- 📥 Inbox viewer per mailbox (read emails inline)
- 🗑️ Delete mailbox

## Stack

- **Frontend**: Single-file PHP (`index.php`)
- **Backend**: PHP 8.3-FPM + MySQL (Mailcow)
- **Server**: Nginx + Cloudflare Tunnel
- **Mail**: Mailcow Dockerized on SSDNodes VPS

## Files

- `index.php` — The admin panel (deploy to `/var/www/mailadmin/`)
- `landing.html` — SEO landing page (deploy to `/var/www/client/jetdigipro/content-writing/`)
- `coldstart.md` — Full session context & credentials

## Deploy

See `coldstart.md` → "Deploy Update" section.
