# EcoTrade: bukti langkah 3, 4 dan 5

## Account and listing usability update

- 18 schema checks, 77 HTTP prototype checks and 42 browser checks passed.
- Verified the password visibility control, incomplete email rejection, profile photo upload and removal, and sign-in using updated email and password. Credential updates rejected a wrong current password and an email already in use.
- Verified the participant avatar, listing cover and listing title in the conversation header, then sent and received messages from separate student sessions at 320px.
- The new administrator signed in and reached the dashboard after the demo admin was retired. Listing, Profile and chat were checked on narrow layouts. The listing form uses visible native choices, and the eye control preserves the accessible label and keyboard focus.

## English interface, admin, chat and photos update

- Database and HTTP checks passed: 18 schema checks and 74 prototype checks. The new cases cover the admin landing page, role denial, session revocation, category availability, two-way chat, archived conversations and monthly CSV totals.
- Browser checks passed: 34 checks across registration, login, listing editing, invalid photo rejection, two-image upload and gallery navigation, including views at 320px to 1440px. The admin navigation, report filters, CSV download and logout also worked at 320px with JavaScript disabled.
- Google authorization buttons, PKCE redirect, invalid state rejection and cancellation passed with a local test configuration. Live Google sign-in requires the project's OAuth client.
- The antislop check used the existing catalogue direction. Interface copy is English, admin figures come from database queries, gallery images come from uploads, forms and carousel controls use native HTML, and short transitions respect the reduced-motion preference.

The original phase 3 to 5 results below describe the prototype before this update.

Tarikh: 2 November 2026, Asia/Kuala_Lumpur. Diuji pada PHP 8.2.12, MariaDB 10.4.32 dan Edge headless melalui Playwright yang tersedia pada laptop.

## Hasil

| Bahagian | Hasil sebenar |
| --- | --- |
| Integrasi HTTP | **49 PASS**, menggunakan dua akaun QA dengan cookie berasingan. |
| Klik browser | **29 PASS**, termasuk borang, navigasi, tema dan saiz skrin. |
| Kontras | **40 PASS**, meliputi teks, placeholder, error, butang utama, border input dan fokus pada tema cerah/gelap. |
| Regression schema | **18 PASS**, enam table dan lapan foreign key masih berfungsi. |
| Kegagalan database | HTML dan JSON menetapkan 503 dengan mesej bantuan; SQLSTATE hanya dalam log terminal. |
| Syntax | Semua fail PHP lulus `php -l`; `scripts/check.ps1` lulus parser PowerShell. |
| Data QA | Akaun, kategori dan listing ujian sementara dibersihkan selepas ujian. Seed asal dikekalkan. |

## Authentication yang diuji

- Pendaftaran berjaya; password pendek, confirmation salah dan email pendua ditolak.
- Field `role=admin` palsu diabaikan: akaun yang didaftar melalui web kekal student.
- Password dalam database ialah hash yang boleh disemak oleh `password_verify()`.
- Password login salah dan akaun tidak aktif ditolak.
- Login sah mencipta token rawak; hanya hash token disimpan dalam `user_sessions`.
- Cookie menggunakan HttpOnly dan SameSite=Lax. Secure cookie boleh diaktifkan melalui HTTPS atau `COOKIE_SECURE=1` semasa deployment.
- Session tamat tidak boleh membuka Barang saya.
- Logout memadam session dan cookie; replay token lama ditolak.
- GET logout ditolak dengan 405. POST tanpa CSRF ditolak dengan 403.

## CRUDS dan permission yang diuji

- Guest yang membuka tambah barang dihantar ke login.
- Tambah barang menyimpan title, description, category, condition, listing type dan source language, dengan gambar NULL.
- `owner_id` palsu dalam POST diabaikan; pemilik diambil daripada session.
- Kategori tidak wujud/tidak aktif dan keadaan barang tidak sah ditolak dengan 422.
- Data HTML seperti `<script>` dipaparkan sebagai teks, bukan dieksekusi.
- Carian tajuk/penerangan dan penapis kategori/keadaan/jenis memberi hasil yang betul. Teks berbentuk SQL tidak mengubah query.
- Pemilik boleh edit, dan perubahan dibaca semula daripada database. Cache terjemahan lama dipadam selepas perubahan penerangan/bahasa sumber.
- Student B menerima 403 untuk GET edit, POST edit, GET archive dan POST archive barang A, walaupun menggunakan CSRF B yang sah.
- Percubaan B tidak mengubah data A. Barang saya B tidak mengandungi barang A; butang edit/archive tidak dipaparkan kepada B.
- Barang reserved tidak boleh diedit atau diarkibkan. Status reserved digunakan sebagai fixture ujian, bukan melalui workflow request yang belum dibina.
- GET pengesahan archive tidak mengubah data. POST archive sah mengubah status kepada archived dan mengekalkan row.
- Barang archived tidak muncul dalam carian umum, kekal dalam penapis archived milik pemilik dan memberi 404 kepada bukan pemilik.

## Rekod klik browser

