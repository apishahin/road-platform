# 🚀 cPanel শেয়ার্ড হোস্টিং-এ ডিপ্লয় গাইড (সাবডোমেইন)

এই গাইডে `road.example.com`-এর জায়গায় আপনার আসল সাবডোমেইন আর
`cpaneluser`-এর জায়গায় আপনার cPanel ইউজারনেম বসান। সব ধাপ File Manager +
cPanel দিয়েই করা যায় — SSH লাগলে তার বিকল্পও দেওয়া আছে।

---

## ধাপ ০ — হোস্টিং যাচাই (২ মিনিট)

1. cPanel → **Select PHP Version** (বা MultiPHP Manager) → সাবডোমেইনের জন্য
   **PHP 8.2 বা 8.3** বেছে নিন।
2. একই জায়গায় extensions চালু আছে কিনা দেখুন:
   `gd, mbstring, pdo_mysql, fileinfo, zip, openssl, ctype, json, tokenizer, xml, curl`
   (বেশিরভাগ ডিফল্ট চালুই থাকে)

---

## ধাপ ১ — সাবডোমেইন তৈরি

1. cPanel → **Domains** (বা Subdomains) → **Create A New Domain/Subdomain**।
2. নাম: `road.example.com`
3. **Document Root:** `/home/cpaneluser/road-app/public` ← ⚠️ গুরুত্বপূর্ণ!
   প্রজেক্টের **মূল ফোল্ডার নয়, `public` ফোল্ডারই** হবে — নাহলে `.env` সহ
   সব ফাইল ইন্টারনেটে উন্মুক্ত হয়ে যাবে।
4. "Share document root with public_html" চেকবক্স **আনটিক** রাখুন।

---

## ধাপ ২ — ডাটাবেস তৈরি

1. cPanel → **MySQL® Databases** → নতুন ডাটাবেস: যেমন `cpaneluser_road`
2. একই পেজে নতুন ইউজার: যেমন `cpaneluser_road` + শক্ত পাসওয়ার্ড (নোট করে রাখুন)
3. **Add User To Database** → দুটো বেছে → **ALL PRIVILEGES** দিন।

---

## ধাপ ৩ — কোড আপলোড

### উপায় ক: SSH/Terminal থাকলে (সবচেয়ে সহজ)

cPanel → **Terminal** খুলুন (না থাকলে হোস্টিং প্রোভাইডারকে SSH চাইতে বলুন):

```bash
cd /home/cpaneluser
git clone https://github.com/apishahin/road-platform.git road-app
cd road-app
composer install --no-dev --optimize-autoloader
```

> ⚠️ রিপো **private** হলে: GitHub → Settings → Developer settings →
> Personal access token (read:repo) বানিয়ে
> `git clone https://<TOKEN>@github.com/apishahin/road-platform.git road-app`

### উপায় খ: SSH নেই (শুধু File Manager)

1. নিজের পিসিতে (XAMPP ফোল্ডারে) PowerShell খুলুন:
   ```powershell
   cd F:\xampp\htdocs\road-platform
   composer install --no-dev --optimize-autoloader
   ```
2. পুরো ফোল্ডারটি zip করুন (`.git` ফোল্ডার বাদ দিলে ছোট হবে)।
3. cPanel → **File Manager** → `/home/cpaneluser/` → Upload → `road.zip`
4. Extract করে ফোল্ডারের নাম দিন `road-app` (ডকরুটের সাথে মিলিয়ে:
   `/home/cpaneluser/road-app/public` থাকা লাগবে)।

---

## ধাপ ৪ — `.env` সেটআপ

File Manager → `/home/cpaneluser/road-app/` → `.env.example` কপি করে
নাম দিন `.env` → Edit করে বসান:

```env
APP_NAME="Road Platform"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://road.example.com

APP_KEY=          # ← নিচে দেখুন

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=cpaneluser_road
DB_USERNAME=cpaneluser_road
DB_PASSWORD=যে_পাসওয়ার্ড_দিয়েছেন
```

**APP_KEY বানানো (SSH ছাড়াই):** নিজের পিসিতে চালান —
```powershell
cd F:\xampp\htdocs\road-platform
php artisan key:generate --show
```
যা আসবে (`base64:....`) তা কপি করে সার্ভারের `.env`-এ `APP_KEY=` পরে বসান।

---

## ধাপ ৫ — ডাটাবেস মাইগ্রেশন + স্টোরেজ লিংক (একবারের কমান্ড)

### SSH/Terminal থাকলে:

