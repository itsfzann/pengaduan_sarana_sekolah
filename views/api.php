<?php
session_start();
header('Content-Type: application/json');

// --- 1. KONEKSI ---
$conn = mysqli_connect("localhost", "root", "", "pengaduan_sarana");
if (!$conn) die(json_encode(["status" => "error", "message" => "Koneksi Gagal"]));

$table  = $_GET['table'] ?? '';
$action = $_GET['action'] ?? '';
$auth   = $_GET['auth'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

// --- 2. FITUR AUTH (Login & Logout) ---
if (!empty($auth)) {
    if ($auth == 'login') {
        $username = mysqli_real_escape_string($conn, $_POST['username'] ?? '');
        $password = md5($_POST['password'] ?? '');

        $sql = "SELECT * FROM tb_user WHERE username='$username' AND password='$password'";
        $res = mysqli_query($conn, $sql);
        $user = mysqli_fetch_assoc($res);

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