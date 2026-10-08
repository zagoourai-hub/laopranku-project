# PRD — Web Perapi Makalah & Laporan Pelajar dan Mahasiswa

> Status: **Draft v1.1** · Tanggal: 2026-10-07 · Pemilik: bigboss · Nama produk: AIdocs (nama kerja di desain: "Rapi")
> Mode: sintesis dari diskusi + hasil spike (`D:\Project\project\testing\spike.php`, `contoh-makalah.docx`, `contoh-makalah.pdf`) + desain Stitch.
>
> **Perubahan v1.1:** UI hanya memakai Flux UI **free** (tanpa Flux Pro); ditambah bagian *UI & Design System* (tema merah, light + dark mode, link Stitch); path referensi spike diperbaiki.

---

## 1. Executive Summary

**Problem Statement.** Pelajar (SMA/SMK) dan mahasiswa menghabiskan waktu lama merapikan format makalah di Word (heading, nomor bab, daftar isi, nomor halaman romawi/angka, margin, daftar pustaka), dan format sering rusak karena konten dan format tercampur saat copy-paste.

**Proposed Solution.** Pelajar dan mahasiswa cukup memberi konten (paste teks mentah, upload file, atau prompt). Sistem mengubahnya jadi dokumen terstruktur (JSON), lalu builder menerapkan template format sekolah/kampus dan menghasilkan DOCX yang rapi dan masih bisa diedit, plus PDF.

**Success Criteria** (angka target = usulan, dikonfirmasi bigboss sebelum launch):
1. **Kualitas format:** 100% dokumen hasil export lolos golden test (cover tanpa nomor, halaman awal romawi, isi angka mulai 1, daftar isi terisi, Bab/Subbab bernomor benar).
2. **Kecepatan:** dari paste teks (≤ 5.000 kata) sampai DOCX siap unduh ≤ 60 detik (p90).
3. **Akurasi structuring:** ≥ 90% heading terdeteksi benar pada set uji 10 teks mentah asli pelajar dan mahasiswa.
4. **Integritas konten:** 0 kalimat baru yang dikarang AI pada jalur paste/upload (konten asli tidak berubah).
5. **Adopsi:** TBD (misal jumlah dokumen ter-export per minggu selama beta).

---

## 2. User Experience & Functionality

### Personas
- **Pelajar SMA/SMK (utama):** butuh makalah, laporan praktikum, dan laporan PKL/Prakerin rapi untuk tugas sekolah; sama-sama punya isi tapi belum mahir mengatur format Word. Perlu hasil sesuai panduan sekolah (preset umum dulu).
- **Mahasiswa S1/D3 (utama):** punya isi tulisan (hasil ketik, copy dari sumber, catatan) tapi tidak mahir/tidak sempat mengatur format Word. Perlu hasil sesuai panduan kampus.
- **Pelajar/mahasiswa dengan prompt:** butuh draf awal dari topik ("buatkan makalah tentang X, 5 bab").
- **Pengguna AI agent (Fase 3–4):** memakai Claude Desktop/ChatGPT/Cursor, ingin agent-nya langsung menghasilkan DOCX format kampus lewat MCP.
- **Admin (bigboss):** mengelola template, kuota, dan biaya AI.

### User Flow utama
```
Pilih jenis dokumen + isi data cover
        │
        ├─ Paste teks ──► normalisasi ──► AI labeling ──┐
        ├─ Upload .md/.docx/.txt ──► parser (AI jika perlu) ─┤
        └─ Prompt ──► outline ──► user setujui ──► generate per bab ─┤
                                                          ▼
                                            Dokumen JSON (tervalidasi)
                                                          ▼
                                      Editor (edit blok, urut bab, undo/redo)
                                                          ▼
                                      Export DOCX ──► (Gotenberg) ──► PDF
```

**State dokumen:** `draft` → `structuring` / `outlining` → `outline_ready` → `generating` (per bab: `pending|running|done|failed`) → `ready` → `exported`. Setiap state gagal bisa di-retry tanpa kehilangan data.

### User Stories & Acceptance Criteria

**A. Input dan konten**

