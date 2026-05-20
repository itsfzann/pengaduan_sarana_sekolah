<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

// Hindari output non-JSON (warning/notice) yang bisa membuat fetch gagal parse.
ini_set('display_errors', '0');
error_reporting(E_ALL);

// Jika terjadi error fatal/uncaught, tetap balikan JSON.
set_exception_handler(function ($e) {
    if (!headers_sent()) {
        http_response_code(500);
    }
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    exit;
});

// --- 1. KONEKSI ---
$conn = mysqli_connect("localhost", "root", "", "pengaduan_sarana");
if (!$conn) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Koneksi Gagal"]);
    exit;
}
mysqli_set_charset($conn, 'utf8mb4');

// Kesalahan mysqli jangan sampai output mentah (akan tetap dikonversi jadi JSON di handler)
mysqli_report(MYSQLI_REPORT_OFF);



$table  = $_GET['table'] ?? '';
$action = $_GET['action'] ?? '';
$auth   = $_GET['auth'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];
$endpoint = $_GET['endpoint'] ?? '';

// --- DASHBOARD ENDPOINT (SPA) ---
if (!empty($endpoint)) {
    // NOTE: endpoint khusus dashboard SPA, tidak mengganggu CRUD tabel.

    $now = date('Y-m-d');


    // KPI admin: waiting/proses/selesai asumsi status di tb_aspirasi kolom 'status'
    if ($endpoint === 'dashboard_kpi') {
        // fallback jika kolom status tidak ada akan error; untuk saat ini gunakan string statis yang umum.
        $waiting = 0;
        $proses  = 0;
        $selesai = 0;

        // Deteksi apakah kolom status ada (best effort)
        $colsRes = mysqli_query($conn, "SHOW COLUMNS FROM tb_aspirasi LIKE 'status'");
        if ($colsRes && mysqli_num_rows($colsRes) > 0) {
            $qWaiting = mysqli_query($conn, "SELECT COUNT(*) AS c FROM tb_aspirasi WHERE status='Menunggu'");
            $qProses  = mysqli_query($conn, "SELECT COUNT(*) AS c FROM tb_aspirasi WHERE status='Proses'");
            $qSelesai = mysqli_query($conn, "SELECT COUNT(*) AS c FROM tb_aspirasi WHERE status='Selesai'");
            $waiting = ($qWaiting) ? (int)mysqli_fetch_assoc($qWaiting)['c'] : 0;
            $proses  = ($qProses) ? (int)mysqli_fetch_assoc($qProses)['c'] : 0;
            $selesai = ($qSelesai) ? (int)mysqli_fetch_assoc($qSelesai)['c'] : 0;
        }

        echo json_encode(["status" => "success", "data" => [
            "waiting" => $waiting,
            "proses"  => $proses,
            "selesai" => $selesai
        ]]);
        exit;
    }

    // Top kategori bulan ini: asumsi relasi tb_aspirasi -> tb_kategori melalui id_kategori atau join tb_input_aspirasi
    if ($endpoint === 'dashboard_top_kategori_bulan_ini') {
        // Best-effort query: join tb_input_aspirasi + tb_aspirasi + tb_kategori + tb_input_aspirasi.tgl_pelaporan
        $bulan = date('m');
        $tahun = date('Y');

        $sql = "SELECT k.ket_kategori AS kategori, COUNT(*) AS jumlah
                FROM tb_input_aspirasi i
                JOIN tb_kategori k ON k.id_kategori = i.id_kategori
                WHERE MONTH(i.tgl_pelaporan) = '$bulan' AND YEAR(i.tgl_pelaporan) = '$tahun'
                GROUP BY k.id_kategori, k.ket_kategori
                ORDER BY jumlah DESC
                LIMIT 6";

        $res = mysqli_query($conn, $sql);
        $rows = [];
        if ($res) {
            while ($r = mysqli_fetch_assoc($res)) $rows[] = $r;
        }
        echo json_encode(["status" => "success", "data" => $rows]);
        exit;
    }

    // Tren 6 bulan: agregasi per bulan + kategori
    if ($endpoint === 'dashboard_tren_6_bulan') {
        $sql = "SELECT DATE_FORMAT(i.tgl_pelaporan,'%Y-%m') AS bulan,
                       k.ket_kategori AS kategori,
                       COUNT(*) AS jumlah
                FROM tb_input_aspirasi i
                JOIN tb_kategori k ON k.id_kategori = i.id_kategori
                WHERE i.tgl_pelaporan >= DATE_SUB(CURDATE(), INTERVAL 5 MONTH)
                GROUP BY bulan, k.id_kategori, k.ket_kategori
                ORDER BY bulan ASC";

        $res = mysqli_query($conn, $sql);
        $rows = [];
        if ($res) {
            while ($r = mysqli_fetch_assoc($res)) $rows[] = $r;
        }
        echo json_encode(["status" => "success", "data" => $rows]);
        exit;
    }

    // Drill down: detail aspirasi per kategori + rentang
    if ($endpoint === 'dashboard_drill') {
        $kategori = $_GET['kategori'] ?? '';
        $rentang = $_GET['rentang'] ?? 'bulan_ini';

        // cari id_kategori dari ket_kategori
        $id_k = '';
        $q = mysqli_query($conn, "SELECT id_kategori FROM tb_kategori WHERE ket_kategori='" . mysqli_real_escape_string($conn,$kategori) . "' LIMIT 1");
        if ($q && mysqli_num_rows($q) > 0) $id_k = mysqli_fetch_assoc($q)['id_kategori'];

        if ($id_k === '') {
            echo json_encode(["status" => "success", "data" => []]);
            exit;
        }

        $where = " i.id_kategori='" . mysqli_real_escape_string($conn,$id_k) . "' ";
        if ($rentang === 'bulan_ini') {
            $where .= " AND MONTH(i.tgl_pelaporan)=MONTH(CURDATE()) AND YEAR(i.tgl_pelaporan)=YEAR(CURDATE()) ";
        } elseif ($rentang === '6_bulan') {
            $where .= " AND i.tgl_pelaporan >= DATE_SUB(CURDATE(), INTERVAL 5 MONTH) ";
        }

        $sql = "SELECT i.id_pelaporan, i.nis, i.lokasi, i.ket, a.status
                FROM tb_input_aspirasi i
                LEFT JOIN tb_aspirasi a ON a.id_pelaporan = i.id_pelaporan
                WHERE $where
                ORDER BY i.id_pelaporan DESC";

        $res = mysqli_query($conn, $sql);
        $rows = [];
        if ($res) {
            while ($r = mysqli_fetch_assoc($res)) $rows[] = $r;
        }

        echo json_encode(["status" => "success", "data" => $rows]);
        exit;
    }

    // Progres siswa (best-effort): agregasi per bulan untuk nis user jika tersedia
    if ($endpoint === 'dashboard_progres_siswa') {
        $user_id = $_GET['user_id'] ?? '';
        $nis = '';
        if (!empty($user_id)) {
            // asumsikan tb_siswa memiliki username/id terhubung ke tb_user? tidak jelas.
            // fallback: gunakan user_id sebagai NIS jika memang sama.
            $nis = $user_id;
        }

        $sql = "SELECT DATE_FORMAT(i.tgl_pelaporan,'%Y-%m') AS bulan,
                       SUM(CASE WHEN a.status='Menunggu' THEN 1 ELSE 0 END) AS menunggu,
                       SUM(CASE WHEN a.status='Proses' THEN 1 ELSE 0 END) AS proses,
                       SUM(CASE WHEN a.status='Selesai' THEN 1 ELSE 0 END) AS selesai
                FROM tb_input_aspirasi i
                LEFT JOIN tb_aspirasi a ON a.id_pelaporan = i.id_pelaporan
                WHERE i.nis='" . mysqli_real_escape_string($conn,$nis) . "'
                GROUP BY bulan
                ORDER BY bulan DESC
                LIMIT 6";

        $res = mysqli_query($conn, $sql);
        $rows = [];
        if ($res) {
            while ($r = mysqli_fetch_assoc($res)) $rows[] = $r;
        }
        echo json_encode(["status" => "success", "data" => array_reverse($rows)]);
        exit;
    }

    // Gamifikasi (best-effort): poin = selesai*10 + proses*5, progress = poin%100
    if ($endpoint === 'dashboard_gamification') {
        $user_id = $_GET['user_id'] ?? '';
        $nis = $user_id;

        $sql = "SELECT
                    SUM(CASE WHEN a.status='Selesai' THEN 1 ELSE 0 END) AS selesai_cnt,
                    SUM(CASE WHEN a.status='Proses' THEN 1 ELSE 0 END) AS proses_cnt
                FROM tb_input_aspirasi i
                LEFT JOIN tb_aspirasi a ON a.id_pelaporan = i.id_pelaporan
                WHERE i.nis='" . mysqli_real_escape_string($conn,$nis) . "'";

        $res = mysqli_query($conn, $sql);
        $row = ($res) ? mysqli_fetch_assoc($res) : null;
        $selesaiCnt = (int)($row['selesai_cnt'] ?? 0);
        $prosesCnt = (int)($row['proses_cnt'] ?? 0);

        $poin = $selesaiCnt * 10 + $prosesCnt * 5;
        $level = (int)floor($poin / 100) + 1;
        $progress = $poin % 100;

        echo json_encode(["status" => "success", "data" => [
            "poin" => $poin,
            "level" => $level,
            "progress" => $progress
        ]]);
        exit;
    }
}



