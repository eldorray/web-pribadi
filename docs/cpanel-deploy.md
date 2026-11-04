# Deploy Laravel ke cPanel Shared Hosting

Project ini adalah Laravel 13, Livewire 4, dan Vite. Hosting harus menyediakan PHP 8.3 atau lebih baru. Jika cPanel hanya menyediakan PHP 8.2, project ini perlu downgrade dependency Laravel terlebih dahulu.

## Cara yang disarankan

Jika cPanel mengizinkan pengaturan document root domain/subdomain, arahkan document root ke:

```text
/home/USERNAME/path-ke-project/public
```

Dengan cara ini, file Laravel seperti `.env`, `vendor`, `storage`, dan `bootstrap` tidak berada langsung di web root.

## Jika document root harus `public_html`

Upload seluruh isi project ke `public_html`. File `.htaccess` di root project akan mengarahkan request ke folder `public/`, sedangkan `public/.htaccess` menangani routing Laravel.

## Persiapan lokal sebelum upload

Jalankan dari root project:

```bash
npm run build
composer install --no-dev --optimize-autoloader
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

Pastikan folder berikut ikut ter-upload:

```text
app
bootstrap
config
database
public
resources
routes
storage
vendor
artisan
composer.json
composer.lock
.htaccess
```

Jangan upload file/folder ini:

```text
.env lokal
.git
node_modules
tests
.DS_Store
```

## File `.env` di hosting

Di hosting, buat file `.env` dari `.env.cpanel.example`, lalu sesuaikan:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://domain-anda.com

DB_CONNECTION=mysql
DB_HOST=localhost
DB_DATABASE=nama_database_cpanel
DB_USERNAME=user_database_cpanel
DB_PASSWORD=password_database
```

Generate `APP_KEY` di lokal atau terminal hosting:

```bash
php artisan key:generate --show
```

Salin hasilnya ke `APP_KEY=` pada `.env` hosting.

## Setelah upload

Jalankan lewat Terminal cPanel/SSH jika tersedia:

```bash
php artisan migrate --force
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Jika `php artisan storage:link` gagal karena shared hosting tidak mengizinkan symlink, buat folder `public/storage` dari File Manager lalu salin isi `storage/app/public` ke sana setiap kali ada upload aset yang perlu dipublikasikan.

## Jika muncul `Please provide a valid cache path`

Error ini berarti folder cache Laravel belum ada atau tidak bisa ditulis oleh PHP. Jalankan dari root project di hosting:

```bash
mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/testing storage/framework/views bootstrap/cache
chmod -R 775 storage bootstrap/cache
php artisan optimize:clear
```

Setelah itu ulangi:

```bash
php artisan migrate --force
```

## Jika seeder gagal karena `Data too long for column 'image'`

Upload migration terbaru, lalu jalankan:

```bash
php artisan migrate --force
php artisan db:seed --force
php artisan optimize:clear
```

Error ini terjadi saat database MySQL memakai batas lama `VARCHAR(255)` untuk kolom `projects.image`, sementara data sample memakai URL gambar yang panjang.

## Permission

Pastikan folder ini writable oleh PHP:

```text
storage
bootstrap/cache
```

Umumnya permission yang cukup:

```text
folder: 755
file: 644
```

Jika cache/log gagal ditulis, set `storage` dan `bootstrap/cache` ke `775` dari File Manager cPanel.

## Catatan admin

Setelah migrasi dan seeding, login admin ada di:

```text
/login
```

Segera ganti password admin setelah deploy.

## Chat AI (`/chat`)

Halaman chat pribadi ini butuh tiga hal di hosting:

1. **Dependency baru.** `composer install --no-dev --optimize-autoloader` akan memasang
   `smalot/pdfparser` (PHP murni, tanpa ekstensi tambahan). Jika `vendor` di-upload manual,
   pastikan folder `vendor/smalot/pdfparser` ikut.
2. **`storage/app/private` harus bisa ditulis.** Lampiran dan gambar hasil generate disimpan
   di sana lewat disk `local` — bukan di `public`. Tidak perlu `storage:link` untuk chat.
   Set folder ke `775` bila upload lampiran gagal.
3. **Streaming.** Jawaban dikirim bertahap (`X-Accel-Buffering: no` sudah dikirim Livewire).
   Jika hosting tetap mem-buffer respons sehingga jawaban baru muncul sekaligus di akhir,
   matikan **Streaming** di `/admin/chat-ai` — chat tetap berfungsi, hanya tidak bertahap.

Setelah deploy, isi di `/admin/chat-ai`: base URL (`…/v1`), API key, klik **Tes koneksi**,
lalu **Ambil model** dan aktifkan model yang dipakai. Tambahkan pengguna chat beserta PIN
6 angka. Halaman `/chat` sengaja tidak ditautkan dari navigasi portfolio dan memakai
`noindex`.

Pembuatan gambar bisa memakan waktu; request-nya menaikkan `set_time_limit(180)` sendiri.
Kalau penyedia menolak `/images/generations`, ganti **Mode buat gambar** ke
`/chat/completions (modalities)`.

## Penilaian (Chat AI)

Setelah upload, jalankan `php artisan migrate`.

- `storage/app/private` harus bisa ditulis, karena tugas mahasiswa dan potongan gambar video disimpan di sana, di luar URL publik.
- `upload_max_filesize` dan `post_max_size` menentukan batas ukuran gambar dan dokumen (paling besar 10 MB) serta ukuran potongan suara video. Halaman Penilaian membaca nilainya sendiri. Kalau kecil, video tetap jalan dengan potongan yang lebih pendek.
- PHP dan web server perlu mengizinkan request sampai sekitar 180 detik, untuk transkripsi dan penilaian.
- Tidak perlu `ffmpeg` atau queue worker. Video diolah di browser dosen, jadi halaman harus tetap terbuka selama proses.
- Di admin → Chat AI, centang **Aktif** dan **Transkripsi** pada satu model transkripsi, misalnya `amanai/grok-voice-transcribe-2.0`. Tanpa itu, video hanya bisa dinilai dengan transkrip yang ditempel manual.

### Menilai dari link di file Excel

Hosting biasanya diblokir YouTube, jadi video diunduh dari komputer dosen. Dua skrip ini dijalankan di komputer dosen, tidak perlu di-upload ke hosting.

```bash
python3 scripts/grading-download.py nilai.xlsx
python3 scripts/grading-fill.py nilai.xlsx hasil-ekspor.csv
```

Skrip pertama membuat folder `nilai-video/<sheet>/` berisi `<NIM> - <Nama>.mp4`, yang lalu diunggah ke sesi Penilaian. Skrip kedua mengisi salinan file Excel dari CSV ekspor, dicocokkan lewat NIM. Keduanya butuh `pip install openpyxl`, dan skrip pertama juga butuh `yt-dlp` dan `ffmpeg`.

