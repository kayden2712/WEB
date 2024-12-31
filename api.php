<?php
header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Thêm log để debug
file_put_contents('debug.log', date('Y-m-d H:i:s') . " - Request: " . $_SERVER['REQUEST_URI'] . "\n", FILE_APPEND);

// Database configuration
$host = 'localhost';
$dbname = 'quanlysinhvien';
$username = 'root';
$password = '';

// Helper functions
function validateDate($date, $format = 'Y-m-d') {
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}

function sendJsonResponse($success, $data = null, $message = '') {
    echo json_encode([
        'success' => $success,
        'data' => $data,
        'message' => $message
    ]);
    exit;
}

try {
    // Database connection
    $conn = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4")
    );
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Get request method and action
    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? '';

    switch ($action) {
        case 'searchStudent':
            $keyword = trim($_GET['keyword'] ?? '');
            if (empty($keyword)) {
                throw new Exception('Từ khóa tìm kiếm không được để trống');
            }
            
            $stmt = $conn->prepare("
                SELECT * FROM sinhvien 
                WHERE MaSV LIKE ? 
                OR HoTen LIKE ?
                ORDER BY 
                    CASE WHEN MaSV LIKE ? THEN 0 
                         WHEN HoTen LIKE ? THEN 1 
                    ELSE 2 END,
                    HoTen ASC
            ");
            
            $searchPattern = "%$keyword%";
            $stmt->execute([$searchPattern, $searchPattern, $searchPattern, $searchPattern]);
            sendJsonResponse(true, $stmt->fetchAll(PDO::FETCH_ASSOC));
            break;

        case 'addStudent':
            if ($method !== 'POST') {
                throw new Exception('Method not allowed');
            }

            $data = json_decode(file_get_contents('php://input'), true);
            
            // Validate data
            $maSV = isset($data['code']) ? trim($data['code']) : '';
            $hoTen = isset($data['name']) ? trim($data['name']) : '';
            $ngaySinh = isset($data['birthDay']) ? trim($data['birthDay']) : '';
            $lop = isset($data['classs']) ? trim($data['classs']) : '';
            $khoa = isset($data['fos']) ? trim($data['fos']) : '';

            if (empty($maSV) || empty($hoTen) || empty($ngaySinh) || empty($lop) || empty($khoa)) {
                throw new Exception('Vui lòng điền đầy đủ thông tin sinh viên');
            }

            if (!preg_match('/^SV\d{3,}$/', $maSV)) {
                throw new Exception('Mã sinh viên không hợp lệ (phải bắt đầu bằng SV và có ít nhất 3 số)');
            }

            if (!validateDate($ngaySinh)) {
                throw new Exception('Ngày sinh không hợp lệ');
            }

            // Check if student exists
            $stmt = $conn->prepare("SELECT COUNT(*) FROM sinhvien WHERE MaSV = ?");
            $stmt->execute([$maSV]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception('Mã sinh viên đã tồn tại trong hệ thống');
            }

            // Add new student
            $sql = "INSERT INTO sinhvien (MaSV, HoTen, NgaySinh, Lop, Khoa) VALUES (?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $result = $stmt->execute([$maSV, $hoTen, $ngaySinh, $lop, $khoa]);

            sendJsonResponse($result, null, $result ? 'Thêm sinh viên thành công' : 'Không thể thêm sinh viên');
            break;

        case 'editStudent':
            if ($method !== 'POST') {
                throw new Exception('Method not allowed');
            }

            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['code']) || !isset($data['name']) || !isset($data['birthDay']) 
                || !isset($data['classs']) || !isset($data['fos'])) {
                throw new Exception('Thiếu thông tin cần thiết');
            }

            $sql = "UPDATE sinhvien 
                    SET HoTen = :name, 
                        NgaySinh = :birthDay, 
                        Lop = :classs, 
                        Khoa = :fos 
                    WHERE MaSV = :code";
            
            $stmt = $conn->prepare($sql);
            $success = $stmt->execute([
                ':name' => $data['name'],
                ':birthDay' => $data['birthDay'],
                ':classs' => $data['classs'],
                ':fos' => $data['fos'],
                ':code' => $data['code']
            ]);

            sendJsonResponse($success && $stmt->rowCount() > 0, null, $success ? ($stmt->rowCount() > 0 ? 'Cập nhật thông tin sinh viên thành công' : 'Không tìm thấy sinh viên hoặc không có thay đổi') : 'Lỗi khi thực thi câu lệnh SQL');
            break;

        case 'deleteStudent':
            if ($method !== 'POST') {
                throw new Exception('Method not allowed');
            }

            $data = json_decode(file_get_contents('php://input'), true);
            $studentId = trim($data['studentId'] ?? '');
            
            if (empty($studentId)) {
                throw new Exception('Mã sinh viên không được để trống');
            }

            $stmt = $conn->prepare("DELETE FROM sinhvien WHERE MaSV = ?");
            $stmt->execute([$studentId]);

            sendJsonResponse($stmt->rowCount() > 0, null, $stmt->rowCount() > 0 ? 'Đã xóa sinh viên thành công' : 'Không tìm thấy sinh viên');
            break;

        case 'getStudents':
            $stmt = $conn->prepare("SELECT * FROM sinhvien ORDER BY id ASC");
            $stmt->execute();
            sendJsonResponse(true, $stmt->fetchAll(PDO::FETCH_ASSOC));
            break;

        case 'getCourses':
        case 'getCourseList':
            file_put_contents('debug.log', date('Y-m-d H:i:s') . " - Getting course list\n", FILE_APPEND);
            $stmt = $conn->prepare("SELECT * FROM monhoc ORDER BY MaMH");
            $stmt->execute();
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            file_put_contents('debug.log', date('Y-m-d H:i:s') . " - Found " . count($result) . " courses\n", FILE_APPEND);
            echo json_encode(['success' => true, 'data' => $result]);
            exit;
            break;

        case 'registerCourse':
            if ($method !== 'POST') {
                throw new Exception('Method not allowed');
            }

            $data = json_decode(file_get_contents('php://input'), true);
            $studentId = $data['studentId'] ?? '';
            $courseIds = $data['courseIds'] ?? [];

            if (empty($studentId) || empty($courseIds)) {
                sendJsonResponse(false, null, 'Thiếu thông tin đăng ký');
                exit;
            }

            try {
                $successCourses = [];
                $errorCourses = [];

                // Kiểm tra sinh viên có tồn tại không
                $checkStudent = $conn->prepare("SELECT 1 FROM sinhvien WHERE MaSV = ?");
                $checkStudent->execute([$studentId]);
                if (!$checkStudent->fetch()) {
                    throw new Exception('Mã sinh viên không tồn tại');
                }

                // Kiểm tra tất cả các đăng ký trùng và tình trạng môn học
                $placeholders = str_repeat('?,', count($courseIds) - 1) . '?';
                
                // Kiểm tra môn học đã đăng ký
                $checkRegistered = $conn->prepare("
                    SELECT MaMH 
                    FROM dangkymonhoc 
                    WHERE MaSV = ? 
                    AND MaMH IN ($placeholders)
                ");
                $checkRegistered->execute(array_merge([$studentId], $courseIds));
                $registeredCourses = $checkRegistered->fetchAll(PDO::FETCH_COLUMN);

                // Kiểm tra thông tin các môn học
                $checkCourses = $conn->prepare("
                    SELECT MaMH, SoLuongDaDangKy, SoLuongMax 
                    FROM monhoc 
                    WHERE MaMH IN ($placeholders)
                ");
                $checkCourses->execute($courseIds);
                $courseInfo = $checkCourses->fetchAll(PDO::FETCH_ASSOC);
                $validCourses = [];
                $courseStatus = [];

                // Xử lý thông tin môn học
                foreach ($courseInfo as $course) {
                    $courseStatus[$course['MaMH']] = $course;
                }

                // Phân loại các môn học
                foreach ($courseIds as $courseId) {
                    if (in_array($courseId, $registeredCourses)) {
                        $errorCourses[] = ["courseId" => $courseId, "reason" => "Đã đăng ký trước đó"];
                    } elseif (!isset($courseStatus[$courseId])) {
                        $errorCourses[] = ["courseId" => $courseId, "reason" => "Môn học không tồn tại"];
                    } elseif ($courseStatus[$courseId]['SoLuongDaDangKy'] >= $courseStatus[$courseId]['SoLuongMax']) {
                        $errorCourses[] = ["courseId" => $courseId, "reason" => "Lớp đã đầy"];
                    } else {
                        $validCourses[] = $courseId;
                    }
                }

                // Nếu có môn học hợp lệ để đăng ký
                if (!empty($validCourses)) {
                    $conn->beginTransaction();

                    foreach ($validCourses as $courseId) {
                        try {
                            // Cập nhật số lượng đăng ký
                            $updateStmt = $conn->prepare("
                                UPDATE monhoc 
                                SET SoLuongDaDangKy = SoLuongDaDangKy + 1 
                                WHERE MaMH = ? 
                                AND SoLuongDaDangKy < SoLuongMax
                            ");
                            $updateStmt->execute([$courseId]);

                            if ($updateStmt->rowCount() > 0) {
                                // Thêm đăng ký
                                $insertStmt = $conn->prepare("
                                    INSERT INTO dangkymonhoc (MaSV, MaMH, NgayDangKy) 
                                    VALUES (?, ?, NOW())
                                ");
                                $insertStmt->execute([$studentId, $courseId]);
                                $successCourses[] = $courseId;
                            } else {
                                $errorCourses[] = ["courseId" => $courseId, "reason" => "Không thể cập nhật số lượng"];
                            }
                        } catch (Exception $e) {
                            $errorCourses[] = ["courseId" => $courseId, "reason" => "Lỗi khi đăng ký"];
                        }
                    }

                    if (!empty($successCourses)) {
                        $conn->commit();
                    } else {
                        $conn->rollBack();
                    }
                }

                // Tạo thông báo kết quả
                $messages = [];
                if (!empty($successCourses)) {
                    $messages[] = "Đăng ký thành công: " . implode(", ", $successCourses);
                }
                if (!empty($errorCourses)) {
                    foreach ($errorCourses as $error) {
                        $messages[] = "Môn {$error['courseId']}: {$error['reason']}";
                    }
                }

                sendJsonResponse(
                    !empty($successCourses),
                    [
                        'successCourses' => $successCourses,
                        'errorCourses' => $errorCourses
                    ],
                    implode("\n", $messages)
                );

            } catch (Exception $e) {
                if (isset($conn) && $conn->inTransaction()) {
                    $conn->rollBack();
                }
                sendJsonResponse(false, null, 'Lỗi: ' . $e->getMessage());
            }
            break;

        case 'getRegisteredCourses':
            $studentId = $_GET['studentId'] ?? '';
            if (empty($studentId)) {
                throw new Exception('Missing student ID');
            }
            
            $sql = "SELECT m.*, dk.NgayDangKy 
                    FROM dangkymonhoc dk 
                    JOIN monhoc m ON dk.MaMH = m.MaMH 
                    WHERE dk.MaSV = :studentId";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute([':studentId' => $studentId]);
            sendJsonResponse(true, $stmt->fetchAll(PDO::FETCH_ASSOC));
            break;

        case 'addCourse':
            if ($method !== 'POST') {
                throw new Exception('Method not allowed');
            }

            $data = json_decode(file_get_contents('php://input'), true);
            
            // Validate data
            if (empty($data['courseCode']) || empty($data['courseName']) || 
                empty($data['credits']) || empty($data['lecturer']) || 
                empty($data['maxStudents'])) {
                throw new Exception('Vui lòng điền đầy đủ thông tin');
            }

            // Check if course exists
            $stmt = $conn->prepare("SELECT COUNT(*) FROM monhoc WHERE MaMH = ?");
            $stmt->execute([$data['courseCode']]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception('Mã môn học đã tồn tại');
            }

            // Add new course
            $sql = "INSERT INTO monhoc (MaMH, TenMH, SoTC, GiangVien, SoLuongMax) 
                    VALUES (?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $result = $stmt->execute([
                $data['courseCode'],
                $data['courseName'],
                $data['credits'],
                $data['lecturer'],
                $data['maxStudents']
            ]);

            sendJsonResponse($result, null, $result ? 'Thêm tín chỉ thành công' : 'Không thể thêm tín chỉ');
            break;

        case 'editCourse':
            if ($method !== 'POST') {
                throw new Exception('Method not allowed');
            }

            $data = json_decode(file_get_contents('php://input'), true);
            
            // Validate data
            if (empty($data['courseCode']) || empty($data['courseName']) || 
                empty($data['credits']) || empty($data['lecturer']) || 
                empty($data['maxStudents'])) {
                throw new Exception('Vui lòng điền đầy đủ thông tin');
            }

            // Update course
            $sql = "UPDATE monhoc 
                    SET TenMH = ?, SoTC = ?, GiangVien = ?, SoLuongMax = ? 
                    WHERE MaMH = ?";
            $stmt = $conn->prepare($sql);
            $result = $stmt->execute([
                $data['courseName'],
                $data['credits'],
                $data['lecturer'],
                $data['maxStudents'],
                $data['courseCode']
            ]);

            sendJsonResponse($result, null, $result ? 'Cập nhật tín chỉ thành công' : 'Không thể cập nhật tín chỉ');
            break;

        case 'deleteCourse':
            if ($method !== 'POST') {
                throw new Exception('Method not allowed');
            }

            $data = json_decode(file_get_contents('php://input'), true);
            if (!isset($data['courseCode'])) {
                throw new Exception('Missing course code');
            }

            $courseCode = $data['courseCode'];

            try {
                // Log để debug
                file_put_contents('debug.log', date('Y-m-d H:i:s') . " - Attempting to delete course: " . $courseCode . "\n", FILE_APPEND);

                // Kiểm tra xem môn học có tồn tại không
                $checkCourse = $conn->prepare("SELECT SoLuongDaDangKy FROM monhoc WHERE MaMH = ?");
                $checkCourse->execute([$courseCode]);
                $course = $checkCourse->fetch(PDO::FETCH_ASSOC);

                if (!$course) {
                    throw new Exception('Không tìm thấy môn học với mã này');
                }

                // Log số lượng đăng ký
                file_put_contents('debug.log', date('Y-m-d H:i:s') . " - Current registrations: " . $course['SoLuongDaDangKy'] . "\n", FILE_APPEND);

                // Chỉ kiểm tra trong bảng dangkymonhoc nếu SoLuongDaDangKy > 0
                if ($course['SoLuongDaDangKy'] > 0) {
                    $checkRegistration = $conn->prepare("SELECT COUNT(*) FROM dangkymonhoc WHERE MaMH = ?");
                    $checkRegistration->execute([$courseCode]);
                    $registrationCount = $checkRegistration->fetchColumn();
                    
                    // Log số lượng đăng ký thực tế
                    file_put_contents('debug.log', date('Y-m-d H:i:s') . " - Actual registrations in dangkymonhoc: " . $registrationCount . "\n", FILE_APPEND);

                    if ($registrationCount > 0) {
                        throw new Exception('Không thể xóa môn học đã có sinh viên đăng ký');
                    }
                }

                // Nếu không có sinh viên đăng ký, tiến hành xóa môn học
                $deleteStmt = $conn->prepare("DELETE FROM monhoc WHERE MaMH = ?");
                $deleteStmt->execute([$courseCode]);

                if ($deleteStmt->rowCount() > 0) {
                    file_put_contents('debug.log', date('Y-m-d H:i:s') . " - Successfully deleted course: " . $courseCode . "\n", FILE_APPEND);
                    sendJsonResponse(true, null, 'Xóa tín chỉ thành công');
                } else {
                    throw new Exception('Không thể xóa tín chỉ');
                }

            } catch (Exception $e) {
                file_put_contents('debug.log', date('Y-m-d H:i:s') . " - Error deleting course: " . $e->getMessage() . "\n", FILE_APPEND);
                throw $e;
            }
            break;

        case 'cancelRegistration':
            if ($method !== 'POST') {
                throw new Exception('Method not allowed');
            }

            $data = json_decode(file_get_contents('php://input'), true);
            $studentId = $data['studentId'] ?? '';
            $courseId = $data['courseId'] ?? '';

            if (empty($studentId) || empty($courseId)) {
                throw new Exception('Thiếu thông tin cần thiết');
            }

            $conn->beginTransaction();
            try {
                // Xóa đăng ký
                $stmt = $conn->prepare("DELETE FROM dangkymonhoc WHERE MaSV = ? AND MaMH = ?");
                $stmt->execute([$studentId, $courseId]);

                // Giảm số lượng đăng ký trong bảng monhoc
                $stmt = $conn->prepare("UPDATE monhoc SET SoLuongDaDangKy = SoLuongDaDangKy - 1 WHERE MaMH = ?");
                $stmt->execute([$courseId]);

                $conn->commit();
                sendJsonResponse(true, null, 'Hủy đăng ký thành công');
            } catch (Exception $e) {
                $conn->rollBack();
                throw new Exception('Không thể hủy đăng ký: ' . $e->getMessage());
            }
            break;

        case 'getRegisteredStudents':
            $courseId = $_GET['courseId'] ?? '';
            if (empty($courseId)) {
                throw new Exception('Mã môn học không được để trống');
            }
            
            $stmt = $conn->prepare("
                SELECT sv.MaSV, sv.HoTen, sv.Lop, dk.NgayDangKy 
                FROM sinhvien sv 
                JOIN dangkymonhoc dk ON sv.MaSV = dk.MaSV 
                WHERE dk.MaMH = ?
                ORDER BY dk.NgayDangKy DESC
            ");
            
            $stmt->execute([$courseId]);
            $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            sendJsonResponse(true, $students);
            break;

        default:
            throw new Exception('Invalid action');
    }

} catch(Exception $e) {
    file_put_contents('debug.log', date('Y-m-d H:i:s') . " - Error: " . $e->getMessage() . "\n", FILE_APPEND);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
} finally {
    if (isset($conn)) {
        $conn = null;
    }
}
?>
