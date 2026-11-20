# ডিপ্লয়মেন্ট ও আপডেট গাইড · Deployment & Update Guide

এই গাইডে তিনটি জিনিস আছে: (১) সাইট প্রথমবার পাবলিশ করার চেকলিস্ট, (২) পাবলিশের পর
নতুন ফিচার/আপডেট নিরাপদে যোগ করার নিয়ম, (৩) সাইট হঠাৎ বন্ধ হলে সবার আগে জানার
ব্যবস্থা ও ব্যাকআপ রুটিন। অ্যাডমিন প্যানেলের **অ্যাডমিন → সিস্টেম** পেজ
(`/admin/system`) এই কাজগুলোর অধিকাংশ ব্রাউজার থেকেই করতে দেয়।

---

## ১) প্রথম পাবলিশ চেকলিস্ট · First-publish checklist

`.env` ফাইলে:

```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com
```

তারপর:

1. HTTPS সার্টিফিকেট চালু করুন (হোস্টিং প্যানেলে সাধারণত ফ্রি SSL থাকে)।
2. অ্যাডমিন হিসেবে লগইন করে **অ্যাডমিন → সিস্টেম**-এ যান।
   **সাইট স্বাস্থ্য পরীক্ষা** কার্ডের সব ঘরে ✓ আসা উচিত — কোনোটিতে ⚠ থাকলে
   পাশের ইঙ্গিত দেখে ঠিক করুন (যেমন debug চালু থাকলে বন্ধ করা)।
3. **প্রথম ব্যাকআপ** তৈরি করে ডাউনলোড করে নিজের কম্পিউটারে রাখুন।

## ২) নতুন ফিচার/আপডেট যোগ করার নিরাপদ নিয়ম · Safe update workflow

নতুন ফিচার সবসময় গিট প্যাচ (`.patch`) বা `git pull` দিয়ে আসবে। ভিজিটররা যেন
মাঝপথে ভাঙা/আধা-আপডেটেড পেজ না দেখে, এই ক্রম মেনে চলুন:

1. **অ্যাডমিন → সিস্টেম → মেইনটেন্যান্স মোড চালু করুন।**
   ভিজিটররা "রক্ষণাবেষণ চলছে" পেজ দেখবে; আপনি (অ্যাডমিন) সব পেজ দেখতে পারবেন।
2. সার্ভারে নতুন কোড আনুন:
   ```bash
   git pull            # অথবা
   git am patch-XX-*.patch
   ```
3. প্যাচে নতুন প্যাকেজ থাকলে (composer.json বদলালে):
   ```bash
   composer install --no-dev --optimize-autoloader
   ```
4. **সিস্টেম পেজে "ডাটাবেস আপডেট চালান"** বোতাম চাপুন
   (টার্মিনাল থাকলে `php artisan migrate --force`ও চলে)।
5. **"সব ক্যাশে" পরিষ্কার করুন।**
6. সাইট ঘুরে দেখুন (আপনি মেইনটেন্যান্সে থাকা অবস্থাতেই পারবেন)। সব ঠিক থাকলে
   **মেইনটেন্যান্স মোড বন্ধ করুন** — সাইট আবার সবার জন্য লাইভ।

কিছু গোলমালে গেলে রোলব্যাক:

```bash
git revert <কমিট>        # অথবা আগের স্থিতিশীল কমিটে checkout
```

তারপর ধাপ ৪–৬ আবার চালান। ডাটাবেস বদলে গেলে সিস্টেম পেজ থেকে সর্বশেষ
ব্যাকআপ রিস্টোর করুন।

## ২.৫) অটোমেটিক টেস্ট (CI) · Automatic tests on every push

GitHub Actions এখন প্রতিটি `push` ও pull request-এ পুরো টেস্ট-স্যুট দুই ভাষায়
(বাংলা + English) নিজে চালায়। রিপোর **Actions** ট্যাবে প্রতিটি কমিটের পাশে সবুজ ✓
বা লাল ✗ দেখবেন। নিয়ম সহজ: **লাল (ব্যর্থ) কমিট কখনো লাইভ সার্ভারে দেবেন না।**

আরও শক্ত সুরক্ষা চাইলে: GitHub → Settings → Branches → **Add branch ruleset**
→ branch `main` → "Require status checks to pass" চালু করে `Tests` সিলেক্ট করুন।
তাহলে টেস্ট ফেল করা কোড আর main-এ ঢুকতেই পারবে না।

## ৩) সাইট বন্ধ হলে সঙ্গে সঙ্গে জানুন · Uptime monitoring

সাইটে `/up` নামে একটি হেলথ এন্ডপয়েন্ট আছে — সব ঠিক থাকলে এটি HTTP 200 দেয়।

