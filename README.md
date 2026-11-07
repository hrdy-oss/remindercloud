# Pengingatku – Pengingat Jadwal & Tugas SMK

Pengingat jadwal dan tugas untuk siswa SMK. Aplikasi ini menggunakan PHP 8.1+
native dengan PDO MySQL. SQLite juga tersedia melalui pengaturan
`PENGINGAT_DB_DRIVER=sqlite`.

## Menjalankan aplikasi

Jalankan server bawaan PHP dari folder proyek:

```sh
php -S localhost:8000
```

Atau salin proyek ke `/var/www/html/pengingat-neo`.

Layar dengan lebar minimal 900px memakai sidebar dan tata letak dua kolom.
Layar yang lebih kecil memakai menu pil di bawah.

## Database Ubuntu

Pastikan MySQL atau MariaDB berjalan. Buat database dan pengguna aplikasi dengan
menjalankan perintah berikut dari folder proyek:

```sh
sudo mysql < scripts/setup_mysql.sql
```

Masukkan kata sandi sudo Ubuntu saat diminta. Kredensial pengguna database
disetel di `app/database.local.php`, yaitu file lokal yang tidak disertakan di Git.

Aplikasi membuat tabel secara otomatis saat pertama kali dijalankan. Untuk
membuat akun admin tanpa kata sandi bawaan, jalankan perintah ini dari folder
proyek:

```sh
php scripts/set_admin_password.php
```

Perintah ini meminta email dan kata sandi secara tersembunyi di terminal,
mewajibkan kata sandi minimal 14 karakter, lalu membuat akun admin atau
mengganti kata sandi akun tersebut. Gunakan email asli yang akan dipakai untuk
masuk. Jika akun placeholder awal masih ada dan email yang dimasukkan berbeda,
akun placeholder itu dinonaktifkan. Jalankan juga pada database lama untuk
mengganti kredensial admin awal yang sebelumnya menggunakan kata sandi bawaan.
Pastikan perintah memakai konfigurasi dan database yang sama dengan aplikasi.

## Akun admin

Dashboard admin tersedia untuk peran `admin` dan `superadmin`. Atur variabel
lingkungan `PENGINGAT_SUPERADMIN_EMAIL` sebelum menjalankan aplikasi untuk
menentukan email superadmin, misalnya:

```sh
PENGINGAT_SUPERADMIN_EMAIL=admin@contoh.id php -S localhost:8000
```

Email tersebut akan mendapat peran superadmin jika sudah terdaftar, atau ketika
akun dengan email itu dibuat. Pendaftar lain otomatis menjadi user.

Admin dapat mencari akun, mengubah nama dan email, menonaktifkan akun, serta
menghapus akun user. Superadmin juga dapat mengubah peran akun. Pastikan selalu
ada setidaknya satu superadmin.

## Verifikasi email pendaftaran

Akun baru belum bisa masuk sampai pemilik alamat email membuka tautan verifikasi.
Kata sandi dibuat setelah tautan dibuka, jadi orang yang mendaftarkan alamat
milik orang lain tidak bisa menentukan kata sandi akun tersebut.

Atur variabel lingkungan berikut pada PHP server:

- `PENGINGAT_BASE_URL`, URL situs lengkap tanpa garis miring di akhir, misalnya
  `https://pengingat.contoh.id` atau `http://localhost/pengingat-neo`.
- `PENGINGAT_SMTP_USER`, alamat Gmail yang akan mengirim email, misalnya
  `akun@gmail.com`.
- `PENGINGAT_SMTP_APP_PASSWORD`, App Password Google untuk alamat tersebut.

Pengiriman memakai Gmail SMTP (`smtp.gmail.com`, STARTTLS port 587) melalui PHP
cURL. Aktifkan Verifikasi 2 Langkah di akun Google, lalu buat App Password
khusus aplikasi ini. Simpan App Password hanya di environment server; jangan
masukkan ke kode atau chat. Untuk situs publik, gunakan URL HTTPS. Pengguna dapat
meminta tautan baru dari halaman masuk. Akun yang sudah ada tetap dianggap
terverifikasi.

Di Apache Ubuntu dengan `mod_php`, buat konfigurasi lokal yang tidak disimpan di
proyek, misalnya `/etc/apache2/conf-available/pengingat-mail.conf`:

```apache
SetEnv PENGINGAT_BASE_URL "https://domain-situsmu.id"
SetEnv PENGINGAT_SMTP_USER "alamatgmailmu@gmail.com"
SetEnv PENGINGAT_SMTP_APP_PASSWORD "app-password-dari-google"
```

Batasi izin file konfigurasi itu, aktifkan dengan `sudo a2enconf
pengingat-mail`, lalu muat ulang Apache. Jika memakai PHP-FPM atau hosting
panel, set variabel yang sama di konfigurasi environment PHP yang digunakan
oleh aplikasi.

# Google Drive Storage setup

Storage uses the Google Drive account connected by an app admin. Site users upload through the app and do not authorize Google. The app keeps file visibility in its database and serves downloads only to the uploader, an explicitly shared site account, or an admin.

1. Enable **Google Drive API** in the Google Cloud project.
2. Configure the OAuth consent screen as **External / Testing** and add the admin's Google account under **Test users**.
3. Create or use an OAuth client whose application type is **Web application**. Add the exact callback URI to **Authorized redirect URIs** (`http://localhost/pengingat-neo/google-drive-callback.php` for this local installation).
4. Set `PENGINGAT_GOOGLE_CLIENT_ID`, `PENGINGAT_GOOGLE_CLIENT_SECRET`, and `PENGINGAT_GOOGLE_REDIRECT_URI` in the server environment. Environment values take priority over the ignored local fallback file. Never put the secret in browser code or commit it.
5. Sign in to Pengingatku as an admin and click **Hubungkan Google Drive admin** on Storage. Approve the requested Drive access once. The first upload creates a `Pengingatku Uploads` folder in the connected account, and later uploads go into that folder. To use a different folder, set `PENGINGAT_GOOGLE_DRIVE_FOLDER_ID` to its ID; it must be accessible to this connected app under its `drive.file` permission.

The OAuth secret is a server credential. If it has been stored in source files or shared, reset it in the Google Cloud Console, update the environment variable, then remove `app/google_drive.local.php` from the server. Google notes that resetting a client secret causes a temporary outage while the new secret is deployed: [Google Cloud credential reset guidance](https://docs.cloud.google.com/docs/security/compromised-credentials?hl=en).

For a deployed site, use the exact HTTPS callback URL in both Google Cloud Console and `PENGINGAT_GOOGLE_REDIRECT_URI`. PHP cURL must be enabled. InfinityFree free hosting limits PHP uploads to 20 MB, so the app caps uploads at 19 MB to leave room for multipart form data. Files are sent to Google Drive and are not stored on the hosting account.
