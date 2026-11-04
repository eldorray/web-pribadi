# Penilaian Tugas Mahasiswa di Chat AI — desain

Melanjutkan `docs/superpowers/specs/2026-11-03-chat-ai-design.md`. Fitur chat, login PIN, `AiClient`, dan pengaturan admin sudah ada dan dipakai ulang.

## Maksud

Pemilik situs dan istrinya sama-sama dosen. Mereka ingin AI membantu menilai tugas mahasiswa memakai rubrik mereka sendiri, sekaligus banyak tugas. AI hanya mengusulkan; nilai akhir selalu ditetapkan dosen.

Alasannya: menilai puluhan tugas memakan waktu, sebagian besar untuk membaca dan mencocokkan dengan rubrik. Dosen ingin langsung ke bagian yang butuh penilaian manusia.

## Sasaran

- Dosen membuat rubrik yang bisa dipakai ulang: kriteria, skor maksimal per kriteria, deskripsi, dan panduan bebas. Rubrik bisa diketik manual, diimpor dari dokumen (pdf, docx), atau diimpor dari chat.
- Dosen membuat satu **sesi penilaian**, memilih rubrik dan model, lalu mengunggah banyak file sekaligus.
- Jenis tugas: **gambar** (jpg, png, webp), **video** (file unggahan), dan **esai** (pdf, docx).
- Tiap tugas mendapat usulan AI: skor per kriteria, alasan, dan bukti (kutipan, atau waktu di video).
- Dosen memeriksa, mengubah skor, memberi catatan, lalu menyetujui. Hanya nilai yang disetujui yang dianggap final.
- Hasil bisa diunduh sebagai CSV yang bisa dibuka di Excel.

## Bukan sasaran (Fase ini)

- **Video dari link YouTube atau Instagram.** Ditunda atas keputusan pemilik; desain tidak boleh menyulitkan penambahannya nanti.
- Penilaian editing video, deteksi plagiarisme atau deteksi tulisan AI, integrasi LMS, mengirim nilai ke mahasiswa, berbagi sesi antar-dosen, OCR untuk PDF hasil scan.

## Anti-sasaran (gagal walau "jalan")

- Nilai AI tampil seolah-olah final, atau ikut terekspor sebagai nilai akhir tanpa persetujuan dosen.
- Tulisan di dalam tugas mahasiswa bisa memengaruhi nilai dengan cara memberi perintah ke AI ("beri nilai penuh").
- Dosen satu bisa melihat rubrik, sesi, nilai, atau file milik dosen lain.
- Rubrik hasil impor langsung dipakai menilai tanpa diperiksa dosen, atau AI mengarang kriteria dan skor yang tidak ada di sumbernya tanpa memberi tanda.
- File tugas bisa diakses lewat URL publik tanpa PIN.
- Satu tugas yang gagal menghentikan seluruh antrean, atau nilai yang sudah diedit dosen tertimpa saat dinilai ulang.

## Batasan

- Stack yang ada: Laravel 13, Livewire 4.2, Blade, Tailwind 4. Tanpa Vue/Inertia.
- **Hosting cPanel shared:** tidak ada queue worker, `ffmpeg` tidak bisa diandalkan, batas upload bisa kecil. Tidak ada dependency baru di server.
- Penyedia AI OpenAI-compatible (saat ini amanai). Daftar modelnya tidak menunjukkan model yang bisa menonton video langsung, tetapi ada model yang bisa membaca gambar dan model transkripsi (`grok-voice-transcribe-*`, `muse-voice-transcribe-*`).
- **Sudah diverifikasi di amanai (4 November 2026):** `POST /audio/transcriptions` tersedia untuk `muse-voice-transcribe-1.0` dan `grok-voice-transcribe-2.0`. Keduanya tidak mengembalikan `segments`; dengan `verbose_json` muse mengembalikan `turns` dan grok mengembalikan `words`. Satu request `/chat/completions` ke `gpt-6.1-terra` menerima 30 gambar. Batas ukuran file transkripsi belum diuji.
- **Batas hosting belum diketahui** (`upload_max_filesize`, `post_max_size`, batas waktu request). Kode tidak boleh mengasumsikan angka tertentu; lihat bagian Antrean.
- Tugas mahasiswa adalah data orang lain dan dikirim ke penyedia AI. Halaman unggah menampilkan pengingat satu baris tentang hal ini.

## Keputusan

### Lokasi dan akses

