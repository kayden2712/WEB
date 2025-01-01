<?php
header('Content-Type: text/html; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database configuration
$host = 'localhost';
$dbname = 'quanlysinhvien';
$username = 'root';
$password = '';

try {
    // Database connection
    $conn = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4")
    );
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Xử lý xuất CSV
    if (isset($_POST['export'])) {
        $table = $_POST['table'];
        
        // Lấy dữ liệu từ bảng được chọn
        $stmt = $conn->prepare("SELECT * FROM $table");
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($results)) {
            $filename = $table . "_" . date('Y-m-d_His') . ".csv";
            
            // Thiết lập header cho file CSV
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            
            // Tạo file handle
            $output = fopen('php://output', 'w');
            fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // Add BOM for UTF-8
            
            // Thêm header
            fputcsv($output, array_keys($results[0]));
            
            // Thêm dữ liệu
            foreach ($results as $row) {
                fputcsv($output, $row);
            }
            
            fclose($output);
            exit();
        }
    }

    // Xử lý nhập CSV
    if (isset($_POST['import']) && isset($_FILES['csvFile'])) {
        $table = $_POST['table'];
        $file = $_FILES['csvFile'];
        
        if ($file['error'] == UPLOAD_ERR_OK) {
            $handle = fopen($file['tmp_name'], "r");
            
            // Đọc và bỏ qua BOM nếu có
            $bom = fgets($handle, 4);
            if (!in_array($bom, array("\xEF\xBB\xBF", "\xFE\xFF", "\xFF\xFE"))) {
                rewind($handle);
            }
            
            // Đọc header và làm sạch
            $header = fgetcsv($handle);
            $header = array_map(function($column) {
                return trim(str_replace("\xEF\xBB\xBF", '', $column));
            }, $header);

            // Debug: In ra header gốc
            error_log("Original headers: " . print_r($header, true));

            // Ánh xạ tên cột theo bảng
            $columnMappings = [
                'sinhvien' => [
                    'MaSV' => 'masv',
                    'HoTen' => 'hoten',
                    'NgaySinh' => 'ngaysinh',
                    'Lop' => 'lop',
                    'Khoa' => 'khoa'
                ],
                'monhoc' => [
                    'MaMH' => 'mamh',
                    'TenMH' => 'tenmh',
                    'SoTC' => 'sotc',
                    'GiangVien' => 'giangvien',
                    'SoLuong' => 'soluong'
                ],
                'dangkymonhoc' => [
                    'MaSV' => 'masv',
                    'MaMH' => 'mamh',
                    'NgayDK' => 'ngaydk'
                ]
            ];

            // Ánh xạ header từ CSV sang tên cột trong DB
            $mappedHeader = [];
            foreach ($header as $col) {
                if (isset($columnMappings[$table][$col])) {
                    $mappedHeader[] = $columnMappings[$table][$col];
                } else {
                    // Nếu không tìm thấy ánh xạ, giữ nguyên tên cột
                    $mappedHeader[] = strtolower($col);
                }
            }

            // Debug: In ra header đã ánh xạ
            error_log("Mapped headers: " . print_r($mappedHeader, true));
            
            // Chuẩn bị câu lệnh INSERT ... ON DUPLICATE KEY UPDATE
            $placeholders = str_repeat('?,', count($mappedHeader) - 1) . '?';
            $columns = implode(',', $mappedHeader);
            
            // Debug: In ra câu lệnh SQL
            error_log("SQL columns: " . $columns);
            
            // Tạo phần UPDATE của câu lệnh
            $updates = array_map(function($col) {
                return "$col=VALUES($col)";
            }, $mappedHeader);
            $updateStr = implode(',', $updates);
            
            // Câu lệnh SQL với ON DUPLICATE KEY UPDATE
            $sql = "INSERT INTO $table ($columns) VALUES ($placeholders) 
                    ON DUPLICATE KEY UPDATE $updateStr";
            
            // Debug: In ra câu lệnh SQL hoàn chỉnh
            error_log("Final SQL: " . $sql);
            
            $stmt = $conn->prepare($sql);
            
            // Đọc và insert/update từng dòng dữ liệu
            $conn->beginTransaction();
            try {
                while (($data = fgetcsv($handle)) !== FALSE) {
                    // Debug: In ra dữ liệu từng dòng
                    error_log("Row data: " . print_r($data, true));
                    $stmt->execute($data);
                }
                $conn->commit();
                $message = "Nhập dữ liệu thành công!";
            } catch (Exception $e) {
                $conn->rollBack();
                $message = "Lỗi khi nhập dữ liệu: " . $e->getMessage();
                // Debug: In ra thông tin lỗi chi tiết
                error_log("Import error: " . $e->getMessage());
            }
            
            fclose($handle);
        }
    }

} catch(PDOException $e) {
    $message = "Lỗi kết nối database: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý dữ liệu CSV</title>
    <style>
        body {
            font-family: "Palatino Linotype", Times, serif;
            margin: 10px;
            background-color: #f5f5f5;
            /* overflow: hidden; */
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        h2 {
            color: #333;
            text-align: center;
        }
        .form-group {
            margin-bottom: 20px;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        select, input[type="file"] {
            width: 100%;
            padding: 8px;
            margin-bottom: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        button {
            background-color: darkblue;
            color: white;
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
            font-size: 15px;
            font-family: "Palatino Linotype", Times, serif;
        }
        button:hover {
            background-color: blue;
        }
        .message {
            padding: 10px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        .success {
            background-color: #dff0d8;
            color: #3c763d;
            border: 1px solid #d6e9c6;
        }
        .error {
            background-color: #f2dede;
            color: #a94442;
            border: 1px solid #ebccd1;
        }
        .back-button {
            background-color: darkred;
            margin-top: 10px;
            font-weight: bold;
            font-size: 15px;
            font-family: "Palatino Linotype", Times, serif;
            color: white;
        }
        .back-button:hover {
            background-color: red;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Quản lý dữ liệu CSV</h2>
        
        <?php if (isset($message)): ?>
            <div class="message <?php echo strpos($message, 'Lỗi') !== false ? 'error' : 'success'; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <!-- Form xuất CSV -->
        <div class="form-group">
            <h3>Xuất dữ liệu</h3>
            <form method="post">
                <label for="export-table">Chọn bảng để xuất:</label>
                <select name="table" id="export-table" required>
                    <option value="sinhvien">Sinh viên</option>
                    <option value="monhoc">Môn học</option>
                    <option value="dangkymonhoc">Đăng ký môn học</option>
                </select>
                <button type="submit" name="export">Xuất CSV</button>
            </form>
        </div>

        <!-- Form nhập CSV -->
        <div class="form-group">
            <h3>Nhập dữ liệu</h3>
            <form method="post" enctype="multipart/form-data">
                <label for="import-table">Chọn bảng để nhập:</label>
                <select name="table" id="import-table" required>
                    <option value="sinhvien">Sinh viên</option>
                    <option value="monhoc">Môn học</option>
                    <option value="dangkymonhoc">Đăng ký môn học</option>
                </select>
                <label for="csvFile">Chọn file CSV:</label>
                <input type="file" name="csvFile" id="csvFile" accept=".csv" required>
                <button type="submit" name="import">Nhập CSV</button>
            </form>
        </div>

        <!-- Nút quay lại -->
        <button class="back-button" onclick="window.location.href='Home.html'">Quay lại trang chủ</button>
    </div>
</body>
</html>