| # | Story | Acceptance Criteria |
|---|---|---|
| A1 | Sebagai pelajar/mahasiswa, saya ingin paste teks mentah agar dirapikan jadi makalah. | Teks ≤ 20.000 kata diterima; karakter aneh copy-paste, spasi ganda, dan nomor manual ("BAB 1.", "1.1") dibersihkan; hasil berupa bab/subbab/paragraf/list; isi kalimat identik dengan input (diuji otomatis). |
| A2 | Sebagai pelajar/mahasiswa, saya ingin menulis prompt topik agar dibuatkan draf. | Form "Tulis topik" berisi: topik (wajib, ≥ 10 karakter), jumlah bab (default dari jenis dokumen, 3–7), panjang per bab (ringkas/sedang/panjang), gaya bahasa, instruksi tambahan (opsional, ≤ 500 karakter), sumber referensi (opsional). Sistem menampilkan outline (bab, subbab, deskripsi) dulu; isi tidak ditulis sebelum user menekan "Setujui". |
| A5 | Sebagai pelajar/mahasiswa yang membuat laporan praktikum/PKL, saya ingin AI menulis dari data asli saya, bukan mengarang. | Jenis **laporan praktikum** meminta: judul percobaan, tujuan, alat dan bahan, langkah kerja, data hasil pengamatan. Jenis **laporan PKL** meminta: nama instansi, divisi/bagian, periode, daftar kegiatan. Field data pokok wajib sebelum generate; AI hanya menarasikan data itu dan tidak menambah angka, nama, atau kegiatan baru; bagian tanpa data ditandai "[lengkapi]" di dokumen. |
| A3 | Sebagai pelajar/mahasiswa, saya ingin upload .docx/.txt/.md. | .md dan .docx ber-style Heading dipetakan tanpa AI; file lain lewat jalur A1; file > 10 MB atau format lain ditolak dengan pesan jelas. |
| A4 | Sebagai pelajar/mahasiswa, saya ingin memilih jenis dokumen (makalah, laporan praktikum, laporan PKL, proposal). | Jenis dokumen menentukan kerangka bab default dan judul cover. |

**B. AI structuring dan penulisan**

| # | Story | Acceptance Criteria |
|---|---|---|
| B1 | Sebagai pelajar/mahasiswa, saya ingin outline bisa saya ubah sebelum isi ditulis. | Bab/subbab bisa ditambah, dihapus, di-rename, diurutkan; perubahan tersimpan. |
| B2 | Sebagai pelajar/mahasiswa, saya ingin isi ditulis per bab dengan progres terlihat. | Satu job per bab; UI menampilkan "Bab n dari N"; bab gagal bisa di-regenerate sendiri. |
| B3 | Sebagai pelajar/mahasiswa, saya ingin memilih gaya bahasa (formal akademik, semi-formal, ringkas). | Gaya tersimpan per dokumen dan dipakai di setiap generate bab. |
| B4 | Sebagai pelajar/mahasiswa, saya tidak ingin referensi palsu. | Di mode prompt AI tidak menulis daftar pustaka atau sitasi kecuali dari sumber yang ditempel user; jika tidak ada sumber, daftar pustaka kosong + catatan "tambahkan referensi". |

**C. Template dan format**

| # | Story | Acceptance Criteria |
|---|---|---|
| C1 | Sebagai pelajar/mahasiswa, saya ingin format standar otomatis. | Default: Times New Roman 12, spasi 1.5, margin kiri 4 / atas 3 / kanan 3 / bawah 3 cm, A4, paragraf rata kiri-kanan dengan indent baris pertama 1.25 cm. |
| C2 | Sebagai pelajar/mahasiswa, saya ingin cover otomatis. | Cover memuat jenis dokumen, judul, nama, NIM, prodi, kampus, tahun; tanpa nomor halaman. |
| C3 | Sebagai pelajar/mahasiswa, saya ingin kata pengantar dan daftar isi otomatis. | Kata pengantar dari template berplaceholder; daftar isi berupa field TOC asli (heading level 1–2), terisi di PDF dan di Word setelah update field. |
| C4 | Sebagai pelajar/mahasiswa, saya ingin nomor halaman romawi lalu angka. | Halaman awal i, ii, …; Bab I mulai dari 1; tidak ada halaman kosong sisipan di PDF. |
| C5 | Sebagai pelajar/mahasiswa, saya ingin Bab dan subbab bernomor otomatis. | Format "BAB I JUDUL" dan "1.1 Subjudul"; nomor dihitung builder, bukan AI; tetap benar setelah bab diurutkan ulang. |
| C6 | Sebagai pelajar/mahasiswa, saya ingin daftar pustaka rapi. | Hanging indent 1.25 cm, diurutkan alfabetis, judul "DAFTAR PUSTAKA" masuk daftar isi. |

**D. Editor dan preview**