// --- 2. FITUR AUTH (Login & Logout) ---
if (!empty($auth)) {
    if ($auth == 'login') {
        $username = $_POST['username'] ?? '';
        $passwordRaw = $_POST['password'] ?? '';
        $passwordHash = md5($passwordRaw);

        // Catatan: password tetap menggunakan md5 agar kompatibel dengan data yang sudah ada.
        $stmt = mysqli_prepare($conn, "SELECT * FROM tb_user WHERE username=? AND password=? LIMIT 1");
        mysqli_stmt_bind_param($stmt, "ss", $username, $passwordHash);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $user = mysqli_fetch_assoc($res);
        mysqli_stmt_close($stmt);

        if ($user) {
            $_SESSION['user_id'] = $user['id_user'];
            $_SESSION['role']    = $user['role'];
            $_SESSION['nama']    = $user['nama_lengkap'];

            echo json_encode([
                "status" => "success", 
                "message" => "Login Berhasil",
                "role" => $user['role'],
                "nama" => $user['nama_lengkap']
            ]);
        } else {
            echo json_encode(["status" => "error", "message" => "Username atau Password Salah"]);
        }
    } elseif ($auth == 'logout') {
        session_destroy();
        echo json_encode(["status" => "success", "message" => "Berhasil Logout"]);
    }
    exit;
}

