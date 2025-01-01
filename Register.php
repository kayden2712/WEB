<?php
require_once "config.php";

header('Content-Type: application/json');

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];

    // Check if username already exists
    $sql_check = "SELECT id FROM users WHERE username = ?";
    $stmt_check = $conn->prepare($sql_check);
    $stmt_check->bind_param("s", $username);
    $stmt_check->execute();
    $stmt_check->store_result();
    if ($stmt_check->num_rows > 0) {
        echo json_encode(["success" => false, "message" => "Tên người dùng đã tồn tại"]);
    } else {
        // Hash the password before saving
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        $sql = "INSERT INTO users (username, password) VALUES (?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $username, $hashed_password);

        if ($stmt->execute()) {
            echo json_encode(["success" => true, "message" => "Đăng ký thành công"]);
        } else {
            echo json_encode(["success" => false, "message" => "Đăng ký thất bại"]);
        }
    }
    $stmt_check->close();
    $stmt->close();
}
$conn->close();
?>