1. [UptimeRobot](https://uptimerobot.com) (ফ্রি)-এ অ্যাকাউন্ট খুলুন।
2. নতুন মনিটর: ধরন **HTTP(s)**, URL = `https://your-domain.com/up`,
   চেক ৫ মিনিট অন্তর।
3. অ্যালার্টে আপনার ইমেইল দিন — সাইট ডাউন হলেই ৫ মিনিটের মধ্যে খবর পাবেন।

## ৪) ব্যাকআপ রুটিন · Backup routine

- **সপ্তাহে অন্তত একবার** অ্যাডমিন → সিস্টেম → ব্যাকআপ তৈরি + ডাউনলোড করুন।
- বড় আপডেটের **আগে** অবশ্যই একটি ব্যাকআপ নিন।
- ব্যাকআপে পুরো ডাটাবেস ও `.env` থাকে — এটি কারও সাথে শেয়ার করবেন না;
  নিজের কম্পিউটার/নিরাপদ ক্লাউড ড্রাইভে রাখুন।
- রিস্টোর করতে: সিস্টেম পেজে ব্যাকআপ zip আপলোড করে "রিকভারি শুরু করুন"।
  রিস্টোরের পর নিরাপত্তার জন্য আপনাকে নতুন করে লগইন করতে হতে পারে।

---

## English summary

- **Before going live:** `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://…`,
  enable HTTPS, then open **Admin → System** (`/admin/system`) and make sure
  every health check shows ✓. Take a first backup.
- **Adding features later:** turn ON maintenance mode (visitors see a friendly
  page, admins keep working) → `git pull` / `git am` the patch →
  `composer install --no-dev` if packages changed → press **Run database
  updates** → **Clear all caches** → turn maintenance OFF. Roll back with
  `git revert` (+ restore the last backup if the database changed).
- **Monitoring:** point a free UptimeRobot HTTP monitor at `/up` — you get an
  email within minutes if the site ever goes down.
- **Backups:** create + download one at least weekly and before every big
  change; keep it private (it contains the database and `.env`). Restore from
  the same System page; you may need to log in again afterwards.


---

## ৫) সাবডোমেইনে লাইভ করা · Going live on a subdomain

আপাতত `road.your-domain.com`-এর মতো সাবডোমেইনে চালানোর ধাপ:

1. **cPanel → Subdomains** থেকে সাবডোমেইন বানান; Document Root দিন
   প্রজেক্টের `public` ফোল্ডারে (যেমন: `/home/user/road/public`)।
   ⚠️ Document Root কখনোই প্রজেক্টের মূল ফোল্ডার নয় — তাহলে `.env` সহ
   সব ফাইল পাবলিক হয়ে যাবে!
2. **SSL চালু করুন:** cPanel → SSL/TLS Status → *Run AutoSSL* (ফ্রি)।
   HTTPS ছাড়া পুশ নোটিফিকেশন ও লগইন-নিরাপত্তা কাজ করবে না।
3. `.env`-এ ঠিক করুন:
   ```
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://road.your-domain.com
   ```
4. প্রথমবার ডিপ্লয়ে টার্মিনালে:
   `php artisan migrate --force` → `php artisan storage:link` →
   `php artisan config:cache`
5. **Cron যোগ করুন** (cPanel → Cron Jobs, প্রতি মিনিটে):
   `* * * * * cd /home/user/road && php artisan schedule:run >> /dev/null 2>&1`
   — এটা চালু হলে অটো-ব্যাকআপ ও সাপ্তাহিক ডাইজেস্ট কাজ করবে।
6. **যাচাই:** অ্যাডমিন → সিস্টেম পেজে সব হেলথ-চেক ✓ হবে — বিশেষ করে
   "ক্রন/শিডিউলার চলছে" (cron সেট হলে ১০ মিনিটের মধ্যে সবুজ হবে)।
7. **পরে আসল ডোমেইন কিনলে:** ডোমেইনটি একই `public` ফোল্ডারে পয়েন্ট করান,
   `.env`-এ `APP_URL` বদলে `php artisan config:cache` চালান — ব্যাস।
   সব ডেটা, ইউজার, সেটিং অপরিবর্তিত থাকবে। সাবডোমেইন থেকে পুরনো ভিজিটরদের
   নতুন ডোমেইনে পাঠাতে চাইলে হোস্টিং-এ redirect বসান।

### English — subdomain quick steps

Create the subdomain with its **document root at the project's `public/`
folder** (never the project root), enable free AutoSSL, set
`APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://sub.example.com`,
run `migrate --force`, `storage:link`, `config:cache`, add the
`* * * * * php artisan schedule:run` cron, then confirm every health check
on **Admin → System** turns green. Moving to a full domain later only
requires pointing the domain at the same folder and updating `APP_URL`.