| # | Story | Acceptance Criteria |
|---|---|---|
| D1 | Sebagai pelajar/mahasiswa, saya ingin melihat preview mirip kertas A4. | Preview HTML berhalaman A4 ter-update ≤ 1 detik setelah edit; tombol "Preview akurat" menampilkan PDF hasil Gotenberg. |
| D2 | Sebagai pelajar/mahasiswa, saya ingin mengedit per blok. | Paragraf/list/heading bisa diedit (TipTap); perubahan auto-save (debounce) ke JSON dokumen. |
| D3 | Sebagai pelajar/mahasiswa, saya ingin drag-and-drop urutan bab. | Urutan bab dan subbab berubah; penomoran di preview langsung menyesuaikan. |
| D4 | Sebagai pelajar/mahasiswa, saya ingin undo/redo. | Minimal 50 langkah, berjalan di browser tanpa round-trip server. |

**E. Export**

| # | Story | Acceptance Criteria |
|---|---|---|
| E1 | Sebagai pelajar/mahasiswa, saya ingin unduh DOCX yang bisa diedit. | File terbuka tanpa error di MS Word dan LibreOffice; heading memakai style Heading 1/2 asli. |
| E2 | Sebagai pelajar/mahasiswa, saya ingin unduh PDF. | PDF dihasilkan dari DOCX yang sama (isi identik), daftar isi terisi. |

**F. Akun dan dokumen**

| # | Story | Acceptance Criteria |
|---|---|---|
| F1 | Sebagai pelajar/mahasiswa, saya ingin mencoba tanpa daftar. | Guest user dibuat otomatis; saat register, dokumen tetap milik akun yang sama. |
| F2 | Sebagai pelajar/mahasiswa, saya ingin login dan menyimpan dokumen. | Login session (starter kit); dokumen hanya bisa diakses pemiliknya. |
| F3 | Sebagai pelajar/mahasiswa, saya ingin mengelola daftar dokumen. | Buka, duplikat, hapus (dengan konfirmasi). |


**G. Jenjang**

| # | Story | Acceptance Criteria |
|---|---|---|
| G1 | Sebagai pelajar/mahasiswa, saya ingin memilih jenjang (Mahasiswa, SMA, SMK). | Jenjang dipilih saat membuat dokumen dan tersimpan per dokumen; label cover mengikuti jenjang (mahasiswa: NIM, prodi, kampus; SMA/SMK: NIS/NISN, jurusan/kelas, sekolah). |
| G2 | Sebagai pelajar SMA/SMK, saya ingin format standar tanpa mengatur sendiri. | Template default sama untuk semua jenjang: Times New Roman 12, spasi 1.5, margin 4-3-3-3. Panduan sekolah spesifik lewat template custom (task 3.4/4.2). |

### Non-Goals (MVP)
- Bukan alat deteksi/penghindar AI detector atau plagiarisme.
- Jenis **Proposal** (skripsi/tugas akhir) tidak ditawarkan untuk SMA/SMK.
- Tidak menjamin kesesuaian dengan panduan sekolah/kampus spesifik (hanya preset umum) sampai fitur template custom.
- Tidak ada editor bebas ala Word (format manual per kata, font bebas) — format selalu dari template.
- Tidak ada kolaborasi realtime, komentar, atau riwayat versi di MVP.
- Tidak memakai komponen atau layanan berbayar untuk UI (Flux Pro dikecualikan).

### UI & Design System

**Desain:** project Stitch "Rapi — Perapi Makalah Mahasiswa" (ID `225495029269444949`). Layar MVP, masing-masing light + dark:

| Layar | Isi utama | Story |
|---|---|---|
| Landing | Hero dengan artefak asli (teks mentah → halaman A4), tabel "Masalah vs Di Rapi", cara kerja 3 langkah | — |
| Dashboard Dokumen | Sidebar, tabel dokumen + badge status, dropdown aksi, kartu kuota Free | F2, F3 |
| Dokumen baru | Radio card jenis dokumen, form cover, radio card jalur input, textarea, pratinjau cover A4 | A1–A4, C2 |
| Tulis topik | Form topik, jumlah bab, panjang, gaya, instruksi, sumber, data pokok praktikum/PKL | A2, A5 |
| Tinjau outline | Pohon bab/subbab drag-and-drop, sumber referensi manual, pengaturan gaya | B1, B4 |
| Proses | Progres per bab (selesai/menulis/menunggu/gagal), tulis ulang bab | B2 |
| Paket / Upgrade ke Pro | Kartu Free vs Pro 30 hari, ringkasan pesanan, tombol "Bayar" (membuka Midtrans Snap) | 4.2, 4.3a |
| Status pembayaran | State menunggu (auto-refresh), berhasil, gagal/kedaluwarsa | 4.3b |
| Pengaturan — Tagihan | Masa aktif Pro, perpanjang 30 hari, riwayat pembayaran | 4.3a–b |