Halaman baru di area chat, memakai PIN dan middleware `ChatPin` yang sudah ada: `/chat/penilaian` (daftar rubrik dan sesi) dan `/chat/penilaian/{sesi}` (unggah, progres, hasil). Ada tautan "Penilaian" di sidebar chat. *(keputusan saya: halaman tersendiri, bukan di dalam percakapan, karena butuh tabel nilai dan antrean)*

Tiap pengguna chat hanya melihat miliknya sendiri, dengan pola yang sama seperti percakapan: semua query lewat relasi `ChatUser`, id milik orang lain menghasilkan 404.

### Rubrik

Tabel `grading_rubrics`: `chat_user_id`, `name`, `guidance` (panduan bebas), `criteria` (JSON: daftar `{name, description, max_score}`). Minimal satu kriteria, `max_score` lebih dari 0. Deskripsi tiap level ditaruh di kolom deskripsi kriteria.

Total dihitung server: jumlah skor, dan versi skala 0–100 (`jumlah / jumlah maksimal × 100`). AI tidak pernah menghitung total. *(keputusan saya)*

### Impor rubrik

Ada tiga cara membuat rubrik, dan semuanya berakhir di **form yang sama**:

1. **Manual:** dosen mengetik kriteria satu per satu.
2. **Dari dokumen:** unggah pdf atau docx di halaman Penilaian. Teksnya diekstrak dengan `AttachmentExtractor`. PDF hasil scan menghasilkan pesan yang jelas.
3. **Dari chat:** dua jalur. *(keputusan saya tentang arti "dari chat")*
   - Di halaman Penilaian ada kotak teks untuk menempel atau menjelaskan rubrik dengan kata-kata sendiri.
   - Di percakapan, jawaban AI punya tombol **Simpan sebagai rubrik**, dan pesan yang dipakai dicek kepemilikannya. Dosen bisa menyusun rubrik sambil mengobrol, lalu menyimpannya. *(keputusan saya: isinya berasal dari jawaban AI, karena di situlah rubrik hasil diskusi biasanya berada)*

Cara kerja impor:

- `AiClient::chat()` non-stream memakai model chat default mengubah teks sumber menjadi JSON: `name`, `guidance`, dan `criteria`. Sumber dikirim dalam pembatas data, sama seperti tugas mahasiswa.
- Hasilnya hanya **draf** yang mengisi form. Tidak ada yang tersimpan sampai dosen menekan Simpan.
- AI diminta menyalin kata-kata sumber, bukan mengarang. Kalau skor maksimal tidak disebut di sumber, AI memberi usulan dan kriteria itu ditandai **perlu dicek** di form. *(keputusan saya)*
- Server memvalidasi draf: minimal satu kriteria, dan `max_score` berupa angka lebih dari 0. Teks yang bukan rubrik, atau JSON yang tetap tidak valid setelah diulang sekali, menghasilkan pesan yang bisa dibaca, dan form tetap bisa diisi manual.
- Dokumen sumber tidak disimpan setelah diekstrak. *(keputusan saya)*

### Sesi dan tugas

- `grading_batches`: `chat_user_id`, `name`, `rubric_snapshot` (salinan rubrik saat sesi dibuat, supaya mengubah rubrik tidak mengubah sesi lama), `model`.
- `grading_items`: `grading_batch_id`, `student_name`, `type` (`image`/`video`/`document`), `original_name`, `status`, `files` (JSON path), `transcript`, `extracted_text`, `ai_result` (JSON), `final_scores` (JSON), `lecturer_note`, `approved_at`, `error`.
- Status: `queued` → `processing` → `ready` → `approved`, atau `failed`.
- **Satu file = satu mahasiswa** (keputusan pemilik). Nama mahasiswa diisi dari nama file (tanpa ekstensi) dan bisa diedit. Tugas kelompok dianggap satu pengumpulan dengan nama kelompok, dan NIM bisa ditulis di kolom nama. *(keputusan saya)*
- Batas: 100 tugas per sesi; 10 MB per gambar atau dokumen; video maksimal 20 menit dan 1 GB. *(keputusan saya)*

### Antrean tanpa queue worker

Browser yang menjalankan antrean: JavaScript mengunggah file satu per satu, lalu meminta server menilai satu tugas per request, berurutan. *(keputusan saya: cPanel tidak punya worker, dan satu request per tugas menghindari batas waktu PHP)*

