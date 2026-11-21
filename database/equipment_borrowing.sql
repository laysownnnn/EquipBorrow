-- ============================================================
--  Simple Equipment Borrowing System - Database
--  File   : database/equipment_borrowing.sql
--  Engine : MySQL / MariaDB (XAMPP & InfinityFree compatible)
--
--  NOTE: There is intentionally NO "CREATE DATABASE" / "USE"
--  statement. On XAMPP, create the database first, select it,
--  then import. On InfinityFree, open phpMyAdmin for your
--  if0_xxxxx database and import this file.
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS borrow_records;
DROP TABLE IF EXISTS borrowers;
DROP TABLE IF EXISTS equipment;
DROP TABLE IF EXISTS admins;

SET FOREIGN_KEY_CHECKS = 1;

-- ------------------------------------------------------------
-- Table: admins  (people who can log in and manage the system)
-- ------------------------------------------------------------
CREATE TABLE admins (
    admin_id      INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    username      VARCHAR(50)   NOT NULL,
    password_hash VARCHAR(255)  NOT NULL,           -- bcrypt hash, never plain text
    full_name     VARCHAR(100)  NOT NULL,
    created_at    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (admin_id),
    UNIQUE KEY uq_admin_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Table: equipment  (items that can be borrowed)
-- ------------------------------------------------------------
CREATE TABLE equipment (
    equipment_id       INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    equipment_code     VARCHAR(20)   NOT NULL,                 -- e.g. EQ-0001
    name               VARCHAR(100)  NOT NULL,
    category           ENUM('Laptop','Projector','Audio','Camera','Sports','Laboratory','Others')
                                     NOT NULL DEFAULT 'Others',
    quantity_total     INT UNSIGNED  NOT NULL DEFAULT 1,       -- units owned
    quantity_available INT UNSIGNED  NOT NULL DEFAULT 1,       -- units on the shelf
    item_condition     ENUM('Good','Fair','Damaged','Lost','Retired','Disposed') NOT NULL DEFAULT 'Good',
    description        TEXT          NULL,
    created_at         TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at         TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (equipment_id),
    UNIQUE KEY uq_equipment_code (equipment_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Table: borrowers  (students / faculty / staff who borrow)
-- ------------------------------------------------------------
CREATE TABLE borrowers (
    borrower_id    INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    id_number      VARCHAR(20)   NOT NULL,                     -- school ID number
    full_name      VARCHAR(100)  NOT NULL,
    borrower_type  ENUM('Student','Faculty','Staff') NOT NULL DEFAULT 'Student',
    department     VARCHAR(100)  NOT NULL,                     -- course / office
    contact_no     VARCHAR(20)   NULL,
    email          VARCHAR(100)  NULL,
    created_at     TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (borrower_id),
    UNIQUE KEY uq_borrower_idnumber (id_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Table: borrow_records  (links a borrower to an equipment)
--   equipment  1 ---< borrow_records >--- 1  borrowers
-- ------------------------------------------------------------
CREATE TABLE borrow_records (
    borrow_id        INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    equipment_id     INT UNSIGNED  NOT NULL,
    borrower_id      INT UNSIGNED  NOT NULL,
    quantity         INT UNSIGNED  NOT NULL DEFAULT 1,
    borrow_date      DATE          NOT NULL,
    due_date         DATE          NOT NULL,
    return_date      DATE          NULL,                       -- NULL = not yet returned
    status           ENUM('Borrowed','Returned') NOT NULL DEFAULT 'Borrowed',
    return_condition ENUM('Good','Fair','Damaged') NULL,
    remarks          VARCHAR(255)  NULL,
    created_at       TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (borrow_id),
    KEY idx_borrow_status (status),
    KEY idx_borrow_due (due_date),
    CONSTRAINT fk_borrow_equipment FOREIGN KEY (equipment_id)
        REFERENCES equipment (equipment_id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_borrow_borrower FOREIGN KEY (borrower_id)
        REFERENCES borrowers (borrower_id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
--  Sample data
-- ============================================================

-- Default login ->  username: admin   password: laysongwapo123
INSERT INTO admins (username, password_hash, full_name) VALUES
('admin', '$2y$10$n.V1ty/QYEVpSde3XBSV5O6UcAGEtlc9JEF7emn/KnRHthi8pHckK', 'System Administrator');

INSERT INTO equipment (equipment_code, name, category, quantity_total, quantity_available, item_condition, description) VALUES
('EQ-0001', 'Epson EB-X51 Projector',     'Projector',  3, 2, 'Good', 'With HDMI and VGA cable'),
('EQ-0002', 'Lenovo ThinkPad E14 Laptop', 'Laptop',     5, 4, 'Good', 'Core i5, 8GB RAM, charger included'),
('EQ-0003', 'Wireless Microphone Set',    'Audio',      4, 2, 'Fair', '2 handheld mics per set'),
('EQ-0004', 'Canon EOS 2000D Camera',     'Camera',     2, 2, 'Good', 'With 18-55mm lens and bag'),
('EQ-0005', 'Volleyball (Mikasa V200W)',  'Sports',    10,10, 'Good', NULL),
('EQ-0006', 'Digital Multimeter',         'Laboratory', 6, 6, 'Good', 'For electronics laboratory');

INSERT INTO borrowers (id_number, full_name, borrower_type, department, contact_no, email) VALUES
('2023-00123', 'Juan Dela Cruz',     'Student', 'BS Information Technology', '09171234567', 'juan.delacruz@example.com'),
('2022-00456', 'Maria Santos',       'Student', 'BS Computer Science',       '09281234567', 'maria.santos@example.com'),
('FAC-0012',   'Prof. Jose Rizal',   'Faculty', 'College of Engineering',    '09391234567', 'jrizal@example.com'),
('STF-0007',   'Ana Reyes',          'Staff',   'Registrar Office',          '09451234567', NULL);

-- quantities match the "available" numbers above
INSERT INTO borrow_records (equipment_id, borrower_id, quantity, borrow_date, due_date, return_date, status, return_condition, remarks) VALUES
(1, 1, 1, '2026-11-10', '2026-11-12', '2026-11-12', 'Returned', 'Good', 'Used for IT101 presentation'),
(1, 3, 1, '2026-11-15', '2026-11-18', NULL,         'Borrowed', NULL,   'Faculty seminar'),
(2, 2, 1, '2026-11-11', '2026-11-14', NULL,         'Borrowed', NULL,   'Capstone defense'),
(3, 4, 2, '2026-11-16', '2026-11-17', NULL,         'Borrowed', NULL,   'Office program');
