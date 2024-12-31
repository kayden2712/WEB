-- Tạo database nếu chưa tồn tại
CREATE DATABASE IF NOT EXISTS quanlysinhvien
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

-- Sử dụng database
USE quanlysinhvien;

-- Xóa bảng nếu tồn tại
DROP TABLE IF EXISTS sinhvien;

-- Tạo bảng sinh viên
CREATE TABLE sinhvien (
    id INT AUTO_INCREMENT PRIMARY KEY,
    MaSV VARCHAR(20) UNIQUE NOT NULL,
    HoTen VARCHAR(100) NOT NULL,
    NgaySinh DATE NOT NULL,
    Lop VARCHAR(50) NOT NULL,
    Khoa VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Thêm dữ liệu mẫu
INSERT INTO sinhvien (MaSV, HoTen, NgaySinh, Lop, Khoa) 
VALUES 
    ('SV001', 'Nguyễn Văn A', '2000-01-15', '12A1', 'Công nghệ thông tin'),
    ('SV002', 'Trần Thị B', '2000-05-20', '12A2', 'Kinh tế'),
    ('SV003', 'Lê Văn C', '2000-08-10', '12A1', 'Công nghệ thông tin'),
    ('SV004', 'Phạm Thị D', '2000-12-25', '12A3', 'Ngoại ngữ'),
    ('SV005', 'Hoàng Văn E', '2000-03-30', '12A2', 'Kinh tế');

-- Kiểm tra ràng buộc và index
ALTER TABLE sinhvien
ADD CONSTRAINT check_masv CHECK (LENGTH(MaSV) >= 4),
ADD INDEX idx_lop (Lop),
ADD INDEX idx_khoa (Khoa);

-- Cấp quyền cho user root
GRANT ALL PRIVILEGES ON quanlysinhvien.* TO 'root'@'localhost';
FLUSH PRIVILEGES; 
-- Thêm bảng môn học
CREATE TABLE monhoc (
    MaMH VARCHAR(20) PRIMARY KEY,
    TenMH VARCHAR(100) NOT NULL,
    SoTC INT NOT NULL,
    GiangVien VARCHAR(100) NOT NULL,
    SoLuongMax INT NOT NULL DEFAULT 40,
    SoLuongDaDangKy INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Thêm bảng đăng ký môn học
CREATE TABLE dangkymonhoc (
    id INT AUTO_INCREMENT PRIMARY KEY,
    MaSV VARCHAR(20),
    MaMH VARCHAR(20),
    NgayDangKy DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (MaSV) REFERENCES sinhvien(MaSV),
    FOREIGN KEY (MaMH) REFERENCES monhoc(MaMH),
    UNIQUE KEY unique_dangky (MaSV, MaMH)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Thêm dữ liệu mẫu cho môn học
INSERT INTO monhoc (MaMH, TenMH, SoTC, GiangVien, SoLuongMax) VALUES 
('CNTT001', 'Lập trình Java', 3, 'Nguyễn Văn X', 40),
('CNTT002', 'Cơ sở dữ liệu', 4, 'Trần Thị Y', 35),
('CNTT003', 'Mạng máy tính', 3, 'Lê Văn Z', 30);