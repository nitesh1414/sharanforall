# Sharan Foundation — Complete Charity Platform

A full-featured **PHP + MySQL** charity platform built for Sharan Foundation (India & UK), featuring dynamic content management, donations with PDF receipts, peer-to-peer fundraising, analytics dashboard, multi-language support, and PHPMailer SMTP email notifications.

## 🌟 Key Modules

### 💝 Donation Module
- **Dedicated donation page** (`pages/donate.php`) with currency toggle, impact tiers, custom amount
- **6 payment methods** (UPI, Bank, Razorpay, Stripe, PayPal, Cheque)
- **PDF receipt generation** via bundled FPDF — professionally branded with 80G/Gift Aid info
- **Email receipt with PDF attached** via PHPMailer SMTP
- **Admin → 💝 Donations** with filters, CSV export, status workflow

### 📊 Donation Analytics (`admin/analytics.php`)
- 6 KPI cards: Total raised (GBP-eq), Completed, Unique Donors, Avg Donation, India/UK totals
- **Month-over-month** comparison with up/down indicator
- 7 interactive Chart.js charts:
  - Monthly trend (combo line + bar — 12 months)
  - Top causes by amount (horizontal bar)
  - Currency distribution (doughnut)
  - Payment methods (bar)
  - Donation types (pie)
  - Top countries (bar)
  - Status breakdown (doughnut)
- **Top 10 donors leaderboard**

### 💳 Live Payment Gateways (Razorpay + Stripe + PayPal)

**Sandbox mode by default — switch to Live in Admin → 💳 Payment Gateways.**

