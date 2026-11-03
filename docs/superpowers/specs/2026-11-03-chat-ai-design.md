# Chat AI — desain

## Maksud

Halaman chat AI pribadi untuk dua orang (pemilik situs dan istrinya), tampil dan terasa seperti Claude web: daftar percakapan di kiri, chat di tengah, kotak input di bawah dengan pilihan model dan effort. Bisa membuat gambar dan menerima lampiran PDF, Word, dan gambar. Penyedia AI bebas, selama API-nya OpenAI-compatible; base URL dan API key diatur dari admin, bukan dari kode.

Alasannya: satu tempat chat AI milik sendiri, dengan penyedia yang bisa diganti kapan saja tanpa deploy ulang.

## Sasaran

- Halaman `/chat` di situs publik, dikunci PIN. Tiap orang punya PIN sendiri dan riwayat chat terpisah.
- Pengaturan di admin: base URL, API key, daftar model, dan pengguna chat beserta PIN-nya.
- Jawaban muncul bertahap (streaming), dirender sebagai markdown.
- Pilihan model dan effort per pesan.
- Mode "Buat gambar" yang memakai model gambar; hasilnya tampil di chat dan bisa diunduh.
- Lampiran: gambar (dikirim ke model vision), PDF dan `.docx` (teksnya diekstrak di server).

## Bukan sasaran (Fase ini)

Web search, tool calling, berbagi percakapan, input suara, pelacakan biaya/token, OCR untuk PDF hasil scan, `.doc` lama, Excel/PowerPoint, edit pesan lama, lebih dari satu penyedia sekaligus, judul percakapan yang dibuat AI.

## Anti-sasaran (gagal walau "jalan")

- API key bocor ke browser (HTML, payload Livewire, JS) atau ke log.
- Satu orang bisa melihat percakapan atau file milik orang lain.
- PIN bisa ditebak dengan brute-force.
- Halaman chat terindeks mesin pencari atau muncul di navigasi portfolio.
- File lampiran bisa diakses lewat URL publik tanpa PIN.

## Batasan

- Stack yang ada: Laravel 13, Livewire 4.2, Blade, Tailwind 4. Tidak ada Vue/Inertia. Ikuti pola komponen Livewire di `app/Livewire/Admin`.
- Hosting cPanel shared (`docs/cpanel-deploy.md`): tidak ada queue worker yang berjalan terus, dan web server bisa mem-buffer respons. Semua panggilan AI berjalan sinkron di request. Pembuatan gambar bisa lama, jadi request tersebut menaikkan `set_time_limit` (mis. 180 detik).
- Hanya satu dependency baru: `smalot/pdfparser` (PHP murni). `.docx` diekstrak dengan `ZipArchive` bawaan. Markdown memakai `Str::markdown()` (league/commonmark sudah terpasang).

## Keputusan

### Akses dan PIN

- Tabel `chat_users`: `name`, `pin` (di-hash dengan `Hash::make`). Dikelola di admin (tambah, ganti PIN, hapus). Panjang PIN 6 digit.
- Login: satu input PIN. Server mencocokkan PIN dengan setiap `chat_users` (hanya dua baris, jadi loop `Hash::check` tidak masalah).
- Rate limit: 5 percobaan per menit per IP lewat `RateLimiter`, pesan yang jelas saat terkunci.
- Setelah PIN benar, `chat_user_id` disimpan di cookie terenkripsi (cookie Laravel terenkripsi secara default) berumur 30 hari. Ada tombol keluar. Mengganti PIN di admin harus membuat cookie lama tidak berlaku — simpan juga hash pendek/versi PIN di cookie dan cocokkan. *(keputusan saya)*
- Middleware `ChatPin` melindungi semua route chat; semua query percakapan selalu lewat relasi `chatUser->conversations()` sehingga data orang lain tidak bisa diambil.
- Route `/chat` di luar grup `TrackVisitor`, kirim `<meta name="robots" content="noindex">`, tidak ditautkan dari navbar.

### Pengaturan di admin

Menu baru "Chat AI" di sidebar admin (`/admin/chat-ai`), satu halaman Livewire dengan tiga bagian:

1. **Koneksi:** `ai_base_url` dan `ai_api_key` disimpan lewat `SiteSetting` (group `ai`). API key dienkripsi dengan `Crypt::encryptString` sebelum disimpan, dan form hanya menampilkan `••••` serta mengganti nilainya kalau field diisi. Tombol "Tes koneksi" memanggil `GET {base_url}/models`.
   - Penting: `Admin\Settings\General` saat ini memuat dan menyimpan ulang **semua** baris `SiteSetting`, dan `FrontendController` mem-pluck semuanya ke view publik. Keduanya harus mengecualikan group `ai`.
2. **Model:** tabel `ai_models`: `model_id`, `label`, `is_enabled`, `is_default`, `supports_effort`, `supports_vision`, `is_image`. Tombol "Ambil model" mengisi daftar dari `GET /models`; model juga bisa ditambah manual jika endpoint itu tidak ada.
3. **Pengguna chat:** nama dan PIN.

### Pemanggilan API

Satu class `App\Support\AiClient` (atau service sejenis) yang membungkus `Http` Laravel dengan base URL dan bearer token dari settings:

