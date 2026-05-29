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
