<?php
require_once "config.php";

header('Content-Type: application/json');

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];

    $sql = "SELECT id, username, password FROM users WHERE username = ?";
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        echo json_encode(["success" => false, "message" => "Lỗi chuẩn bị câu truy vấn: " . $conn->error]);
        exit;
    }
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 1) {
        $row = $result->fetch_assoc();
        if (password_verify($password, $row['password'])) {
            echo json_encode(["success" => true, "message" => "Đăng nhập thành công"]);
        } else {
            echo json_encode(["success" => false, "message" => "Mật khẩu không chính xác"]);
        }
    } else {
        echo json_encode(["success" => false, "message" => "Tài khoản không tồn tại"]);
    }
    $stmt->close();
}
$conn->close();
?>