```bash
cd /home/cpaneluser/road-app
php artisan migrate --force
php artisan storage:link
php artisan config:cache
```

### SSH না থাকলে — cPanel Cron দিয়ে একবার চালান:

cPanel → **Cron Jobs** → "Once per minute" (`* * * * *`) সিলেক্ট করে
কমান্ডে এই **তিনটা আলাদা আলাদা** cron যোগ করুন (PHP-র পাথ cPanel →
Select PHP Version পেজে লেখা থাকে, সাধারণত এমন হয়):

```
cd /home/cpaneluser/road-app && /usr/local/bin/ea-php82 php artisan migrate --force >> /home/cpaneluser/setup.log 2>&1
```
```
cd /home/cpaneluser/road-app && /usr/local/bin/ea-php82 php artisan storage:link >> /home/cpaneluser/setup.log 2>&1
```
```
cd /home/cpaneluser/road-app && /usr/local/bin/ea-php82 php artisan config:cache >> /home/cpaneluser/setup.log 2>&1
```

২–৩ মিনিট অপেক্ষা করুন, `/home/cpaneluser/setup.log` ফাইল File Manager-এ
খুলে "Migration table created / The link has been created" দেখলে **এই তিনটি
cron মুছে দিন**।

---

## ধাপ ৬ — স্থায়ী Cron (সবসময় রাখতে হবে!)

Cron Jobs → প্রতি মিনিটে:

```
* * * * * cd /home/cpaneluser/road-app && /usr/local/bin/ea-php82 php artisan schedule:run >> /dev/null 2>&1
```

এটি ছাড়া অটো-ব্যাকআপ ও সাপ্তাহিক ডাইজেস্ট চলবে না। সেট হলে অ্যাডমিন →
সিস্টেম পেজে **"ক্রন / শিডিউলার চলছে"** ১০ মিনিটের মধ্যে সবুজ হবে।

---

## ধাপ ৭ — SSL (ফ্রি)

1. cPanel → **SSL/TLS Status** → সাবডোমেইন টিক দিয়ে **Run AutoSSL**।
2. ১–২ মিনিটে সবুজ তালা আসবে।
3. cPanel → **Domains** → সাবডোমেইনে **Force HTTPS Redirect: On**।

---

## ধাপ ৮ — যাচাই ✅

1. ব্রাউজারে `https://road.example.com/up` → `{"status":"up"}`-র মতো উত্তর।
2. `https://road.example.com` খুলে রেজিস্ট্রেশন করে অ্যাডমিন বানান
   (প্রথম ইউজারকে অ্যাডমিন করতে ডাটাবেসে `role` = `admin` দিন — cPanel →
   **phpMyAdmin** → `users` টেবিল → এডিট)।
3. **অ্যাডমিন → সিস্টেম** পেজে সব হেলথ-চেক ✓ সবুজ কিনা দেখুন।
4. প্রথম **ব্যাকআপ** নিয়ে ডাউনলোড করুন (সিস্টেম পেজেই)।

---

## ⚠️ সমস্যা হলে (সবচেয়ে কমন ৫টি)

| লক্ষণ | কারণ ও সমাধান |
|---|---|
| **500 Server Error** | `.env`-এ ভুল আছে বা PHP পুরনো। লগ: File Manager → `road-app/storage/logs/laravel.log` |
| **শুধু হোমপেজ চলে, অন্য পেজ 404** | `public/.htaccess` ফাইল আপলোড হয়নি — File Manager-ে "Show hidden files" চালু করে দেখুন |
| **ছবি আপলোড/দেখায় না** | `storage:link` হয়নি (ধাপ ৫) অথবা `storage` ফোল্ডারের permission 755 নয় |
| **সাদা পেজ** | PHP 7.x চলছে — 8.2+ করুন (ধাপ ০) |
| **লগইনেই ঢোকে না / 419** | `APP_URL` ভুল বা HTTPS ফোর্স হয়নি (ধাপ ৭) |

## পরে আপডেট (নতুন প্যাচ এলে)

1. অ্যাডমিন → সিস্টেম → **মেইনটেন্যান্স মোড চালু**
2. SSH থাকলে: `cd road-app && git pull` — না থাকলে: নতুন ফাইলগুলো File
   Manager দিয়ে আপলোড (প্যাচে যেসব ফাইল বদলেছে)
3. সিস্টেম পেজে **"ডাটাবেস আপডেট চালান"** (যদি মাইগ্রেশন থাকে) → **ক্যাশ পরিষ্কার**
4. মেইনটেন্যান্স বন্ধ