| Gateway | What it handles | Where to get keys |
|---|---|---|
| **Razorpay** | INR primary (UPI / cards / netbanking / wallets) — also works internationally | [dashboard.razorpay.com](https://dashboard.razorpay.com) |
| **Stripe** | Global cards + Apple/Google Pay (best for GBP/USD) | [dashboard.stripe.com](https://dashboard.stripe.com) |
| **PayPal** | PayPal balance + cards via PayPal (global) | [developer.paypal.com](https://developer.paypal.com) |

**End-to-end flow:**
1. Donor selects gateway on `pages/donate.php` → JS posts to `api/payment/init.php`
2. Init creates a `donations` row (status `pending`) + asks gateway to create an order/session
3. Gateway returns:
   - **Razorpay** → checkout popup opens client-side
   - **Stripe** → redirect to Stripe-hosted checkout
   - **PayPal** → redirect to PayPal approval page
4. After donor pays → gateway redirects to `api/payment/{gw}_success.php` → signature verified → donation marked completed → PDF receipt emailed automatically
5. Webhooks at `api/payment/{gw}_webhook.php` provide backup confirmation + handle subscription events asynchronously
6. **No-JS fallback**: bank transfer / UPI / cheque options still available (use the original `submit_donation.php` manual flow)

**Setup checklist:**
- ✅ All credentials stored encrypted in DB (admin → Payment Gateways)
- ✅ Sandbox/Live toggle
- ✅ Per-gateway enable/disable
- ✅ Webhook signature verification (HMAC for Razorpay/Stripe, API call for PayPal)
- ✅ Idempotent — duplicate webhooks won't double-charge
- ✅ Webhook event log with audit trail (Admin → Payment Gateways → bottom)
- ✅ Failed-payment handling marks donation as `failed` with reason

**Webhook URLs to configure in each gateway dashboard:**
- Razorpay → `https://sharanforall.org/api/payment/razorpay_webhook.php`
- Stripe → `https://sharanforall.org/api/payment/stripe_webhook.php`
- PayPal → `https://sharanforall.org/api/payment/paypal_webhook.php`

**Test cards (Sandbox):**
- Razorpay → `4111 1111 1111 1111`, any CVV, future expiry • UPI: `success@razorpay`
- Stripe → `4242 4242 4242 4242` (success), `4000 0000 0000 9995` (decline), any CVV
- PayPal → Use a sandbox personal account from PayPal Developer dashboard

### 🔁 Recurring Donations + Auto-Renewal Cron
- **Donor-facing**: Check "Monthly" or "Yearly" on donation form → auto-creates a subscription with optional frequency override (weekly/monthly/quarterly/yearly)
- **Self-service portal** (`pages/manage-donation.php?token=…`):
  - Donor receives a personal magic link by email
  - Can **pause, resume, change amount, or cancel** anytime
  - Sees full charge history
- **Admin → 🔁 Recurring Donations** page:
  - KPI cards: Total subs, Active, Due Today, MRR (INR + GBP)
  - Filter by status (active / paused / cancelled / expired)
  - Edit any subscription
  - View full charge history per donor
  - **▶ Run Cron Now** button — manually trigger the cron from admin
- **Cron job** (`cron/recurring_donations.php`):
  - **Phase 1**: Sends reminder emails 3 days before each charge
  - **Phase 2**: Processes today's due charges
    - **Auto-charge** via gateway tokens (Razorpay/Stripe stub included — wire your SDK)
    - **Manual methods** (bank/UPI/standing order) → emails donor "due today" notice + advances schedule
  - **Auto-retry** failed charges (3 attempts, 3 days apart, then auto-pauses)
  - Sends PDF receipt on successful charge
  - Logs every run to `cron_log` table for audit
- **Schedule options**:
  - Real cron: `0 9 * * * /usr/bin/php /path/to/cron/recurring_donations.php`
  - Web ping: `https://sharanforall.org/cron/recurring_donations.php?key=YOUR_SECRET`
  - Admin button: "Run Cron Now" in admin panel

### 🎗️ Peer-to-Peer Fundraising
- **Public listing** (`pages/fundraisers.php`) — browse active campaigns
- **"Start a Fundraiser"** form (`pages/start-fundraiser.php`) — anyone can launch a campaign for marathons, birthdays, etc.
- **Single fundraiser page** (`pages/fundraiser.php?slug=...`) with:
  - Live progress bar + goal tracking
  - Organizer bio + story
  - **Contribution form** for supporters to pledge
  - Recent supporters list with messages
  - Social sharing (Facebook / Twitter / WhatsApp / LinkedIn / Email)
- **Admin → 🎗️ Fundraisers** to:
  - Review pending submissions
  - Approve/reject (auto-emails organizer)
  - Manually update raised amounts
  - Feature campaigns on homepage
  - View all contributions per campaign
- Contributions auto-sync to main donations table

### 📧 PHPMailer SMTP + Email Templates
- **Bundled PHPMailer v6.9** — no Composer needed
- Configurable via Admin → Settings (Gmail, SendGrid, Mailgun, etc.)
- Branded HTML email templates
- **PDF attachment** support for donation receipts
- Email log viewer in admin

### 🌐 Multi-Language (English + Hindi)
- Auto-detect, cookie-based persistence
- Flag-based switcher in nav (🇬🇧 EN / 🇮🇳 हि)
- All content has `_hi` columns in DB (graceful fallback to English)
- Admin forms have language tabs

---

## 🚀 Quick Setup (XAMPP / WAMP / MAMP)

### 1. Place the folder
Copy `acts-foundation` into your server's webroot, e.g.:
```
C:\xampp\htdocs\LIVEpro\acts-foundation\
```

### 2. Import the database
- Start **Apache** + **MySQL** in XAMPP
- Open http://localhost/phpmyadmin
- Click **Import** → choose `sql/acts_foundation.sql` → **Go**

### 3. Verify DB config
Open `config/database.php` (defaults already match XAMPP):
```php
DB_HOST = localhost
DB_USER = root
DB_PASS = (empty)
BASE_URL = /LIVEpro/acts-foundation/
```

### 4. Run installer (once)
Visit: **http://localhost/LIVEpro/acts-foundation/install.php**
- This creates the admin user with the correct password hash.
- ⚠️ **Delete `install.php` after installation is complete.**

### 5. Login
- 🌐 **Site:** http://localhost/LIVEpro/acts-foundation/
- 🔐 **Admin:** http://localhost/LIVEpro/acts-foundation/admin/
  - Username: `admin`
  - Password: `admin123` *(change immediately!)*

---

## 🎯 What's Dynamic Now

**Every public page reads live data from the database** — edit in admin, see changes immediately on site!

| Public Page | Data Source |
|-------------|-------------|
| `index.php` (Home) | Programs, Testimonials, Site Settings, Stats |
| `about.php` | Team Members, Mission/Vision/Values, Settings |
| `programs.php` | All Programs (with images, features, stats) |
| `projects.php` | All Projects (progress bars, currencies, status) |
| `gallery.php` | All Gallery Images (filterable by category) |
| `blog.php` | Blog Posts (paginated, searchable, categorized) |
| `blog-post.php?slug=…` | Individual Blog Post (with related posts) |
| `contact.php` | Contact info from Settings, saves messages to DB |
| `volunteer.php` | Saves applications to DB + emails admin & applicant |
| `partner.php` | Saves inquiries to DB + emails admin & contact |

---

## 📧 Email Notifications

When any form is submitted, **two emails are sent automatically**:

| Form | Admin Gets | User Gets |
|------|------------|-----------|
| **Volunteer** | 🤝 Full application details + link to admin | 🙏 Thank-you / next steps |
| **Partner** | 🏢 Org details + proposal + link to admin | 🤝 Confirmation + 48 hr response promise |
| **Contact** | ✉️ Message + sender info + link to admin | 🙏 Confirmation |
| **Newsletter** | (none) | 🎉 Welcome email |

**Branded HTML email template** with Sharan Foundation header/footer included.

### Email Configuration

Edit `includes/mailer.php`:
```php
define('MAIL_FROM_EMAIL',    'noreply@sharanforall.org');  // sender
define('ADMIN_NOTIFY_EMAIL', 'admin@sharanforall.org');    // who gets alerts
```

#### XAMPP Local Setup
PHP's `mail()` needs a working SMTP server. Options:

1. **Easiest** — Edit `C:\xampp\php\php.ini` `[mail function]`:
   ```ini
   SMTP=smtp.gmail.com
   smtp_port=587
   sendmail_from=youremail@gmail.com
   ```
   Then configure `C:\xampp\sendmail\sendmail.ini` with your Gmail App Password.

2. **Production** — Switch to PHPMailer with SMTP (download from github.com/PHPMailer, drop into `includes/`, replace the `send_mail()` body).

3. **Mercury Mail** bundled with XAMPP also works.

> Every email attempt is logged in `logs/mail.log`. View it in **Admin → 📨 Email Log**.

---

## 📁 Folder Structure

```
acts-foundation/
├── index.php                  ← Dynamic homepage
├── install.php                ← Run ONCE then delete
├── README.md
│
├── config/
│   ├── database.php           ← DB credentials
│   └── .htaccess              ← Block direct access
│
├── includes/
│   ├── functions.php          ← Helpers (CSRF, flash, slug, upload)
│   ├── mailer.php             ← Email notifications + branded template
│   ├── public_header.php      ← Shared site nav/topbar
│   ├── public_footer.php      ← Shared site footer
│   └── .htaccess
│
├── api/                       ← Form handlers (save to DB + send emails)
│   ├── submit_volunteer.php
│   ├── submit_partner.php
│   ├── submit_contact.php
│   └── submit_newsletter.php
│
├── pages/                     ← All public-facing pages (PHP)
│   ├── about.php
│   ├── programs.php
│   ├── projects.php
│   ├── gallery.php
│   ├── blog.php
│   ├── blog-post.php          ← ?slug=…
│   ├── contact.php
│   ├── volunteer.php
│   └── partner.php
│
├── sql/
│   └── acts_foundation.sql    ← Full schema + sample data (12 tables)
│
├── css/style.css               ← Shared public styles
├── js/main.js                  ← Public JS (lightbox, filters, menu)
├── images/                     ← Base brand images (logo, hero, etc.)
├── uploads/                    ← Admin-uploaded files (programs/blog/etc.)
├── logs/                       ← Email send log
│
└── admin/                      ← Complete admin panel
    ├── login.php / logout.php
    ├── index.php               ← Dashboard with stats + recent submissions
    ├── programs.php            ← CRUD: Programs
    ├── projects.php            ← CRUD: Projects
    ├── blog.php                ← CRUD: Blog Posts (HTML editor)
    ├── gallery.php             ← CRUD: Gallery (with filter)
    ├── team.php                ← CRUD: Team Members
    ├── testimonials.php        ← CRUD: Testimonials
    ├── volunteers.php          ← View/manage Volunteer apps (filter, notes)
    ├── partners.php            ← View/manage Partner inquiries
    ├── contacts.php            ← View Contact messages
    ├── subscribers.php         ← View subscribers + CSV export
    ├── settings.php            ← Edit site-wide info (contact, social, mission)
    ├── email_log.php           ← View outgoing email activity
    ├── includes/{auth,header,footer}.php
    └── assets/css/admin.css
```

---

## ✨ Key Features

### Public Site
- 📱 Fully responsive
- 🗄️ All content driven by database (no more HTML edits!)
- 🔍 Searchable & paginated blog
- 🖼️ Filterable gallery with lightbox
- 📊 Live project progress bars from DB
- ⚡ Auto-updating stats based on DB records
- 🍞 Breadcrumbs, SEO-friendly URLs (blog slugs)
- 📨 Newsletter signup in footer

### Admin Panel
- 🔐 Secure bcrypt-hashed login + sessions
- 📊 Dashboard with 8 stat cards + recent activity tables
- 🆕 "New submission" badges on sidebar
- 📝 Full CRUD on 6 content types
- 🖼️ Image uploads (5MB, validated, organized in subfolders)
- 🔁 Status workflow: new → reviewed → approved/rejected
- 📥 CSV export for subscribers
- ⚙️ Site settings (one click to update header/footer everywhere)
- 📨 Email log viewer
- 🛡️ CSRF protection on every form
- 📱 Responsive admin (works on tablets/phones)

### Security
- ✅ PDO prepared statements (SQL injection-proof)
- ✅ Password hashing with `password_hash`
- ✅ CSRF tokens on all admin forms
- ✅ File upload validation (type, size)
- ✅ `.htaccess` blocks in `config/`, `includes/`, `logs/`
- ✅ `uploads/` blocks PHP execution
- ✅ Auto-escape with `e()` helper

---

## 🧪 Test the Full Flow

1. **Submit a volunteer application:**
   http://localhost/LIVEpro/acts-foundation/pages/volunteer.php

2. **Login to admin** → see "🤝 Volunteers" with a red "1" badge

3. **Click View** → see all details → change status → save

4. **Check Email Log** in admin → see the notifications that were attempted/sent

5. **Try editing a Program** in admin → reload `pages/programs.php` → see your change live!

---

## 🔧 Customization Tips

**Add a new admin user** (via phpMyAdmin SQL tab):
```sql
INSERT INTO admins (name, email, username, password, role)
VALUES ('Your Name', 'you@email.com', 'yourname',
        '<bcrypt-hash>', 'superadmin');
```
Generate the hash with PHP: `echo password_hash('newpassword', PASSWORD_DEFAULT);`

**Change the brand:** Edit `css/style.css` `:root` block (`--primary`, `--accent`, etc.)

**Add a new field to a section:** Add the column in MySQL → add the input in `admin/<section>.php` → use it in the corresponding public `pages/<section>.php`.

---

## 🙏 Made With Love

For Sharan Foundation — serving God, serving people, in India 🇮🇳 and the UK 🇬🇧.

> *"Pure and undefiled religion before God is this: to visit orphans and widows in their trouble." — James 1:27*
