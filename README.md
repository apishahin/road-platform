# Road Platform

[![Tests](https://github.com/apishahin/road-platform/actions/workflows/tests.yml/badge.svg)](https://github.com/apishahin/road-platform/actions/workflows/tests.yml)

সড়ক-সমস্যা প্রতিবেদনের জন্য নাগরিক প্ল্যাটফর্ম — নাগরিকরা ছবিসহ সমস্যা জমা দেন, কমিউনিটি যাচাই করে, কর্তৃপক্ষ মেরামত সম্পন্ন করে। A citizen platform for reporting and tracking road problems — report with photos, community verification, authority follow-up, live statistics and donation support. Bengali-first, English supported.

## সহায়তা নথিগুলো · Docs

- **[DEPLOYMENT.md](DEPLOYMENT.md)** — প্রকাশনা, নিরাপদ আপডেট, ব্যাকআপ ও মনিটরিং গাইড (বাংলা + English)
- অ্যাডমিন প্যানেল: **অ্যাডমিন → সিস্টেম** (`/admin/system`) — সার্ভার তথ্য, স্বাস্থ্য পরীক্ষা, ক্যাশে, ব্যাকআপ/রিকভারি, ডাটাবেস আপডেট

## চালানো · Run

```bash
composer install
cp .env.example .env && php artisan key:generate
php artisan migrate --force
php artisan serve
```

## টেস্ট · Tests

```bash
php artisan test                 # English
APP_LOCALE=bn php artisan test   # বাংলা
```

প্রতিটি `git push`-এ GitHub Actions নিজে দুই ভাষায় পুরো স্যুট চালায় — লাল হলে সেই কমিট লাইভে দেবেন না।
Every push runs the full suite in both languages via GitHub Actions — never deploy a red commit.