// --- 3. CORE ENGINE (CRUD UNIVERSAL) ---
if ($table) {
    // Ambil info Primary Key secara dinamis
    $pk_res = mysqli_query($conn, "SHOW KEYS FROM `$table` WHERE Key_name = 'PRIMARY'");
    $pk_row = mysqli_fetch_assoc($pk_res);
    $pk     = $pk_row['Column_name'] ?? 'id';

    // --- READ & SEARCH ---
    if ($method == 'GET' && empty($action)) {
        $search = mysqli_real_escape_string($conn, $_GET['search'] ?? '');
        $where = "";

        if (!empty($search)) {
            // Logika pencarian cerdas untuk tabel apapun
            $cols_res = mysqli_query($conn, "SHOW COLUMNS FROM `$table`");
            $all_cols = mysqli_fetch_all($cols_res, MYSQLI_ASSOC);
            
            // Cari berdasarkan kolom ke-2 atau ke-3 (biasanya Nama atau Aktivitas)
            $col1 = $all_cols[1]['Field'] ?? ''; 
            $col2 = $all_cols[2]['Field'] ?? '';
            
            if ($table == 'tb_log_aktivitas') {
                // Khusus tabel log, kita prioritaskan cari di kolom 'aktivitas'
                $where = " WHERE `aktivitas` LIKE '%$search%' ";
            } elseif ($col2) {
                $where = " WHERE `$col1` LIKE '%$search%' OR `$col2` LIKE '%$search%' ";
            } elseif ($col1) {
                $where = " WHERE `$col1` LIKE '%$search%' ";
            }
        }

        // --- PENYESUAIAN ORDER BY ---
        $orderBy = "ORDER BY `$pk` DESC";
        // Jika tabel log, pastikan urutan berdasarkan id_log terbaru
        if ($table == 'tb_log_aktivitas') {
            $orderBy = "ORDER BY id_log DESC";
        }

        $sql = "SELECT * FROM `$table` $where $orderBy";
        $res = mysqli_query($conn, $sql);
        
        // Output Array Murni (Langsung [...]) agar script.js tidak error
        if ($res === false) {
            echo json_encode(["status" => "error", "message" => mysqli_error($conn)]);
            exit;
        }
        echo json_encode(mysqli_fetch_all($res, MYSQLI_ASSOC));
        exit;
    }

    // --- INSERT & UPDATE ---
    if ($method == 'POST') {
        $data = $_POST;
        $db_cols_res = mysqli_query($conn, "SHOW COLUMNS FROM `$table`");
        $db_cols = array_column(mysqli_fetch_all($db_cols_res, MYSQLI_ASSOC), 'Field');

        $filtered_data = [];
        foreach ($data as $key => $value) {
            if (in_array($key, $db_cols) && $key !== $pk) {
                $val = mysqli_real_escape_string($conn, $value);
                if ($key === 'password') {
                    if (!empty($val)) $filtered_data[$key] = md5($val);
                } else {
                    $filtered_data[$key] = $val;
                }
            }
        }

        if (empty($filtered_data)) {
            die(json_encode(["status" => "error", "message" => "Tidak ada data valid"]));
        }

        if ($action == 'update') {
            $id = mysqli_real_escape_string($conn, $_GET['id'] ?? '');
            $sets = [];
            foreach ($filtered_data as $k => $v) $sets[] = "`$k`='$v'";
            $sql = "UPDATE `$table` SET " . implode(", ", $sets) . " WHERE `$pk`='$id'";
            $msg = "Update Berhasil";
        } else {
            $cols = "`" . implode("`,`", array_keys($filtered_data)) . "`";
            $vals = "'" . implode("','", array_values($filtered_data)) . "'";
            $sql = "INSERT INTO `$table` ($cols) VALUES ($vals)";
            $msg = "Simpan Berhasil";
        }

        if (mysqli_query($conn, $sql)) {
            echo json_encode(["status" => "success", "message" => $msg]);
        } else {
            echo json_encode(["status" => "error", "message" => mysqli_error($conn)]);
        }
        exit;
    }

    // --- DELETE ---
    if ($method == 'GET' && $action == 'delete') {
        $id = mysqli_real_escape_string($conn, $_GET['id'] ?? '');
        if (mysqli_query($conn, "DELETE FROM `$table` WHERE `$pk`='$id'")) {
            echo json_encode(["status" => "success", "message" => "Data Berhasil Dihapus"]);
        } else {
            echo json_encode(["status" => "error", "message" => mysqli_error($conn)]);
        }
        exit;
    }
}