Popup pembayaran (QRIS/VA/e-wallet) memakai UI Midtrans Snap, tidak didesain sendiri.
| Editor | Struktur (kiri), editor blok TipTap (tengah), preview A4 live (kanan), undo/redo, unduh | D1–D4, E1–E2 |

**Token desain:**
- Font UI Inter; isi kertas Times New Roman.
- Netral zinc; aksen **merah `#DC2626`** hanya untuk tombol primary (satu per halaman), item aktif, progress, dan fokus.
- Radius 8px (button/input), 12px (card).
- **Dark mode:** bg zinc-900, sidebar zinc-950, border white/10. **Halaman A4 tetap putih** di kedua mode.
- Badge status: zinc (Draft), amber (Menulis), green (Siap), red (Gagal).
- Toggle light/dark mengikuti sistem, dengan pilihan manual di Pengaturan (`$flux.appearance`).

**Aturan anti AI-slop:** tanpa gradient/glow/glassmorphism, tanpa ikon sparkle, tanpa emoji, tanpa testimoni/statistik palsu, tanpa deretan 3 kartu ikon seragam; konten contoh realistis berbahasa Indonesia.

**Komponen — hanya Flux UI free:**

| Kebutuhan | Dipakai (free) | Bukan (Pro) |
|---|---|---|
| Pilih jalur input (Tempel/Topik/Upload) | Radio card | Tabs |
| Upload file | `<input type="file">` bergaya Flux | File upload |
| Editor isi | TipTap + Alpine | Rich text editor |
| Pilih template/gaya | Select native | Searchable select / combobox |
| Menu aksi, tooltip, modal, toast | Dropdown/Menu, Tooltip, Modal, Toast | — |
| Layout | Sidebar, Navlist, Navbar, Breadcrumbs, Profile, Card, Table, Badge, Callout | — |

Status free beberapa komponen (Table, Toast, Callout) dikonfirmasi saat task 1.1; jika ternyata Pro, diganti markup Tailwind biasa.

---

## 3. AI System Requirements

### Tool dan API
- **SDK:** `laravel/ai` (resmi, sudah punya `OpenRouterProvider` — terverifikasi di spike). Fallback: Prism PHP.
- **Provider:** OpenRouter. Model per tugas lewat config:
  - `AI_MODEL_STRUCTURE` — model murah yang mendukung JSON schema (labeling paragraf).
  - `AI_MODEL_OUTLINE` dan `AI_MODEL_WRITE` — model kuat untuk outline dan penulisan bab (kandidat: Claude Sonnet). Pilihan final **TBD** setelah evaluasi.
- **Kontrak output:** satu file JSON Schema dokumen dipakai untuk structured output AI, validasi server (`opis/json-schema`), dan `inputSchema` MCP.

### Aturan pipeline
1. **Kode dulu, AI belakangan:** normalisasi teks, parsing .md, dan .docx ber-heading dilakukan tanpa AI.
2. **Structuring = labeling, bukan menulis ulang:** paragraf diberi ID (`p1…pn`); AI mengembalikan struktur yang mereferensikan ID; kode menyusun dokumen dari teks asli. Teks panjang dipecah per chunk.
3. **AI tidak pernah menulis nomor atau format** ("BAB I", "1.1", nomor halaman, style).
4. **Generate per bab:** prompt berisi outline penuh + ringkasan bab sebelumnya + gaya bahasa + daftar sumber user (jika ada) + data pokok (laporan praktikum/PKL).
4b. **Tidak mengarang fakta:** untuk laporan praktikum/PKL, AI dilarang membuat angka pengamatan, nama orang/instansi, atau kegiatan di luar data pokok; kekosongan ditulis "[lengkapi]".
5. **Validasi:** output tidak valid → retry 1× dengan pesan error validator → jika masih gagal, state `failed` + pesan ke user.

### Evaluasi kualitas
- **Set uji:** 10 teks mentah asli pelajar dan mahasiswa (copy dari web, PDF, chat) + 5 prompt topik. Dikumpulkan sebelum memilih model (**TBD: sumber data**).
- **Metrik:** akurasi deteksi heading (target ≥ 90%), integritas teks (100% kalimat asli terjaga, diuji diff otomatis), validitas JSON (≥ 99% setelah retry), biaya dan latensi per dokumen.
- **Regresi:** set uji dijalankan ulang setiap ganti model atau prompt.

