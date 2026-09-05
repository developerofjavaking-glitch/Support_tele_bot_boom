# Telegraph Web Admin — Telegram Admin Client & Control Panel

A self-hosted, dark-themed 2-way messaging admin panel for your Telegram bot, inspired by the Telegraph client app. Built with PHP 8.2, SQLite, and vanilla JS — deployable as a single Docker container on [Render.com](https://render.com).

## What's new in this version

- **Restructured project**: a real `public/` web root, app logic under `src/`, and the SQLite database stored **outside** the web root (previously it lived inside the served folder — a security gap now fixed).
- **Login screen**: the panel is no longer open to anyone with the URL. Username/password come from environment variables.
- **Unread badges**: the sidebar now shows how many unread messages each user has; opening a chat marks it read.
- **Delete conversation**: remove a user and their full chat history from the trash icon in the chat header.
- **Separated assets**: CSS and JS pulled out of `index.php` into their own files under `public/assets/`.
- **Class-based backend**: `Database`, `Telegram`, and `Auth` classes replace the old procedural helpers, making the code easier to extend.

## Project Structure

```
.
├── Dockerfile
├── .gitignore
├── README.md
├── src/                      # application logic (not web-accessible)
│   ├── config.php            # env vars, paths, session bootstrap
│   ├── Database.php          # PDO/SQLite connection + schema + queries
│   ├── Telegram.php          # Telegram Bot API wrapper
│   └── Auth.php              # session-based login guard
├── public/                   # Apache document root
│   ├── index.php             # the admin panel UI (requires login)
│   ├── login.php
│   ├── logout.php
│   ├── webhook.php           # public endpoint — Telegram posts updates here
│   ├── api.php                # authenticated AJAX endpoints
│   └── assets/
│       ├── css/style.css
│       └── js/app.js
└── database/                  # SQLite file lives here (auto-created, gitignored)
```

## 1. Create a Telegram Bot

1. Message [@BotFather](https://t.me/BotFather) on Telegram.
2. Send `/newbot` and follow the prompts.
3. Copy the **bot token** BotFather gives you (looks like `123456789:ABCdefGhIJKlmNoPQRsTUVwxyZ`).

## 2. Deploy to Render.com

1. Push this project to a GitHub/GitLab repository.
2. In the Render Dashboard: **New +** → **Web Service** → connect your repo.
3. Render detects the `Dockerfile` automatically. Set:
   - **Environment:** Docker
   - **Region:** closest to your users
4. Under **Environment Variables**, add:

   | Key | Value |
   |---|---|
   | `BOT_TOKEN` | your BotFather token |
   | `ADMIN_USERNAME` | a username you choose for logging into the panel |
   | `ADMIN_PASSWORD` | a strong password |

5. Click **Create Web Service**.
6. Note your public URL once deployed, e.g. `https://telegraph-admin.onrender.com`.

> ⚠️ **Persistent disk:** Render's free/starter plan uses an ephemeral filesystem — the SQLite database resets on redeploys/restarts. For production, attach a [Render Disk](https://render.com/docs/disks) mounted at `/var/www/html/database` so users and chat history persist.

## 3. Set the Telegram Webhook

1. Open your deployed app and log in with the `ADMIN_USERNAME` / `ADMIN_PASSWORD` you set.
2. Click **Setup Webhook** in the header.
3. Enter:
   ```
   https://telegraph-admin.onrender.com/webhook.php
   ```
4. Click **Save Webhook**.

Or set it manually via URL (replace the placeholders):
```
https://api.telegram.org/bot<YOUR_BOT_TOKEN>/setWebhook?url=https://YOUR-APP.onrender.com/webhook.php
```

Verify anytime with:
```
https://api.telegram.org/bot<YOUR_BOT_TOKEN>/getWebhookInfo
```

## 4. Try It Out

1. Message your bot on Telegram (e.g. `/start`).
2. The user appears in the sidebar within ~3 seconds, with an unread badge.
3. Click the user to open the chat — this marks it read — and reply from the browser.
4. Use **Mass Broadcast** to message everyone at once, or the trash icon to delete a conversation.

## Environment Variables Reference

| Variable | Required | Description |
|---|---|---|
| `BOT_TOKEN` | ✅ Yes | Telegram bot token from BotFather |
| `ADMIN_USERNAME` | Recommended | Login username for the panel (default: `admin`) |
| `ADMIN_PASSWORD` | Recommended | Login password for the panel (default: `change-me` — **change this**) |
| `PORT` | Auto-set by Render | The container listens on this port automatically |

## Local Development

```bash
docker build -t telegraph-admin .
docker run -p 8080:80 \
  -e BOT_TOKEN=123456789:your-token-here \
  -e ADMIN_USERNAME=admin \
  -e ADMIN_PASSWORD=yourpassword \
  telegraph-admin
```

Open `http://localhost:8080`. To receive real Telegram updates locally, tunnel with `ngrok http 8080` and point the webhook at the tunnel's HTTPS URL.

## Security Notes

- Login is now required for `index.php` and `api.php`. `webhook.php` stays public since Telegram itself calls it (Telegram doesn't support custom auth headers on webhooks) — it only accepts POST and does nothing dangerous with the payload.
- The SQLite database now lives in `database/` **outside** `public/`, so it's no longer directly downloadable over HTTP.
- All queries use PDO prepared statements; all dynamic content is HTML-escaped in the UI.
- Sessions use `HttpOnly` cookies and are marked `Secure` automatically when served over HTTPS.
- Set a real, unique `ADMIN_PASSWORD` — the default is only a local-dev fallback.

## Troubleshooting

| Problem | Likely Cause |
|---|---|
| Redirected to login in a loop | Cookies blocked, or `ADMIN_USERNAME`/`ADMIN_PASSWORD` not set |
| "Bot Offline / Token Missing" badge | `BOT_TOKEN` env var isn't set or is invalid |
| Users never appear | Webhook not set, or set to the wrong URL/path |
| Messages fail to send | Bot token invalid, or the user has blocked the bot |
| Database resets after redeploy | No persistent disk attached — see the note in Step 2 |
| 500 error / blank page after deploy | Check Render logs — often a missing `AllowOverride`/docroot issue if you customized the Dockerfile |