| Kawalan / tindakan | Perkara yang diperhatikan |
| --- | --- |
| Tab, Enter pada skip link | Fokus berpindah ke main content. |
| Mod gelap, Mod cerah | Tema berubah dan state butang dikemas kini. |
| Daftar akaun, submit kosong | Validation native browser menghalang submit kosong. |
| Submit confirmation salah | Ralat yang jelas dipaparkan di borang. |
| Submit pendaftaran sah | Halaman login dan mesej berjaya dipaparkan. |
| Login salah, login sah | Ralat dipaparkan; kemudian akaun dibawa ke Barang saya. |
| Barang saya akaun baru | Empty state menunjukkan belum ada barang. |
| Tambah barang, semua select, Simpan barang | Detail dibuka; data kekal selepas reload. |
| Edit barang, Batal, Edit barang semula, Simpan perubahan | Batal kembali ke detail; perubahan sah memaparkan title baharu. |
| Cari barang, keyword, kategori, keadaan, jenis | Hasil dan empty state sepadan dengan penapis. |
| Reset carian | Keyword dan penapis dikosongkan. |
| Arkibkan barang, Batal, pengesahan semula | Batal mengekalkan barang; pengesahan mengarkibkannya. |
| Status archived di Barang saya | Rekod archived masih kelihatan kepada pemilik. |
| Halaman seterusnya, sebelumnya | Pagination berfungsi dengan 13 listing QA sementara. |
| Log keluar | Halaman login dibuka. |
| Link antara register/login dan nama EcoTrade | Destinasi yang betul dibuka. |

Paparan diuji pada 320, 390, 768, 1024 dan 1440px, termasuk tema gelap. Tiada horizontal overflow. Borang create dan hasil carian juga diuji pada mobile. Ujian 200% saiz teks pada 320px menemui overflow di butang/reset carian; `flex-wrap` ditambah dan keseluruhan ujian browser lulus semula. Tiada JavaScript page error sepanjang aliran tersebut.

Screenshot sebenar: `.local/screenshots/browse-desktop.png`, `browse-dark.png`, `browse-mobile.png` dan `create-mobile.png`. Screenshot browse dikemas kini selepas pembersihan QA supaya hanya data demo asal dipaparkan.

## Delivery Gate antislop

- **Hard Gate PASS:** aliran aplikasi dijalankan melalui HTTP dan browser; control menggunakan elemen native, label serta fokus yang kelihatan. Empty/loading/error disediakan. Kontras diukur; layout mobile dan teks 200% lulus. Tiada statistik pemasaran, testimonial, logo rekaan atau pautan ke fungsi yang belum wujud.
- **Purpose-Gate PASS:** keputusan warna, typography, layout, spacing dan metadata ditulis dalam `DESIGN.md`, berdasarkan arah hijau gelap + kertas yang dipilih pengguna. Tiada gradients, glow, glass, ilustrasi atau gerakan hiasan.
- **Liveliness PASS:** ENERGY 1 / RHYTHM 2 / MOTION 1 dinyatakan sebelum pembinaan. Bahasa katalog buku hadir melalui serif headings, warna kertas, kategori dan baris listing; heading, carian, form dan detail mempunyai hierarki yang berbeza. Accent hijau menandakan tindakan utama.
- **Craftsmanship PASS:** setiap control yang muncul dalam flow demo diklik, pagination diuji dengan fixture tambahan, tema cerah/gelap disemak dan screenshot diperiksa. Query menggunakan parameter, permission dikuatkuasakan di server dan archive mengekalkan rekod. Ujian merekodkan data sebenar daripada database.

## Ulang ujian

Database port 3307 dan PHP port 8000 perlu berjalan:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\check.ps1 -Prototype
& 'C:\xampp\php\php.exe' .\tools\check-http.php
& 'C:\xampp\php\php.exe' .\tools\check-page-error.php
```

`check-page-error.php` memang mencetak ralat sambungan yang dijangka dalam log sebelum baris PASS.

Browser QA menggunakan `tools/check-ui.cjs` dengan package Playwright dalam runtime Codex. Ia bukan dependency untuk menjalankan aplikasi. Pada laptop ini, command QA ialah:

```powershell
$env:NODE_PATH = "$env:USERPROFILE\.cache\codex-runtimes\codex-primary-runtime\dependencies\node\node_modules"
& "$env:USERPROFILE\.cache\codex-runtimes\codex-primary-runtime\dependencies\node\bin\node.exe" .\tools\check-ui.cjs
& "$env:USERPROFILE\.cache\codex-runtimes\codex-primary-runtime\dependencies\python\python.exe" -X utf8 .\tools\check-contrast.py
```

Jalankan ujian QA pada database demo tempatan. `tools/cleanup-qa.php` hanya menerima corak email QA rawak dan digunakan oleh ujian browser untuk membersihkan rekodnya sendiri.

## Skop yang masih akan datang

Deployment AWS, workflow donation/exchange requests, pickup, Translate API, reports dan pengurusan admin belum dibina. Prototype belum mempunyai pemulihan password, pengesahan email atau rate limiting login. Tiada dakwaan readiness production, pensijilan aksesibiliti atau skor performance dibuat. Ujian di atas ialah bukti fungsi tempatan dan semakan UI khusus yang disenaraikan.