### Safety, privasi, biaya
- Teks user dikirim ke provider AI via OpenRouter → disebutkan di kebijakan privasi. Retensi dokumen: **TBD** (usulan: guest dihapus setelah 30 hari tidak aktif).
- Batas per user: jumlah generate per hari dan `max_tokens` per job (angka **TBD**, sekaligus jadi pembeda Free/Pro).
- Prompt caching untuk system prompt + schema.
- Budget biaya AI per dokumen: **TBD**; spending limit dipasang di akun OpenRouter.
- Posisi produk: **perapi format** (utama), mode prompt sebagai fitur sekunder.

---

## 4. Technical Specifications

### Stack (terkunci)

| Lapisan | Keputusan |
|---|---|
| Framework | Laravel 13 (PHP 8.4 runtime; lihat temuan spike) + Livewire 4 + **Flux UI free** (Livewire starter kit), tanpa Flux Pro |
| Editor | Alpine + TipTap dalam `wire:ignore`, SortableJS untuk urut bab, preview paged.js |
| ORM / DB | Eloquent + PostgreSQL (konten di kolom `jsonb`) — *Postgres = asumsi, belum dikonfirmasi eksplisit* |
| Auth | Starter kit (session, Fortify) + guest user. Sanctum/Passport ditambah saat MCP |
| AI | `laravel/ai` + OpenRouter, dijalankan di Queue (driver database untuk MVP) |
| DOCX | PHPWord 1.4 + post-processing XML (wajib, lihat temuan spike) |
| PDF | Gotenberg 8 (LibreOffice) dengan `updateIndexes=true`, `skipEmptyPages=true` |
| MCP | `laravel/mcp`, server only (Fase 4) |
| Pembayaran | Midtrans Snap (QRIS, VA, e-wallet), Pro dijual sekali bayar per 30 hari (QRIS tidak mendukung auto-debit). **Sandbox** untuk dev/testing; production hanya setelah task 4.3c lolos (Fase 4) |
| Hosting | Dev/demo: laptop + Cloudflare Tunnel → Oracle Cloud Always Free (Docker Compose) → VPS berbayar saat produksi |

### Arsitektur
```
Livewire UI ──► DocumentService ──► Postgres (documents.content jsonb)
     │                │
     │                ├─► AI Jobs (queue) ──► OpenRouter
     │                └─► SchemaValidator (JSON Schema)
     └─► Export ──► DocxBuilder (PHPWord + XML patch) ──► Gotenberg ──► PDF
MCP server (Fase 4) ──► DocumentService (class yang sama)
```

### Modul dan antarmuka
| Modul | Tanggung jawab | Antarmuka |
|---|---|---|
| `TextNormalizer` | Bersihkan teks, pecah paragraf ber-ID, buang nomor manual | `normalize(string): Paragraph[]` |
| `ImportParser` | .md/.docx/.txt → dokumen atau paragraf | `parse(UploadedFile): Document\|Paragraph[]` |
| `Structurer` | Labeling AI → dokumen | `structure(Paragraph[]): Document` |
| `OutlineGenerator` / `ChapterWriter` | Mode prompt | `outline(prompt, opsi)`, `write(document, chapterIndex)` |
| `SchemaValidator` | Validasi JSON vs schema | `validate(array): errors[]` |
| `DocxBuilder` | Dokumen + template → file DOCX | `build(Document, Template): path` |
| `PdfConverter` | DOCX → PDF via Gotenberg | `convert(path): path` |
| `Template` | Preset format (font, spasi, margin, gaya heading, format cover) | data/config, bukan class per kampus |

Logika inti **tidak** ditaruh di komponen Livewire, supaya MCP bisa memakai class yang sama.

### Skema dokumen (kontrak publik)
```json
{
  "schema_version": 1,
  "type": "makalah | laporan_praktikum | laporan_pkl | proposal",
  "cover": { "title": "", "name": "", "nim": "", "prodi": "", "campus": "", "year": "" },
  "style": "formal | semi_formal | ringkas",
  "chapters": [
    { "id": "uuid", "title": "Pendahuluan", "sections": [
      { "id": "uuid", "title": "Latar Belakang", "blocks": [
        { "id": "uuid", "type": "paragraph", "text": "" },
        { "id": "uuid", "type": "list", "ordered": true, "items": [""] }
      ]}
    ]}
  ],
  "references": [ { "id": "uuid", "text": "" } ],
  "sources": [ { "id": "uuid", "text": "", "url": null, "doi": null } ],
  "brief": {
    "topic": "", "chapter_count": 4, "length": "ringkas | sedang | panjang", "instructions": "",
    "facts": { "praktikum": { "judul": "", "tujuan": "", "alat_bahan": [""], "langkah": [""], "data_pengamatan": "" },
               "pkl": { "instansi": "", "divisi": "", "periode": "", "kegiatan": [""] } }
  }
}
```
Invariant: judul tidak mengandung nomor; `id` unik; `blocks.type` MVP = `paragraph | list` (tabel/gambar di Fase 3).