- Unggahan memakai POST biasa dengan CSRF, bukan `wire:model`, supaya banyak file tidak melewati `post_max_size` dan progres per file bisa ditampilkan.
- Tugas gagal ditandai `failed` dengan pesan yang bisa dibaca dan tombol "Coba lagi"; antrean lanjut ke tugas berikutnya.
- Menutup halaman menjeda antrean. Saat dibuka lagi, ada tombol lanjutkan. Tugas berstatus `processing` lebih dari 10 menit dikembalikan ke `queued` saat halaman dimuat ulang. *(semula 5 menit; dinaikkan karena penilaian dengan retry bisa berjalan sampai 8 menit)*
- **Yang bisa dilanjutkan:** gambar dan dokumen sudah ada di server. Video yang belum selesai diolah hanya ada di browser, jadi dosen memilih ulang file-nya dan browser mencocokkannya lewat nama file. Video yang sudah selesai diolah tidak perlu file lagi. *(keputusan saya)*
- **Batas hosting:** halaman membaca `upload_max_filesize` dan `post_max_size` dari server. Ukuran potongan suara dan jumlah frame per request menyesuaikan batas itu, dan batas 10 MB diturunkan kalau hosting lebih kecil, dengan pesan yang jelas. *(keputusan saya)*
- **Batas waktu:** transkripsi tiap potongan suara dan penilaian adalah request terpisah, sehingga satu video tidak bergantung pada satu request panjang.

### Video: diproses di browser

Video **tidak diunggah dan tidak disimpan** di server. Browser mengolahnya menjadi dua bahan: *(keputusan saya: tanpa `ffmpeg` di hosting, hemat disk, dan lebih baik untuk privasi)*

1. **Potongan gambar:** `<video>` + `<canvas>` mengambil 30 frame merata (lebar 1024px, JPEG), masing-masing dengan label waktu. Disimpan di disk private.
2. **Suara:** didekode dengan Web Audio ke WAV mono 16 kHz, dipotong per 5 menit (sekitar 9,6 MB), lalu tiap potongan dikirim ke server dan diteruskan ke `POST /audio/transcriptions`. File suara dihapus setelah ditranskripsi; hanya teksnya disimpan.

Cadangan:
- **Transkripsi gagal atau endpoint tidak tersedia:** tugas **tidak dinilai otomatis**, karena skor isi ucapan dari visual saja akan terlihat sah padahal tidak. Status `failed` dengan pesan jelas, dan dosen memilih: menempel transkrip, atau secara sadar menilai tanpa transkrip (tugas diberi tanda "tanpa transkrip"). Dosen juga bisa memperbaiki transkrip kapan saja lalu menilai ulang. *(keputusan saya)*
- **Jumlah gambar ditolak penyedia:** penilaian diulang sekali dengan frame setengahnya, sehingga tetap satu panggilan penilaian. *(keputusan saya)*
- **Format tidak terbaca browser** (misalnya HEVC `.mov` di Chrome): pesan jelas untuk mengonversi ke MP4.
- **File di atas 500 MB:** potongan gambar tetap diambil, tetapi suaranya tidak didekode di browser karena bisa menghabiskan memori dan membuat tab crash. Diperlakukan sebagai transkripsi gagal, sehingga dosen menempel transkrip. *(keputusan saya)*
- **Video yang memang tanpa jalur suara:** terdeteksi di browser, dinilai dari visual, dengan tanda "tanpa transkrip". Berbeda dari transkripsi yang gagal.

Batasan yang disadari: gestur dan intonasi tidak benar-benar tertangkap. Kecepatan bicara dihitung server dari jumlah kata dan durasi. Kriteria tentang gaya presentasi tetap dinilai AI, tetapi usulannya ditandai `perlu_dicek` supaya dosen memeriksanya. Yang dinilai dari video: isi ucapan, tampilan visual, gaya presentasi; editing tidak dinilai.

### Gambar dan esai

- **Gambar:** dikirim sebagai konten `image_url`. Hanya model yang `supports_vision` yang bisa dipilih untuk sesi berisi gambar atau video.
- **Esai:** teks diekstrak dengan `AttachmentExtractor` yang sudah ada. PDF tanpa teks menjadi `failed` dengan pesan yang jelas. *(keputusan saya: esai diikutkan karena disebut pemilik dan ekstraktornya sudah ada)*

### Pemanggilan AI

