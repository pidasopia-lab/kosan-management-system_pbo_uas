CREATE DATABASE IF NOT EXISTS kosan_pida;
USE kosan_pida;

CREATE TABLE settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    base_price INT NOT NULL DEFAULT 500000,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE rooms (
    id INT PRIMARY KEY AUTO_INCREMENT,
    room_number INT NOT NULL UNIQUE,
    is_occupied BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ALTER TABLE rooms ADD COLUMN image_path VARCHAR(255) DEFAULT NULL;
);

CREATE TABLE facilities (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    price INT NOT NULL,
    icon VARCHAR(50) DEFAULT 'plus-circle',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE tenants (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    room_id INT NOT NULL,
    start_month INT NOT NULL,
    paid_months JSON DEFAULT '[]',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE
);

CREATE TABLE tenant_facilities (
    tenant_id INT,
    facility_id INT,
    PRIMARY KEY (tenant_id, facility_id),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (facility_id) REFERENCES facilities(id) ON DELETE CASCADE
);

CREATE TABLE payments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    tenant_id INT NOT NULL,
    amount INT NOT NULL,
    months JSON NOT NULL,
    payment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    method ENUM('cash', 'qris') DEFAULT 'cash',
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
);

CREATE TABLE registrations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    requested_room INT NOT NULL,
    facilities JSON DEFAULT '[]',
    total_price INT NOT NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO settings (base_price) VALUES (500000);

-- 12 kamar
INSERT INTO rooms (room_number) VALUES 
(1),(2),(3),(4),(5),(6),(7),(8),(9),(10),(11),(12);

INSERT INTO facilities (name, price, icon) VALUES
('AC', 150000, 'snowflake'),
('Kamar Mandi Dalam', 100000, 'shower'),
('Parkir Motor', 50000, 'motorcycle'),
('Laundry', 75000, 'tshirt');