- Chat: `POST /chat/completions` dengan `stream: true`, membaca SSE (`data: {...}`) baris demi baris dan meneruskan potongan `choices[0].delta.content`.
- `reasoning_effort` (`low` / `medium` / `high`) hanya dikirim jika model diberi tanda `supports_effort`; selain itu pilihan effort disembunyikan di UI.
- Gambar: penyedia yang dipakai adalah penyedia custom OpenAI-compatible, jadi endpoint gambarnya belum pasti. Admin punya setting `ai_image_mode` dengan dua pilihan:
  - `images` (default): `POST /images/generations` (`model`, `prompt`, `size: 1024x1024`, `n: 1`). Terima `b64_json` maupun `url`.
  - `chat`: `POST /chat/completions` dengan `modalities: ["image", "text"]`, mengambil gambar dari `choices[0].message.images[].image_url.url` (data URL atau URL biasa).
  Hasil keduanya disimpan ke disk lokal private. Tombol "Tes koneksi" juga bisa dipakai untuk mencoba buat gambar kecil agar pemilik tahu mode mana yang cocok.
- Riwayat: kirim seluruh pesan percakapan, dipotong ke pesan terbaru jika melebihi batas karakter (mis. ~100 ribu karakter). *(keputusan saya; batas bisa disesuaikan builder)*
- Gambar lampiran hanya dikirim penuh (base64) untuk pesan **terbaru**; di pesan lama diganti teks `[gambar: nama-file]` agar payload tidak membengkak. Gambar hasil generate tidak dikirim balik ke model. *(keputusan saya)*
- Mode gambar: prompt = isi pesan saat ini saja, tanpa konteks percakapan. "Buat lebih biru" tidak tahu gambar sebelumnya — batasan yang disadari. *(keputusan saya)*
- Model reasoning yang mengirim `delta.reasoning_content`: isinya tidak ditampilkan, UI cukup menunjukkan indikator "Sedang berpikir…" sampai `content` mulai datang. *(keputusan saya)*
- Tombol "Berhenti": browser membatalkan request; loop stream memeriksa `connection_aborted()` dan berhenti, lalu teks parsial yang sudah diterima disimpan sebagai jawaban (ditandai terpotong). *(keputusan saya)*
- Error dari API (401, 404 model, timeout) ditampilkan sebagai gelembung error di chat dengan tombol "Coba lagi". API key tidak pernah ikut dalam pesan error atau log.

### Streaming

Pakai `$this->stream()` / `wire:stream` Livewire. Selama streaming, teks mentah ditampilkan; setelah selesai, pesan disimpan dan dirender sebagai markdown. Karena cPanel bisa mem-buffer respons, kirim header `X-Accel-Buffering: no` dan sediakan setting `ai_streaming` (on/off) di admin; kalau off, jawaban muncul sekaligus setelah selesai.

### Lampiran

- Diterima: jpg, jpeg, png, webp, pdf, docx. Maks 10 MB per file, 5 file per pesan. Validasi dengan aturan `mimes` + `max` Laravel (mime dicek dari isi file).
- Disimpan di disk `local` (private) di `chat/{chat_user_id}/…`, dilayani lewat route yang dilindungi `ChatPin` dan mengecek pemilik.
- Gambar: dikirim sebagai konten `image_url` base64. Tombol lampirkan gambar mati jika model yang dipilih tidak `supports_vision`.
- PDF/DOCX: teks diekstrak sekali saat unggah dan disimpan bersama metadata lampiran, lalu disisipkan ke pesan pengguna sebagai blok teks berlabel nama file. PDF tanpa teks → pesan "PDF ini tidak berisi teks yang bisa dibaca".

### Data

- `chat_conversations`: `chat_user_id`, `title`, timestamps. Judul = 50 karakter pertama pesan pertama; bisa diganti nama.
- `chat_messages`: `chat_conversation_id`, `role` (`user`/`assistant`), `content` (teks), `model`, `effort` (nullable), `image_path` (nullable, untuk gambar hasil generate), `attachments` (JSON: path, nama, mime, teks hasil ekstrak), `is_error` (bool), timestamps.
- Hapus percakapan = hapus pesan dan file-filenya dari disk.

### Tampilan

Mengikuti tata letak Claude web, tetapi memakai palet dan font situs yang sudah ada (bukan meniru merek Claude):

- **Desktop:** sidebar kiri (tombol "Chat baru", daftar percakapan terbaru di atas, ganti nama/hapus, nama pengguna + keluar di bawah). Area tengah dengan kolom pesan selebar ~720px. Pesan pengguna sebagai gelembung di kanan; jawaban AI tanpa gelembung, teks penuh.
- **Kotak input** menempel di bawah: textarea yang membesar otomatis (Enter kirim, Shift+Enter baris baru), tombol lampiran (menampilkan chip file dengan tombol hapus), dropdown model, dropdown effort, tombol mode "Buat gambar", tombol kirim/berhenti.
- **Jawaban:** markdown dengan `html_input: strip` dan `allow_unsafe_links: false`; blok kode punya tombol salin. Gambar hasil generate tampil di chat dengan tombol unduh.
- **Layar kosong:** sapaan dengan nama pengguna dan kotak input di tengah.
- **Mobile:** sidebar menjadi drawer; kotak input tetap di bawah, input 16px agar tidak zoom.
- **Halaman PIN:** kartu sederhana dengan satu input numerik.

### Pengujian

Feature test (PHPUnit, `Http::fake`):

- Tanpa PIN → diarahkan ke halaman PIN; PIN salah ditolak dan dibatasi setelah 5 kali.
- Pengguna A tidak bisa membuka percakapan atau file milik pengguna B (404).
- API key tidak muncul di HTML halaman chat maupun admin.
- Mengirim pesan menyimpan pesan pengguna dan jawaban AI (respons palsu).
- `reasoning_effort` hanya terkirim untuk model `supports_effort`.
- Lampiran dengan tipe/ukuran tidak valid ditolak; ekstraksi `.docx` menghasilkan teks.
- Generate gambar menyimpan file dan `image_path`.

## Diserahkan ke builder

Penamaan class/komponen, pembagian komponen Livewire, detail styling, cara parsing SSE, struktur JSON lampiran, dan batas riwayat yang tepat.