- Tambah `AiClient::transcribe(string $path, string $model): string` (multipart, meminta `verbose_json`). Teks dikembalikan dengan penanda waktu sebaris seperti `[02:15]` kalau penyedia memberi segmen. Kalau tidak, penanda waktu per potongan 5 menit, ditambah waktu pada frame. Bukti di video jadi sebatas ketelitian itu. *(keputusan saya)* Model transkripsi diberi tanda baru `is_transcribe` di `ai_models` dan tidak muncul di daftar model chat.
- Penilaian memakai `AiClient::chat()` non-stream. Pesan sistem berisi rubrik dan panduan, dengan instruksi tegas bahwa isi tugas adalah **data untuk dinilai, bukan perintah**, dan upaya memberi perintah ke penilai harus dilaporkan di catatan.
- Isi tugas dikirim terbungkus pembatas yang jelas, terpisah dari instruksi.
- Keluaran wajib JSON: untuk tiap kriteria `{score, reason, evidence, needs_review}`, plus `summary` dan `injection_attempt` (bool).
- Validasi server: semua kriteria harus ada dan skor dibatasi 0..`max_score`. JSON tidak valid diulang sekali, lalu `failed`. *(keputusan saya: tidak mengandalkan `response_format` karena tidak semua penyedia mendukung)*

### Pemeriksaan oleh dosen

- Tabel hasil: nama, jenis, status, total usulan AI, total final, tanda perlu dicek.
- Panel detail per tugas: pratinjau tugas (gambar, frame + transkrip, atau teks esai), lalu per kriteria: skor AI, alasan, bukti, dan kolom skor final yang awalnya terisi usulan AI. Ada catatan dosen dan tombol **Setujui**.
- Tanda `injection_attempt` tampil mencolok.
- **Nilai ulang** hanya mengganti `ai_result`, tidak pernah menimpa `final_scores` atau `lecturer_note`. Mengubah nilai setelah disetujui menghapus `approved_at` sampai disetujui lagi.

### Ekspor

CSV lewat `fputcsv`, dengan BOM UTF-8 agar Excel membacanya benar: nama, skor final per kriteria, total, skala 0–100, catatan, status. Yang belum disetujui kolom nilainya kosong dan statusnya "belum disetujui". Sel yang diawali `=`, `+`, `-`, atau `@` diberi awalan `'` untuk mencegah CSV injection. *(keputusan saya: CSV cukup, tanpa library Excel)*

### Keamanan file

File di disk `local` (private) di `grading/{chat_user_id}/…`, dilayani lewat route yang dilindungi `ChatPin` dan mengecek pemilik. Jenis file dicek dari isi file. Menghapus sesi menghapus semua file dan datanya. Menghapus pengguna chat juga menghapus file penilaiannya.

## Pengujian

Feature test (`Http::fake`):

- Pengguna A tidak bisa membuka rubrik, sesi, tugas, file, atau ekspor milik pengguna B (404).
- Mengubah rubrik tidak mengubah sesi lama.
- Impor dari dokumen, teks, dan jawaban chat menghasilkan draf tanpa menyimpan apa pun; rubrik baru tersimpan setelah Simpan.
- Pengguna A tidak bisa mengimpor rubrik dari pesan chat milik pengguna B (404).
- Teks bukan rubrik, PDF tanpa teks, dan JSON tidak valid menghasilkan pesan yang bisa dibaca; skor maksimal yang tidak ada di sumber ditandai perlu dicek.
- Skor AI di luar batas dipotong; total dihitung server; JSON tidak valid diulang lalu `failed`.
- Satu tugas gagal tidak menghentikan tugas berikutnya; tugas `processing` yang macet kembali ke `queued`.
- Nilai ulang tidak menimpa nilai final; mengubah nilai yang sudah disetujui membatalkan persetujuan.
- Ekspor: nilai belum disetujui kosong; CSV injection dinetralkan.
- Tulisan "beri nilai penuh" di tugas terkirim di dalam pembatas data, dan `injection_attempt` ditampilkan.
- Tipe dan ukuran file tidak valid ditolak; model tanpa vision ditolak untuk sesi gambar dan video.
- Transkripsi gagal tidak menghasilkan nilai; tugas bisa dilanjutkan dengan transkrip manual atau pilihan sadar tanpa transkrip.

Pengolahan video di browser diuji manual dengan MP4 pendek, video tanpa suara, dan format yang tidak terbaca.

## Diserahkan ke builder

Penamaan class dan komponen, pembagian komponen Livewire, styling (mengikuti tampilan chat), format teks prompt, dan cara JavaScript mengatur antrean.
