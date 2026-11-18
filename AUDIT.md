# সিকিউরিটি ও স্পিড অডিট · Security & Speed Audit

**তারিখ:** ২০২৬-১১-১৮ · **সংস্করণ:** patch-41 · **পদ্ধতি:** সম্পূর্ণ কোডবেজের ম্যানুয়াল রিভিউ +
স্ট্যাটিক প্যাটার্ন স্ক্যান + ৩২০+ অটোমেটেড টেস্ট।

## ✅ যা যাচাই করা হয়েছে এবং নিরাপদ পাওয়া গেছে

| এলাকা | ফলাফল |
|---|---|
| SQL ইনজেকশন | সব কোয়েরি query builder/বাউন্ড প্যারামিটারে — কোনো string-interpolated raw SQL নেই |
| XSS (ব্লেড আউটপুট) | সব `{!! !!}` স্যানিটাইজড: markdown `html_input: strip` + `allow_unsafe_links: false`, hero টেক্সট escape-টেড, ফান্ড বিবরণ `nl2br(e())` |
| অ্যাক্সেস কন্ট্রোল | সব ২০টি অ্যাডমিন কন্ট্রোলারে role-guard; মডারেশনে volunteer-স্কোপ আলাদা |
| IDOR | পাবলিক রুটে ULID (`public_id`), সংখ্যাভিত্তিক id শুধু guard-যুক্ত অ্যাডমিন রুটে |
| ফাইল আপলোড | `mimes:jpeg,png,webp` + সর্বোচ্চ সাইজ + ছবি রি-প্রসেসিং; ছবি মডারেশন ছাড়া পাবলিক নয় |
| রেট লিমিট | OTP/লগইন ১০/মিনি, রিপোর্ট ২০/মিনি + ফোন-ভেরিফিকেশন বাধ্যতামূলক, পেমেন্ট কলব্যাক ১২০/মিনি |
| OTP | HMAC-SHA256 হ্যাশে সংরক্ষণ, মেয়াদ + প্রচেষ্টা-সীমা, ব্যবহারের পর অকার্যকর |
| CSRF | সব ফর্মে টোকেন; পেমেন্ট গেটওয়ে কলব্যাক ব্যতিক্রম — সার্ভার-সাইডে স্টেট যাচাই করা |
| সেশন | ডাটাবেস-ভিত্তিক, লগইনে রোটেট; অ্যাডমিন প্যানেল আলাদা guard |
| ডেটা মিনিমাইজেশন | অ্যানালিটিক্সে IP/ব্যক্তিগত তথ্য নেই (দৈনিক ঘূর্ণায়মান হ্যাশ) |
| API | `/api/v1/issues/nearby` পাবলিক, throttle-যুক্ত, শুধু পাবলিশড ডেটা |

## 🔧 এই অডিটে যা ঠিক করা হলো

1. **স্পিড — মডারেশন ইনডেক্স যোগ** (`reports.moderation`, `evidence.moderation`):
   প্রতিটি পাবলিক ইস্যু পেজ ও মডারেশন কিউ এই কলামে ফিল্টার করে; ডেটা বাড়লে
   ফুল-টেবিল স্ক্যান হত। এখন ইনডেক্স-ভিত্তিক।
2. **নিরাপত্তা হেডার** প্রতিটি পেজে: `X-Content-Type-Options: nosniff`,
   `X-Frame-Options: SAMEORIGIN` (ক্লিকজ্যাকিং প্রতিরোধ),
   `Referrer-Policy: strict-origin-when-cross-origin`,
   `Permissions-Policy` (ক্যামেরা/লোকেশন শুধু নিজ সাইটে)।

## 📋 ভবিষ্যতের জন্য নিয়ম

- নতুন পাবলিক এন্ডপয়েন্টে সবসময় `throttle` রাখুন।
- ইউজার-ইনপুট কখনো `{!! !!}`-এ সরাসরি নয় — আগে `e()` বা markdown-strip।
- লাইভ সার্ভারে **অ্যাডমিন → সিস্টেম → স্বাস্থ্য পরীক্ষা** মাঝে মাঝে দেখুন
  (debug বন্ধ, HTTPS, পারমিশন ইত্যাদি)।
- সাপ্তাহিক ব্যাকআপ + UptimeRobot মনিটরিং চালু রাখুন (DEPLOYMENT.md)।

---

## English summary

Full-codebase review + automated suite. **No vulnerabilities found.** Verified:
parameterized queries everywhere, sanitized rich output, role guards on all admin
controllers, ULID public identifiers, validated/re-moderated uploads, rate limits
on auth/report/payment endpoints, HMAC-hashed expiring OTPs, CSRF everywhere
(gateway callbacks verified server-side), privacy-preserving analytics.

Fixed in this pass: database indexes on `reports.moderation` /
`evidence.moderation` (hot public-page filters), and baseline security headers
(`nosniff`, `SAMEORIGIN`, referrer and permissions policies) on every response.