### Data
- `users` (+ `is_guest`), `documents` (`user_id`, `title`, `type`, `status`, `content jsonb`, `template_key`, timestamps, soft delete), `document_jobs` (`document_id`, `chapter_index`, `status`, `error`, `tokens_in/out`, `cost`).

### Keamanan
- **Escaping XML wajib aktif** (`Settings::setOutputEscapingEnabled(true)`) — satu `&` dari user merusak seluruh DOCX (temuan spike).
- Policy Laravel: dokumen hanya untuk pemilik. File export disajikan via signed URL berumur pendek.
- Upload: validasi MIME + ukuran; parsing di job, bukan request.
- Rate limit endpoint AI per user dan per IP (guest).
- Secret (OpenRouter key, Midtrans server key) hanya di `.env` server; client key Midtrans saja yang boleh ke browser.
- Pembayaran: status Pro hanya berubah lewat webhook yang lolos verifikasi `signature_key` + cek status ke API Midtrans, tidak pernah dari redirect/callback di browser. Env: `MIDTRANS_SERVER_KEY`, `MIDTRANS_CLIENT_KEY`, `MIDTRANS_IS_PRODUCTION=false` (sandbox).

### Temuan spike (wajib masuk `DocxBuilder`)
| # | Masalah PHPWord 1.4 | Solusi terbukti |
|---|---|---|
| 1 | `cmToTwip`/`pointToTwip` menghasilkan desimal → LibreOffice gagal konversi | Bulatkan semua twips ke integer |
| 2 | `paperSize => 'A4'` menulis ukuran desimal | Set `pageSizeW=11906`, `pageSizeH=16838` |
| 3 | Escaping XML mati default → `&` merusak file | `setOutputEscapingEnabled(true)` |
| 4 | Tidak bisa set format nomor halaman romawi per section | Patch XML: `w:pgNumType w:fmt="lowerRoman"` di section front matter |
| 5 | `addTOC()` menulis XML yang tidak dikenali LibreOffice (daftar isi kosong) | Placeholder lalu ganti dengan field `TOC \o "1-2" \h \z \u` standar |
| 6 | Style heading tanpa outline level | Tambah `w:outlineLvl` ke Heading1/2 |
| 7 | Hanging indent ditulis bersama `firstLine="0"` → hanging tidak jalan | Hapus `firstLine="0"` saat ada `hanging` |
| 8 | LibreOffice menyisipkan halaman kosong saat nomor halaman di-restart | Gotenberg `skipEmptyPages=true` |
| 9 | PHPWord memicu deprecation di PHP 8.5 | Jalankan di PHP 8.4 (sesuai Laravel 13 min 8.3) |

**Belum diverifikasi:** membuka `contoh-makalah.docx` di **MS Word asli** (spike hanya diuji di LibreOffice/Gotenberg). → Task 0.4.

### Keputusan & trade-off
- **Livewire dipilih** di atas Inertia+React demi produktivitas; konsekuensi: editor kaya (rumus, kolaborasi realtime) akan lebih sulit di Fase 4.
- **PDF dari DOCX via Gotenberg** (bukan render HTML) supaya isi identik; konsekuensi: butuh container ~1 GB RAM.
- **Preview HTML = perkiraan**; yang akurat hanya PDF.
- **Tidak pakai JWT**; session cookie cukup untuk web.
- **Flux UI free saja**: Tabs, file upload, rich text editor, dan searchable select (semua Pro) diganti radio card, input file native, TipTap, dan select native. Biaya UI = Rp0.

---

## 5. Testing Decisions

- **Golden test builder:** fixture JSON dokumen → DOCX → PDF (Gotenberg) → cek teks per halaman: cover tanpa nomor, "i/ii" di front matter, "1" di BAB I, daftar isi memuat semua heading level 1–2, tidak ada halaman kosong. Dijalankan di CI dengan container Gotenberg.
- **Contract test schema:** fixture valid/invalid terhadap JSON Schema; MCP & AI memakai file yang sama.
- **Integritas teks:** setelah structuring, gabungan teks blok == teks input ternormalisasi (diff = 0).
- **Edge case input:** teks kosong, teks tanpa heading, 20.000 kata, karakter `& < > "`, emoji, teks campur EN/ID.
- **AI dengan fake provider:** test job/queue memakai response tiruan (deterministik); evaluasi kualitas model dijalankan terpisah (bukan di CI).
- **Feature test (Pest):** policy akses dokumen, guest → register mempertahankan dokumen, retry bab gagal, kuota.
- **Manual:** buka DOCX hasil di MS Word (Windows) setiap rilis template.

