/**
 * CORE ENGINE UNIVERSAL - script.js
 * Pendekatan Agnostik: Mendukung tabel apapun secara dinamis
 */
const API = "api.php";

// --- 1. MUAT TABEL (Dinamis & Mendukung Pencarian) ---
async function muatTabel(table, elementId, keyword = "") {
  const tableEl = document.getElementById(elementId);
  if (!tableEl) return;

  try {
    const url = `${API}?table=${table}&search=${encodeURIComponent(keyword)}`;
    const res = await fetch(url);
    let data = await res.json();

    // --- LOGIKA TAMBAHAN (SINKRONISASI FORMAT) ---
    // Jika data dibungkus dalam {status:..., data:...}, ambil bagian data-nya saja
    if (data && data.status && data.data) {
      data = data.data;
    }
    // --------------------------------------------

    const thead = tableEl.querySelector("thead");
    const tbody = tableEl.querySelector("tbody");

    if (Array.isArray(data) && data.length > 0) {
      const kolom = Object.keys(data[0]);
      const pk = kolom[0]; // Kolom pertama dianggap Primary Key

      // Buat Header Otomatis
      thead.innerHTML = `<tr>
                ${kolom.map((k) => `<th>${k.toUpperCase().replace(/_/g, " ")}</th>`).join("")}
                <th>AKSI</th>
            </tr>`;

      // Buat Baris Otomatis
      tbody.innerHTML = data
        .map((row) => {
          const safeJson = encodeURIComponent(JSON.stringify(row));
          return `<tr>
                    ${kolom.map((k) => `<td>${row[k] ?? ""}</td>`).join("")}
                    <td>
                        <button class="btn-edit" onclick="persiapanEdit('${safeJson}','${table}')">Edit</button>
                        <button class="btn-hapus" onclick="hapusData('${row[pk]}','${table}','${elementId}')">Hapus</button>
                    </td>
                </tr>`;
        })
        .join("");
    } else {
      tbody.innerHTML = `<tr><td colspan="100%" style="text-align:center">Data tidak ditemukan atau kosong.</td></tr>`;
    }
  } catch (e) {
    console.error("Gagal muat tabel:", e);
    const tbody = tableEl.querySelector("tbody");
    if (tbody)
      tbody.innerHTML =
        '<tr><td colspan="100%">Gagal mengambil data dari server.</td></tr>';
  }
}

// --- 2. SUBMIT DATA (Insert atau Update) ---
async function submitData(event, table, elementId) {
  event.preventDefault();
  const form = event.target;
  const formData = new FormData(form);
  const action = form.getAttribute("data-action") || "";

  // Primary key tidak selalu tersedia sebagai input hidden.
  // Ambil dari hidden PK jika ada, kalau tidak coba cari input yang kemungkinannya PK.
  let idVal = "";
  const pkInput = form.querySelector('input[type="hidden"]');
  if (pkInput && pkInput.value) idVal = pkInput.value;

  if (!idVal && action === "update") {
    const candidates = [
      "id_pelaporan",
      "NIS",
      "nis",
      "id_kategori",
      "id_aspirasi",
      "id_log",
      "username",
      "id_admin",
    ];
    for (const name of candidates) {
      const el = form.querySelector(`[name="${name}"]`);
      if (el && el.value) {
        idVal = el.value;
        break;
      }
    }
  }

  let url = `${API}?table=${table}`;
  if (action === "update" && idVal) {
    url += `&action=update&id=${encodeURIComponent(idVal)}`;
  }

  try {
    const res = await fetch(url, {
      method: "POST",
      body: formData,
    });
    const hasil = await res.json();

    if (hasil.status === "success") {
      alert(hasil.message || "Proses berhasil!");
      form.reset();
      form.removeAttribute("data-action");
      if (pkInput) pkInput.value = "";

      muatTabel(table, elementId);
    } else {
      alert("Gagal: " + (hasil.message || "Terjadi kesalahan"));
    }
  } catch (e) {
    console.error("Submit error:", e);
    alert("Terjadi kesalahan koneksi.");
  }
}

// --- 3. PERSIAPAN EDIT ---
function persiapanEdit(encodedRow, table) {
  const data = JSON.parse(decodeURIComponent(encodedRow));
  const form = document.querySelector(`form[data-table="${table}"]`);
  if (!form) {
    alert("Form edit tidak ditemukan untuk tabel ini.");
    return;
  }

  Object.keys(data).forEach((key) => {
    const input = form.elements[key];
    if (input) {
      if (key === "password") {
        input.value = "";
      } else {
        input.value = data[key];
      }
    }
  });

  form.setAttribute("data-action", "update");
  window.scrollTo({ top: 0, behavior: "smooth" });
}

// --- 4. HAPUS DATA ---
async function hapusData(id, table, elementId) {
  if (!confirm("Apakah Anda yakin ingin menghapus data ini?")) return;

  try {
    const res = await fetch(`${API}?table=${table}&action=delete&id=${id}`);
    const hasil = await res.json();

    alert(hasil.message || "Data dihapus.");
    if (hasil.status === "success") {
      muatTabel(table, elementId);
    }
  } catch (e) {
    console.error("Delete error:", e);
    alert("Gagal menghapus data.");
  }
}

// --- 5. DROPDOWN RELASI ---
async function muatOpsiRelasi() {
  const relasiElements = document.querySelectorAll("select[data-source-table]");
  relasiElements.forEach(async (select) => {
    const table = select.getAttribute("data-source-table");
    try {
      const res = await fetch(`${API}?table=${table}`);
      let data = await res.json();

      // Cek format data di dropdown juga
      if (data && data.status && data.data) data = data.data;

      if (Array.isArray(data)) {
        const defaultOption = select.options[0]
          ? select.options[0].outerHTML
          : '<option value="">-- Pilih --</option>';
        select.innerHTML =
          defaultOption +
          data
            .map((row) => {
              const keys = Object.keys(row);
              const id = row[keys[0]];
              const nama = row[keys[1]] || row[keys[2]] || id;
              return `<option value="${id}">${nama}</option>`;
            })
            .join("");
      }
    } catch (e) {
      console.error(`Gagal muat relasi ${table}:`, e);
    }
  });
}

// --- 6. STARTUP ---
document.addEventListener("DOMContentLoaded", () => {
  // Jalankan muat tabel otomatis
  document.querySelectorAll("table[data-source]").forEach((tbl) => {
    muatTabel(tbl.dataset.source, tbl.id);
  });
  // Jalankan muat dropdown otomatis
  muatOpsiRelasi();
});
