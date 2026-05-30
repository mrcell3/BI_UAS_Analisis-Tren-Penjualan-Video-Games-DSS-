Anggota Kelompok:
1. Nayla Lelyanggraheni Hutomo (2409116061)
2. Jemis Movid (2409116070)
3. Marcela Persa Linthin (2409116072)

# Cupcorn Entertainment - Game Sales (DSS)

Aplikasi Sistem Pendukung Keputusan berbasis web yang dirancang khusus untuk menganalisis dan memvisualisasikan data historis penjualan video game global (1980–2020). 
Sistem ini menerapkan sistem Business Intelligence dan Decision Support System (DSS) untuk membantu perusahaan dalam memahami tren pasar, perilaku pemain, serta performa penjualan game berdasarkan data historis.

---

## 🎯 Fitur Utama & Analisis (Analytics Module)

Sistem ini menyediakan 5 modul analisis interaktif yang dilengkapi dengan fitur **Sistem Pendukung Keputusan (DSS) Berbasis Status Dinamis**:

### 1. Genre Analysis (Analisis performa genre game)
Menu ini digunakan untuk melihat segmentasi pasar game secara makro berdasarkan jenis/genre game. Manajer bisa melihat genre apa yang paling mendominasi pasar global sepanjang sejarah atau pada era tahun tertentu.

**Status DSS yang Dihasilkan:**

     🔴 Niche Market: Status otomatis ketika sebuah genre memiliki total volume penjualan yang sangat terbatas pada rentang tahun terpilih (di bawah batas ambang komersial minimum 50M sales).
   
     🟡 Declining: Mendeteksi adanya indikasi penurunan performa secara spesifik dengan membandingkan volume penjualan tahun final (*last year*) terhadap satu tahun sebelumnya (*previous year*) pada filter aktif.
   
     🟢 High Potential: Kategori untuk genre-genre utama yang mempertahankan volume penjualan tinggi serta konsisten menjaga stabilitas performa komersialnya di berbagai wilayah global.

### 2. Platform Analysis (Analisis performa platform game)
Menu ini berfokus pada ekosistem perangkat keras atau konsol game. Gunanya untuk mengetahui platform mana yang paling menguntungkan untuk merilis sebuah game pada periode tahun terpilih.

**Status DSS yang Dihasilkan:**

     🟢 Stable Market: Platform yang mendominasi pasar pada era terpilih dengan angka penjualan di atas rata-rata industri serta memiliki basis pengguna yang besar.
   
     🟡 Casual Market: Platform dengan volume penjualan di bawah rata-rata industri pada era tersebut, cenderung bergerak stabil pada segmentasi game kasual/keluarga.

### 3. Regional Analysis (Analisis penjualan berdasarkan wilayah)
Menu ini memetakan performa penjualan berdasarkan wilayah geografis dunia (North America, Europe, Japan, dan negara lainnya). Manajer menggunakannya untuk menentukan target pasar utama atau wilayah distribusi game.

**Status DSS yang Dihasilkan:**

     🟢 Major Market: Wilayah kontributor utama yang menyumbang $\ge$ 25% dari total penjualan dunia pada era aktif.
   
     🟡 Specialized Market: Wilayah dengan pangsa pasar lebih kecil ($<$ 25%), namun memiliki preferensi platform dan genre yang sangat unik (contohnya pasar Jepang).

### 4. Publisher Analysis (Analisis performa publisher)
Menyediakan fitur *Competitor Intelligence* untuk melakukan analisis kompetitor terhadap perusahaan-perusahaan penerbit game lain di industri dengan melacak performa volume penjualan dari Top 10 Publisher di industri game.
Menu ini sengaja didesain bersih (*Clean UI*) tanpa panel status tambahan karena informasi peringkat persaingan kuantitatifnya sudah terjawab secara instan melalui grafik peringkat Top 10 untuk menghindari penumpukan informasi (*information overload*).

### 5. Rating Analysis (Analisis hubungan rating dan penjualan)
Menganalisis hubungan sebab-akibat (korelasi) antara tingkat kepuasan pemain (*Rating*) dengan performa penjualan (*Sales*).

**Status DSS yang Dihasilkan:**

     🟢 High Rating High Sales: Genre yang memiliki kualitas rating tinggi ($\ge$ 3.65) dan sukses besar di pasar (Performa produk sangat optimal).
   
     🟡 High Rating Low Sales: Game berkualitas tinggi dan disukai konsumen, tetapi kurang optimal dalam komersial/volume penjualan pada era tersebut.
   
     🔵 Low Rating High Sales: Rating genre cenderung biasa saja/rendah (< 3.65), namun performa volume penjualannya di pasar sangat tinggi (Produk tetap laris karena faktor pasar).
   
     🔴 Low Rating Low Sales: Genre dengan kualitas rating maupun tingkat penjualan yang berada di bawah standar performa industri pada era tersebut.

---

## 🔐 Manajemen Hak Akses

Aplikasi ini menerapkan prinsip *Separation of Duty* (Pemisahan Tugas) melalui pembagian 2 hak akses pengguna utama:

* **Publisher Manager (Eksekutif):** Bertindak sebagai pengambil keputusan bisnis. Memiliki akses penuh terhadap seluruh visualisasi modul analisis (*Read-Only*) beserta filter rentang tahun, tanpa akses mengubah data mentah demi keamanan sistem.
* **Data Integration Staff (Operasional):** Bertindak sebagai Superuser yang memegang kendali operasional aplikasi. Memiliki akses penuh untuk operasi **CRUD (Create, Read, Update, Delete)** data transaksi pada modul *Data Management*, serta hak akses membaca visualisasi untuk kebutuhan verifikasi dan penjaminan mutu data (*Quality Control*).

---

## 🚀 Cara Instalasi di Lokal (Local Deployment)

Ikuti langkah-langkah berikut untuk menjalankan proyek ini di komputer lokal Anda:

1.  **Clone Repositori:**
    ```bash
    git clone (https://github.com/mrcell3/BI_UAS_Analisis-Tren-Penjualan-Video-Games-DSS-.git)
    ```
2.  **Pindahkan Proyek:**
    Pindahkan folder proyek ke direktori server lokal Anda (misal `C:\laragon\www\` atau `C:\xampp\htdocs\`).
3.  **Konfigurasi Database:**
    - Buka browser dan masuk ke `phpMyAdmin`.
    - Buat database baru bernama `game_dss`.
    - Import file database SQL (`game_dss.sql`) ke dalam database baru tersebut.
4.  **Sesuaikan Koneksi Database:**
    Buka file konfigurasi database di `config/db.php` dan sesuaikan kredensial server lokal Anda:
    ```php
    // config/db.php
    $host = 'localhost';
    $user = 'root';
    $pass = ''; // 
    $db   = 'game_dss';
    ```
5.  **Jalankan Aplikasi:**
    Buka browser Anda dan akses URL lokal proyek, `http://localhost/game_dss` atau `http://game_dss.test`.

---

## 🔑 Kredensial Akun Demo (Untuk Pengujian)

Untuk menguji fitur multi-role tanpa melakukan registrasi manual, gunakan akun demo berikut pada halaman login:

* **Akun Manager (Publisher Manager):**
    * Email: `manager@gmail.com`
    * Password: `password`
* **Akun Staff (Data Integration Staff):**
    * Email: `staff@gmail.com`
    * Password: `password`

---