---

## 6. Risks & Roadmap

### Fase & task atomik
Setiap task ≈ ≤ 1 hari, bisa diverifikasi sendiri.

**Fase 0 — Spike (✅ selesai kecuali 0.4)**
- [x] 0.1 Builder PHPWord: cover, front matter romawi, Bab/subbab bernomor, daftar pustaka.
- [x] 0.2 Konversi PDF via Gotenberg dengan daftar isi terisi.
- [x] 0.3 Verifikasi `laravel/ai` mendukung OpenRouter.
- [ ] 0.4 Buka `contoh-makalah.docx` di MS Word: cek daftar isi (update field), nomor halaman, hanging indent.

**Fase 1 — Inti: input → AI → template → DOCX**
- [ ] 1.1 Install Laravel 13 Livewire starter kit, Postgres, queue database, Docker Compose (app, postgres, gotenberg); pastikan hanya Flux free terpasang dan cek status free Table/Toast/Callout.
- [ ] 1.1b Terapkan token desain: aksen merah `#DC2626`, light + dark mode, layout sidebar sesuai desain Stitch.
- [ ] 1.2 File JSON Schema dokumen v1 + `SchemaValidator` + contract test.
- [ ] 1.3 Migration `documents`, `document_jobs`; model + cast jsonb + policy pemilik.
- [ ] 1.4 `DocxBuilder` dari spike: semua 9 solusi temuan spike + preset template default.
- [ ] 1.5 Golden test builder (fixture → DOCX → PDF → assert halaman).
- [ ] 1.6 `TextNormalizer` + unit test (nomor manual, spasi, karakter aneh).
- [ ] 1.7 `Structurer` (laravel/ai + OpenRouter, labeling ber-ID, chunking) + test fake provider + test integritas teks.
- [ ] 1.8 `ImportParser` untuk .md dan .txt.
- [ ] 1.9 `ImportParser` untuk .docx (heading style → struktur; tanpa heading → Structurer).
- [ ] 1.10 `OutlineGenerator` + halaman setujui/edit outline (Livewire).
- [ ] 1.10b Form "Tulis topik" (A2) + field data pokok per jenis dokumen (A5) + validasi; data disimpan di `brief`.
- [ ] 1.11 `ChapterWriter` job per bab + progres `wire:poll` + regenerate bab + aturan referensi (B4).
- [ ] 1.12 Halaman buat dokumen sesuai desain "Dokumen baru": radio card jenis, form cover, radio card jalur input, pratinjau cover.
- [ ] 1.13 Tombol export DOCX (signed URL).
- [ ] 1.14 Kumpulkan set uji 10 teks + 5 prompt, jalankan evaluasi, pilih model per tugas.

**Fase 2 — Editor, PDF, akun**
- [ ] 2.1 Guest user otomatis + konversi ke akun saat register.
- [ ] 2.2 Daftar dokumen: buka, duplikat, hapus.
- [ ] 2.3 Editor blok TipTap (Alpine, `wire:ignore`) + auto-save debounce.
- [ ] 2.4 Drag-and-drop bab/subbab (SortableJS) + penomoran ulang.
- [ ] 2.5 Undo/redo di sisi browser (≥ 50 langkah).
- [ ] 2.6 Preview A4 paged.js.
- [ ] 2.7 Export PDF + tombol "Preview akurat".
- [ ] 2.8 Rate limit + kuota generate per user.
- [ ] 2.9 Deploy demo: Cloudflare Tunnel; lalu Oracle Cloud Free.
- [ ] 2.10 Jenjang resmi (Mahasiswa/SMA/SMK): pilihan saat buat dokumen, label cover per jenjang, template default dipakai bersama.
- [ ] 2.11 Teks produk dan PRD netral (pelajar dan mahasiswa); cek relevansi jenis Proposal untuk SMA/SMK.

**Fase 3 — Referensi, template, elemen**
- [ ] 3.1 Format sitasi APA/IEEE/Harvard untuk daftar pustaka.
- [ ] 3.2 Cari referensi asli (Crossref/OpenAlex) → user pilih → AI menulis dengan sitasi terpilih.
- [ ] 3.3 Peringatan sitasi tanpa entri daftar pustaka.
- [ ] 3.4 Template custom per kampus (simpan & pakai ulang) + upload logo cover.
- [ ] 3.5 Blok tabel & gambar + caption bernomor + daftar tabel/gambar.
- [ ] 3.6 Halaman pengesahan, abstrak, lampiran.

