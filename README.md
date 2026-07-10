# 🧙 Merlin Spellcaster

**The hybrid email marketing platform that casts spells on your audience.**

A fully-featured email marketing & market research platform inspired by Mautic and Listmonk — built entirely in PHP 8.5+ with zero Composer dependencies, designed for shared hosting (Enhance, cPanel, Plesk, etc.).

---

## ✨ Features

### 📬 Email Marketing
- **Campaign Management** — Create, schedule, send, pause, and track email campaigns
- **HTML Email Editor** — Full HTML editor with live preview and template loading
- **Template Library** — Reusable email templates with starter designs
- **Segmented Lists** — Unlimited mailing lists with public/private types and double opt-in

### 👤 Subscriber Management
- **Subscriber Profiles** — Full contact history with opens, clicks, and list memberships
- **CSV Import** — Bulk import via CSV upload or paste, with column mapping
- **Subscription Forms** — Embeddable forms with JavaScript snippet and direct URL
- **Bulk Actions** — Select & unsubscribe/delete multiple subscribers at once
- **Tagging** — Tag-based subscriber segmentation
- **API Auto-Import** — REST API endpoint for programmatic subscriber creation

### ⚡ Automation
- **Drip Sequences** — Multi-step automation with delays (days + hours)
- **Trigger Types** — Subscribe, Confirm, Tag-based triggers
- **Step Types** — Email, Wait, Apply Tag, Webhook

### 📊 Analytics
- **Campaign Analytics** — Open rates, click rates, unsubscribes, bounces
- **Engagement Funnel** — Visual funnel from sent → delivered → opened → clicked
- **Subscriber Growth** — 12-month growth chart
- **Click Heatmap** — Top-clicked URLs per campaign
- **Open Tracking** — 1×1 pixel with unique open detection
- **Click Tracking** — Redirect-based click tracking with HMAC verification

### 🔬 Market Research
- **Survey Builder** — Drag-and-drop question builder with 8 question types
- **Question Types** — Text, Long Text, Multiple Choice, Dropdown, Rating (1–5), NPS (0–10), Yes/No, Email
- **NPS Scoring** — Automatic Net Promoter Score calculation
- **Response Analytics** — Distribution charts, text response review
- **CSV Export** — Export all survey responses

### 🛡 Security
- **HMAC Token Verification** — All tracking links use cryptographic tokens
- **CSRF Protection** — All state-changing forms protected
- **SQL Injection Prevention** — 100% prepared statements

---

## 📦 Installation

### Requirements
- PHP 8.5+ (8.4+ supported, 8.2+ minimum)
- PDO + PDO_MySQL extension
- MySQL / MariaDB 5.7+
- Web server (Apache/LiteSpeed with .htaccess, or Nginx)
- Email: SMTP server or PHP `mail()` fallback

### Quick Install (Setup Wizard)
1. Upload all files to your web server
2. Make `uploads/` directory writable: `chmod 755 uploads/`
3. Visit `https://yourdomain.com/setup/` in your browser
4. Follow the 6-step wizard:
   - ✅ Requirements check
   - 🗄 Database configuration & connection test
   - 📬 SMTP configuration
   - 👤 Create admin account
   - 📋 Create first mailing list
   - 🎉 Launch!

### Manual Configuration
Copy `config.local.php.example` → `config.local.php` and fill in credentials:

```php
<?php
$db_host = 'localhost';
$db_port = 3306;
$db_name = 'your_database';
$db_user = 'your_user';
$db_pass = 'your_password';
```

---

## ⏱ Cron Jobs

Set up these URLs as scheduled tasks (every minute) in your hosting control panel:

```
https://yourdomain.com/cron/send.php?secret=YOUR_SECRET
https://yourdomain.com/cron/automation.php?secret=YOUR_SECRET
```

Find your secret in **Settings → Cron & API**.

---

## 🔌 API

All API calls require a Bearer token (same as cron secret):

```
POST /api/index.php
Authorization: Bearer YOUR_SECRET
Content-Type: application/json

{"route":"subscribers","action":"create","email":"user@example.com","first_name":"Jane","list_id":1}
```

Available routes: `subscribers`, `campaigns`, `lists`, `template`

---

## 🏗 Architecture

```
/
├── admin/           # All admin UI pages
│   ├── dashboard.php
│   ├── campaigns.php, campaign_create.php, campaign_view.php
│   ├── subscribers.php, subscriber_view.php
│   ├── lists.php, forms.php, imports.php
│   ├── templates.php, template_edit.php
│   ├── automation.php
│   ├── analytics.php
│   ├── research.php, survey_create.php, survey_view.php
│   ├── settings.php
│   └── login.php
├── core/
│   ├── Auth.php          # Authentication
│   ├── Mailer.php        # Pure-PHP SMTP (no Composer)
│   └── TemplateEngine.php # Zero-dependency template engine
├── cron/
│   ├── send.php          # Email queue processor
│   └── automation.php    # Automation sequence runner
├── includes/
│   ├── header.php        # Shared admin layout
│   └── footer.php
├── setup/
│   ├── index.php         # 6-step setup wizard
│   └── test_db.php       # AJAX connection tester
├── config.php            # Core config & DB bootstrap
├── o.php                 # Open tracking pixel
├── r.php                 # Click tracking redirect
├── subscribe.php         # Public subscription endpoint
├── unsubscribe.php       # One-click unsubscribe
└── survey.php            # Public survey form
```

---

## 📝 License

MIT License — Free to use, modify, and distribute.

Built with ❤️ using PHP 8.5, MySQL, Tailwind CSS, Alpine.js, and Chart.js.
