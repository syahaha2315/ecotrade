# EcoTrade prototype

Langkah 1 hingga 5 tersedia: database enam table ikut ERD, register/login/logout, listing CRUDS dan permission pemilik. Buka [EcoTrade tempatan](http://127.0.0.1:8000/) untuk guna aplikasi. Endpoint JSON sambungan database kini berada di [health.php](http://127.0.0.1:8000/health.php). AWS belum dideploy.

Jika database dan PHP sudah berjalan daripada sesi sebelumnya, **refresh browser sahaja**. Tiada migration atau setup semula diperlukan untuk langkah 3, 4 dan 5.

## Cuba flow demo Progress 2

1. Klik **Daftar akaun**. Isi nama, email dan password sekurang-kurangnya 10 aksara. Ulang password yang sama. Akaun baharu sentiasa berperanan student.
2. Selepas berjaya, halaman **Log masuk** dibuka. Cuba password salah dahulu untuk lihat ralat, kemudian log masuk dengan password betul.
3. Buka **Tambah barang**. Contoh tajuk: `Buku Matematik Demo`. Pilih kategori, keadaan, jenis perkongsian dan bahasa. Isi penerangan sekurang-kurangnya 20 aksara, kemudian **Simpan barang**.
4. Buka **Cari barang**, cari tajuk tadi dan cuba penapis kategori/keadaan/jenis. Klik tajuk atau **Lihat barang** untuk membuka detail.
5. Pada barang sendiri, klik **Edit barang**, ubah maklumat dan **Simpan perubahan**. Refresh halaman untuk semak perubahan masih ada.
6. Semak permission menggunakan browser private/incognito supaya session A dan B berasingan. Log masuk sebagai `student.b@ecotrade.test` dengan password `EcoTradeDemo!2026`. Cuba URL edit milik akaun pertama (`/?page=edit&id=ID_BARANG`). Permintaan akan menerima **403**, termasuk POST jika seseorang cuba menghantar borang sendiri. Akaun B juga tidak boleh archive barang itu.
7. Kembali ke browser pemilik. Klik **Arkibkan barang** dan sahkan **Ya, arkibkan barang**. Barang hilang daripada carian umum; pemilik boleh lihat rekodnya dalam **Barang saya**, penapis **Status: Diarkibkan**. Archive ialah tindakan akhir untuk prototype ini; fungsi restore belum disediakan.
8. Klik **Log keluar**. Halaman dilindungi kini memerlukan login semula.

`Sumbangan` dan `Pertukaran` sekarang ialah jenis listing. Workflow menghantar/menerima request dan pickup akan ditambah kemudian. Gambar masih optional dan tiada upload gambar dalam fasa ini.

Ujian automatik tambahan, selepas database dan PHP port 8000 berjalan:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\check.ps1 -Prototype
```

Ia menjalankan 18 semakan database dan 49 semakan HTTP prototype. Ujian mencipta dua akaun QA rawak dan kategori sementara, kemudian membersihkan rekod ujian sendiri. Bukti ujian dan Delivery Gate terkini ada dalam [docs/PROTOTYPE-TESTS.md](docs/PROTOTYPE-TESTS.md).

Folder projek kekal di `C:\Users\Syakir\Desktop\Bitu3923\Eco trade`. Tidak perlu pindah ke `htdocs`, pasang Composer atau hidupkan Apache. PHP built-in server digunakan untuk demo tempatan.

## Mula dekat laptop kau

PHP dan MariaDB sudah tersedia dalam `C:\xampp`. Semasa semakan laptop ini, instance MySQL asal XAMPP gagal startup dengan ralat InnoDB. Prototype ini menggunakan data directory sendiri dalam `.local/mysql` pada **port 3307**, dengan binary MariaDB XAMPP yang sama. Data directory XAMPP asal tidak digunakan untuk demo ini. Tidak perlu tekan Start MySQL dalam XAMPP Control Panel.

1. Buka folder projek dalam VS Code. Pilih **Terminal > New Terminal** dan gunakan PowerShell.
2. Dalam **terminal pertama**, hidupkan database:

   ```powershell
   Set-Location 'C:\Users\Syakir\Desktop\Bitu3923\Eco trade'
   powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\start-db.ps1
   ```

   Kali pertama, script mencipta data directory. Tunggu mesej `ready for connections` dan `port: 3307`. Biarkan terminal ini terbuka. Jika tertera `Database EcoTrade sudah berjalan`, instance projek ini sudah hidup; teruskan tanpa start lagi.

3. Buka **terminal kedua** dengan butang `+` dalam panel terminal VS Code. Salin dua command ini, satu demi satu:

   ```powershell
   Set-Location 'C:\Users\Syakir\Desktop\Bitu3923\Eco trade'
   powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\setup.ps1
   ```

   Hasil yang dijangka:

   ```text
   SIAP: database ecotrade, enam table dan data demo tersedia.
   ```

   Instance projek ini dicipta dengan akaun admin `root` tanpa password dan hanya mendengar pada `127.0.0.1`. Ini untuk latihan tempatan sahaja. Aplikasi menggunakan akaun berasingan dengan password rawak. Jika kau menetapkan password root instance projek kemudian, gunakan command ini dan masukkan password apabila diminta:

   ```powershell
   powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\setup.ps1 -AskAdminPassword
   ```

4. Semak sambungan dan kekangan database:

   ```powershell
   powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\check.ps1
   ```

   Setiap baris sepatutnya bermula dengan `PASS`, diikuti jumlah semakan lulus. Rekod ujian sementara di-rollback selepas semakan. Nombor auto-increment mungkin melangkau selepas ujian, itu normal.

5. Hidupkan aplikasi PHP:

   ```powershell
   powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\serve.ps1
   ```

6. Biarkan terminal itu terbuka. Buka [EcoTrade tempatan](http://127.0.0.1:8000/) dalam browser untuk melihat halaman carian. Untuk semakan sambungan database sahaja, buka [health.php](http://127.0.0.1:8000/health.php). Endpoint itu memulangkan JSON seperti ini:

   ```json
   {
       "application": "EcoTrade",
       "stage": "Semakan sambungan database EcoTrade",
       "status": "connected",
       "message": "PHP berjaya membaca kategori daripada database.",
       "categories": [
           { "category_id": 1, "category_name": "Buku" },
           { "category_id": 2, "category_name": "Kalkulator" },
           { "category_id": 3, "category_name": "Learning Tools" }
       ]
   }
   ```

   Nombor ID boleh berbeza jika database pernah digunakan. JSON ini ialah hasil query database sebenar; halaman carian, borang dan detail menggunakan sambungan PDO yang sama.

Tekan **Ctrl+C** dalam terminal server PHP untuk hentikan PHP. Untuk hentikan database dengan kemas, jalankan command ini di terminal lain:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\stop-db.ps1
```

Selepas restart laptop, jalankan `start-db.ps1` dalam terminal pertama dan `serve.ps1` dalam terminal kedua. Setup tidak perlu diulang setiap kali. **Jangan padam `.local/mysql`**, sebab itulah data database yang kekal. Jangan share folder itu; ia mungkin mengandungi data peribadi apabila aplikasi berkembang.

`ExecutionPolicy Bypass` di atas terpakai pada proses PowerShell tersebut sahaja; ia tidak mengubah polisi Windows secara kekal.

## Cara buktikan perubahan memang tersimpan

Selepas server berjalan, buka terminal PowerShell kedua:

```powershell
& 'C:\xampp\mysql\bin\mysql.exe' --no-defaults --host=127.0.0.1 --port=3307 --user=root ecotrade
```

Jika root ada password, tambah `-p` sebelum `ecotrade`. Dalam prompt MariaDB, jalankan:

```sql
SHOW TABLES;
SELECT category_id, category_name, is_active FROM categories;
UPDATE categories SET category_name = 'Buku Rujukan' WHERE category_name = 'Buku';
```

Refresh browser di `/health.php` atau halaman carian. Nama kategori akan bertukar kepada `Buku Rujukan`. Hentikan PHP dengan Ctrl+C, jalankan semula `serve.ps1` dan refresh: nama itu masih tersimpan. Pulihkan data demo sebelum presentation:

```sql
UPDATE categories SET category_name = 'Buku' WHERE category_name = 'Buku Rujukan';
exit;
```

Jangan jalankan setup ketika nama kategori demo diubah: seed mencari kategori berdasarkan nama.

## Apa yang setup buat

- Cipta database `ecotrade` jika belum wujud.
- Cipta enam table dengan InnoDB, UTF-8 (`utf8mb4`), primary key, unique key, lapan foreign key dan CHECK constraints.
- Cipta akaun aplikasi `ecotrade_app` untuk localhost, dengan akses `SELECT`, `INSERT`, `UPDATE`, `DELETE` pada database ini sahaja. Akaun root digunakan semasa setup.
- Jana password database rawak dan simpan dalam `config/local.php`. Fail ini diabaikan Git dan berada di luar folder web `public`.
- Tambah tiga kategori, tiga akaun demo dan dua listing demo tanpa gambar. Password akaun demo diproses dengan `password_hash()`.

| Akaun demo | Peranan | Password awal |
| --- | --- | --- |
| `admin@ecotrade.test` | admin | `EcoTradeDemo!2026` |
| `student.a@ecotrade.test` | student | `EcoTradeDemo!2026` |
| `student.b@ecotrade.test` | student | `EcoTradeDemo!2026` |

Akaun ini data ujian tempatan dan boleh digunakan pada halaman Log masuk. Password demo ini berbeza daripada password rawak akaun database `ecotrade_app`. Email tidak dihantar atau disahkan dalam prototype ini.

Setup boleh diulang: rekod dengan email, nama kategori dan pasangan pemilik/tajuk demo yang sama tidak digandakan; password dan maklumat rekod sedia ada tidak direset. Ia bukan alat migration dan tidak mengubah struktur table sedia ada. Jangan guna pada database projek lain atau database production. Jangan padam `config/local.php` selepas setup, sebab ia menyimpan password akaun database.

## Enam table mengikut ERD

| Table | Tujuan dan hubungan |
| --- | --- |
| `users` | Akaun admin/student, email unik, hash password, bahasa dan status aktif. |
| `categories` | Kategori listing dan status aktif. |
| `listings` | Pemilik merujuk `users`, kategori merujuk `categories`. Gambar `image_s3_key` boleh NULL. |
| `requests` | Requester merujuk `users`; target dan offered item merujuk `listings`. Offered item boleh NULL. |
| `translations` | Merujuk satu listing ATAU satu request. Satu cache bagi parent, field dan bahasa sasaran. |
| `user_sessions` | Hash session, user dan masa tamat; disediakan untuk authentication kemudian. |

Semua kolum dalam `erd new.png` disertakan. Bahasa menggunakan kod `en` dan `ms`. Timestamp aplikasi disimpan sebagai UTC; pickup dan timestamp boleh ditukar kepada Asia/Kuala_Lumpur pada paparan kemudian.

Nilai status dan condition ialah pilihan implementasi prototype:

- Listing: `available`, `reserved`, `completed`, `archived`.
- Request: `pending`, `accepted`, `rejected`, `cancelled`, `completed`.
- Condition: `new`, `like_new`, `good`, `fair`.
- Jenis listing: `donation`, `exchange`.

Foreign key menggunakan `ON DELETE RESTRICT` supaya rekod yang dirujuk kekal. Archive mengubah `listings.status` kepada `archived` tanpa memadam rekod. Hanya pemilik boleh edit/archive, dan hanya apabila status masih `available`; reserved/completed/archived ditolak dengan 409.

Peraturan workflow request yang melibatkan beberapa table masih perlu dibina kemudian: requester tidak boleh meminta item sendiri; offered item mesti milik requester; donation mesti tiada offered item; exchange mesti ada offered item; penerimaan request mesti reserve item secara atomik; completion memerlukan pengesahan kedua-dua pihak. Authentication dan ownership permission untuk listing sudah dilaksanakan.

`source_hash` untuk cache akan menggabungkan bahasa sumber dan teks asal. API terjemahan belum dibina. Apabila penerangan atau bahasa sumber listing diedit, cache terjemahan listing itu dipadam dalam transaksi yang sama untuk mengelakkan teks lama digunakan semula.

Session login menggunakan token rawak dengan tempoh lapan jam. Hanya hash token disimpan dalam `user_sessions`. Cookie HttpOnly dan SameSite=Lax digunakan; logout memadam row session supaya token lama tidak boleh digunakan semula. Semua operasi POST memerlukan token CSRF yang ditandatangani dan diikat kepada session. Password dihash menggunakan `password_hash()` dan disemak dengan `password_verify()`; had semasa ialah 72 bait.

## Fail yang kau akan guna

| Fail | Fungsi |
| --- | --- |
| `database/schema.sql` | SQL enam table; boleh dibaca untuk explain ERD. |
| `database/seed.php` | Data demo dan hashing password. |
| `app/database.php` | Sambungan PDO untuk authentication, listing dan health check. |
| `app/auth.php` | Register, verification password, session database dan CSRF. |
| `app/listings.php` | Carian, CRUDS, semakan pemilik dan transaksi edit/archive. |
| `app/controller.php` | Routing dan validation permintaan GET/POST. |
| `app/views/` | Halaman PHP dengan output HTML yang di-escape. |
| `public/index.php` | Halaman aplikasi. |
| `public/health.php` | Endpoint JSON sambungan database dan kategori aktif. |
| `public/assets/` | CSS responsive dan JavaScript untuk tema/loading. |
| `tools/setup.php` | Cipta database, table, akaun aplikasi dan data demo melalui CLI. |
| `tools/check.php` | Uji sambungan, struktur dan constraint menggunakan transaksi sementara. |
| `tools/check-prototype.php` | 49 ujian HTTP dengan dua akaun QA berasingan. |
| `scripts/*.ps1` | Command ringkas untuk setup, semak dan jalankan server. |
| `config/local.php` | Config peribadi yang dijana oleh setup. Jangan share atau commit. |
| `.local/mysql` | Data database berasingan untuk projek ini. Jangan padam atau commit. |

Environment variables `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER` dan `DB_PASSWORD` mengatasi nilai dalam `config/local.php`. Ini memudahkan konfigurasi deployment kemudian. Setup automatik ini khusus untuk localhost; TLS, secrets dan konfigurasi RDS akan disediakan dalam langkah AWS. Apabila aplikasi disediakan di belakang HTTPS/ALB nanti, `COOKIE_SECURE=1` perlu ditetapkan bersama konfigurasi HTTPS yang diuji.

## Kalau ada masalah

| Mesej / keadaan | Apa perlu buat |
| --- | --- |
| `Connection refused`, `10061`, atau HTTP 503 | Pastikan terminal `start-db.ps1` masih terbuka dengan mesej `ready for connections`, kemudian jalankan setup jika belum. |
| `ibdata1 must be writable` | Fail mungkin sedang digunakan oleh instance database yang sudah hidup. Jalankan `start-db.ps1` yang terkini; jika ia menyatakan database sudah berjalan, teruskan dengan check atau browser. Jangan padam `ibdata1`. |
| `Access denied for user 'root'` | Jalankan setup dengan `-AskAdminPassword`. Untuk admin selain root, tambah `-AdminUser nama_akaun`. |
| `Access denied for user 'ecotrade_app'` | Semak password dalam `config/local.php`. Setup tidak mereset akaun database yang telah wujud; jangan padam fail config atau reset akaun secara rawak. |
| `could not find driver` / `pdo_mysql belum aktif` | Guna PHP XAMPP: tambah `-PhpPath 'C:\xampp\php\php.exe'` pada script. Semak extension `pdo_mysql` dalam php.ini. |
| Port 8000 sudah digunakan | Jalankan `serve.ps1 -Port 8001`, kemudian buka `http://127.0.0.1:8001/`. |
| Port 3307 sudah digunakan | Semak sama ada database projek sudah berjalan. Jika perlu port lain, guna `start-db.ps1 -Port 3308`, ubah `port` dalam `config/local.php` kepada 3308, dan guna `stop-db.ps1 -Port 3308`. Jika setup pertama belum dibuat, tetapkan `$env:DB_PORT = '3308'` sebelum setup. |
| JSON `categories` kosong | Sambungan berjaya tetapi tiada kategori aktif. Semak `SELECT * FROM categories;`. |
| Browser tunjuk kod PHP atau minta download | Buka URL server `http://127.0.0.1:8000/`, bukan fail PHP melalui File Explorer. |
| XAMPP bukan dalam C:\xampp | Tambah `-PhpPath 'lokasi\php.exe'` pada setup/check/serve dan `-MySqlBin 'lokasi\mysql\bin'` pada start-db/stop-db. |
| Database berhenti tiba-tiba / ralat InnoDB | Simpan log ralat dan buat salinan data sebelum kerja recovery. Jangan gantikan atau padam `ibdata1`, redo log atau folder mysql. |

Jika setup gagal separuh jalan, punca ralat dipaparkan dalam terminal; table yang sudah dicipta kekal kerana DDL tidak di-rollback. Selesaikan punca dan ulang setup. Jika `ecotrade_app` sudah dicipta tetapi fail config gagal ditulis, password perlu dipulihkan oleh admin database sebelum mencuba lagi.

## Rujukan teknikal

[PHP PDO](https://www.php.net/manual/en/book.pdo.php), [PDO prepared statements](https://www.php.net/manual/en/pdo.prepare.php), [MariaDB constraints](https://mariadb.com/docs/server/reference/sql-statements/data-definition/constraint), [initialize instance MariaDB Windows](https://mariadb.com/docs/server/server-management/install-and-upgrade-mariadb/installing-mariadb/installing-system-tables-mariadb-install-db/mariadb-install-db-exe). Schema disasarkan kepada MariaDB XAMPP dan MySQL 8.0.16+; ujian RDS sebenar akan dibuat semasa langkah deployment.