**Fase 4 — Distribusi & monetisasi**
- [ ] 4.1 MCP server (`laravel/mcp`): `list_templates`, `create_document`, `get_document`, `update_chapter`, `export_document`; auth token (Sanctum) lalu OAuth (Passport).
- [ ] 4.2 Paket Free/Pro (kuota dokumen/halaman, template custom & PDF untuk Pro) — tanpa watermark DOCX.
- [ ] 4.3a Integrasi **Midtrans Snap** (`midtrans/midtrans-php`) di **sandbox**: order Pro sekali bayar → popup Snap (QRIS, VA, e-wallet) → tabel `payments`.
- [ ] 4.3b Webhook notifikasi Midtrans: verifikasi `signature_key`, cek ulang status ke API Midtrans, idempotent per `order_id`; status `settlement`/`capture` → `users.pro_until = max(now, pro_until) + 30 hari`.
- [ ] 4.3c Feature test webhook (signature salah ditolak, notifikasi ganda tidak menambah masa Pro dua kali, `expire`/`cancel` tidak mengubah apa pun) + uji manual dengan simulator pembayaran sandbox.
- [ ] 4.3d Pindah ke production: aktivasi akun merchant Midtrans, ganti key production, `MIDTRANS_IS_PRODUCTION=true`, set URL notifikasi production. Hanya setelah 4.3a–c lolos.
- [ ] 4.4 Fitur AI tambahan (PUEBI, parafrase, ringkas/perpanjang, terjemahan).
- [ ] 4.5 Kolaborasi & riwayat versi (evaluasi ulang batas Livewire).

### Risiko teknis
| Risiko | Dampak | Mitigasi |
|---|---|---|
| PHPWord kurang aktif dikembangkan; perlu patch XML | Builder rapuh saat upgrade | Golden test; pin versi; fallback: service Node `docx` di container terpisah |
| DOCX tampil beda di MS Word vs LibreOffice | Komplain user | Task 0.4 + uji manual di Word tiap rilis template |
| Output AI tidak valid / mengubah isi | Data rusak | Labeling ber-ID, validasi schema, retry, test integritas |
| Biaya AI membengkak | Rugi di paket Free | Model murah untuk structuring, kuota, spending limit OpenRouter |
| Referensi palsu dari AI | Reputasi, masalah akademik user | Aturan B4; Fase 3 referensi terverifikasi |
| Gotenberg butuh RAM ~1 GB | Hosting gratis tidak cukup | Oracle Free / VPS; antrean export |
| Editor kompleks di Livewire | Fase 4 lambat | Batasi editor MVP; evaluasi ulang sebelum Fase 4.5 |

---

## 7. Out of Scope (PRD ini)
Import PDF, input suara, CSV → tabel, Google Docs export, komentar, mode bandingkan versi, rumus, kolaborasi realtime, aplikasi mobile, API publik selain MCP.

---

## 8. Further Notes

**Keputusan terkonfirmasi:** Laravel 13 + Livewire + Flux UI free; tema merah dengan light + dark mode; payment gateway **Midtrans** (development dan testing di **sandbox** dulu).

**Asumsi:** PostgreSQL; deploy awal di laptop + Cloudflare Tunnel; referensi di mode prompt mengikuti opsi (c) “AI dilarang mengarang, hanya dari sumber user”.

**TBD (butuh keputusan bigboss):**
1. Nama produk.
2. Target KPI final (angka di bagian 1).
3. Model AI final per tugas (setelah task 1.14).
4. Budget biaya AI per dokumen dan batas kuota Free.
5. Retensi data guest dan teks dokumen.
6. Harga paket Pro (per 30 hari).
7. Panduan format 3–5 kampus/sekolah acuan untuk template.
8. **Open question:** pengguna di bawah 18 tahun (pelajar SMA/SMK) — kebijakan privasi dan persetujuan orang tua/sekolah belum diputuskan.

**Referensi:**
- Spike: `D:\Project\project\testing\spike.php` (bukti builder + semua workaround), `contoh-makalah.docx`, `contoh-makalah.pdf` di folder yang sama.
- Desain: Stitch project `225495029269444949` (stitch.withgoogle.com).
- Flux UI free vs Pro: repo `livewire/flux` dan fluxui.dev/pricing.

**Publikasi:** draft lokal; belum dipublikasikan ke issue tracker mana pun.
