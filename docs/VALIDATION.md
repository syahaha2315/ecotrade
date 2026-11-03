# Bukti ujian EcoTrade, langkah 1 dan 2

Ini rekod ujian fasa awal. Halaman aplikasi kini tersedia di `/`; endpoint JSON berpindah ke `/health.php`. Laporan semasa untuk langkah 3 hingga 5 ialah [PROTOTYPE-TESTS.md](PROTOTYPE-TESTS.md).

Tarikh ujian: 2 November 2026 (Asia/Kuala_Lumpur).

Runtime sebenar: PHP 8.2.12 dan MariaDB 10.4.32 daripada XAMPP. Database projek menggunakan `127.0.0.1:3307`, dengan data directory `.local/mysql`. PHP menggunakan `127.0.0.1:8000` dan document root `public`.

## Hasil yang sudah diuji

| Semakan | Hasil |
| --- | --- |
| Syntax PHP | PASS: semua fail PHP aplikasi dan tools lulus `php -l`. |
| Syntax PowerShell | PASS: enam script lulus parser PowerShell. |
| Setup sebenar | PASS: database, enam table, akaun aplikasi dan seed berjaya dicipta. |
| `tools/check.php` | PASS: 18 semakan database lulus. |
| HTTP GET `/` | PASS: HTTP 200, `status: connected`, kategori Buku, Kalkulator dan Learning Tools dibaca. |
| HTTP POST `/` | PASS: HTTP 405, endpoint baca sahaja. |
| HTTP `/config/local.php` | PASS: HTTP 404, config tidak disajikan oleh document root. |
| HTTP `/database/schema.sql` | PASS: HTTP 404. |
| HTTP `/tools/setup.php` | PASS: HTTP 404, setup tidak tersedia melalui web. |
| Database tidak dapat dicapai | PASS: `tools/check-error.php` memaksa port tertutup; endpoint menetapkan HTTP 503 dan JSON bantuan. SQLSTATE hanya dicatat dalam log terminal, tidak masuk respons JSON. |
| Perubahan data sebenar | PASS: nama kategori sementara dibaca oleh sambungan PDO baharu dan endpoint HTTP. |
| Keadaan kosong | PASS: selepas kategori dinyahaktifkan sementara, endpoint memberi `categories: []` dan mesej tiada kategori aktif. |
| Pemulihan data ujian | PASS: nama dan status kategori dipulihkan dalam `finally`. |
| Setup diulang | PASS: jumlah kekal 3 users, 3 categories, 2 listings, 0 requests, 0 translations, 0 sessions. |
| Permission database | PASS: aplikasi menggunakan `ecotrade_app`; grant data ialah SELECT, INSERT, UPDATE, DELETE pada `ecotrade` sahaja. |
| Stop/start database | PASS: `stop-db.ps1` diikuti `start-db.ps1` berjaya; GET masih HTTP 200, dan jumlah kekal 3 users, 3 categories, 2 listings. |
| Start ketika database sudah berjalan | PASS: script menyemak data directory instance pada port 3307, memaparkan `Database EcoTrade sudah berjalan` dan keluar tanpa melancarkan instance kedua. Syntax script dan lima semakan HTTP lulus selepas pembaikan. |

18 semakan database meliputi sambungan PDO, enam table, lapan foreign key, kategori aktif, akaun selain root, format hash password, email unik, UTF-8 dan gambar NULL, kategori asing tidak wujud, sekatan pemadaman pemilik yang dirujuk, target/offered item berbeza, cache terjemahan unik, parent terjemahan eksklusif, field terjemahan mengikut parent, masa tamat session dan rollback rekod sementara.

## Delivery Gate antislop

- Hard Gate PASS untuk skop backend: aplikasi benar-benar dijalankan; HTTP 200/405/404 dan kegagalan 503 disemak. Tiada logo, statistik pemasaran, navigasi atau kawalan UI dicipta. Respons kategori kosong diuji.
- Purpose-Gate PASS untuk skop backend: deliverable ialah schema, CLI dan endpoint JSON. Teknik warna, font, kad, ikon, animasi dan layout tidak digunakan; keputusan format JSON membolehkan pembacaan database dibuktikan sebelum membina halaman pengguna.
- Liveliness PASS untuk skop backend: tiada reka bentuk visual dalam langkah ini; Design Read, dials dan motif visual tidak terpakai. Ini bukan kelulusan reka bentuk UI EcoTrade.
- Craftsmanship PASS untuk skop backend: script setup/check/start/stop dijalankan, seed diuji semula tanpa pendua, data diuji selepas restart, dan panduan disesuaikan dengan instance port 3307 yang digunakan.

Semakan klik, mobile, fokus keyboard, contrast dan loading indicator tidak terpakai kepada endpoint JSON/CLI ini. Semua itu perlu diuji apabila halaman pengguna dibina. Tiada skor prestasi browser atau dakwaan kesiapan production dibuat.

## Ulang semakan

Jalankan database dan PHP mengikut README. Dalam terminal lain pada folder projek:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\check.ps1
& 'C:\xampp\php\php.exe' .\tools\check-http.php
& 'C:\xampp\php\php.exe' .\tools\check-error.php
```

Ujian integrasi berikut mengubah nama dan status kategori secara sementara, kemudian memulihkannya. Jalankan pada data demo tempatan apabila tiada orang lain sedang mengedit kategori:

```powershell
& 'C:\xampp\php\php.exe' .\tools\check-data-flow.php
```

`check-error.php` memang mencetak ralat sambungan yang dijangka dalam log terminal. Kejayaan ujian ditunjukkan oleh baris PASS selepasnya.

## Batas skop

Authentication, kebenaran pemilik listing, CRUDS, workflow donation/exchange, pickup, Translate, reports dan AWS belum dilaksanakan. MySQL/RDS belum diuji; hasil di atas khusus untuk runtime MariaDB laptop ini. Semua kolum ERD disediakan, tetapi peraturan workflow yang melibatkan beberapa table masih memerlukan transaksi backend kemudian.
