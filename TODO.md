# TODO - Perbaikan Bug + Tampilan + Login + Header

- [x] 1. Tambahkan halaman `views/login.html`
  - [ ] Form username/password
  - [ ] POST ke `api.php?auth=login`
  - [ ] Redirect sukses ke `views/admin.html`
  - [ ] Tangani pesan error

- [x] 2. Tambahkan file CSS global `css/layout.css`
  - [x] Style background + container umum
  - [x] Style header/nav (senada dengan tema biru)

- [x] 3. Update semua halaman CRUD untuk pakai header
  - [ ] Edit `views/admin.html`
  - [ ] Edit `views/siswa.html`
  - [ ] Edit `views/kategori.html`
  - [ ] Edit `views/aspirasi.html`

- [ ] 4. Seragamkan CSS halaman
  - [ ] Rapikan `css/admin.css`, `css/siswa.css`, `css/kategori.css`, `css/aspirasi.css`
  - [ ] Pindahkan style umum ke `css/layout.css`

- [x] 5. Fix bug potensial edit/update/delete pada `views/script.js`
  - [ ] Pastikan PK yang dipakai untuk update/delete konsisten
  - [ ] Pastikan edit mengisi field PK yang benar

- [ ] 6. Testing manual
  - [ ] Test login/logout
  - [ ] Test CRUD: tampilkan, edit, hapus
  - [ ] Pastikan tampilan rapi dan responsive
