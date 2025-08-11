CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    balance DECIMAL(10,2) DEFAULT 0.00,
    email VARCHAR(100) DEFAULT NULL,
    phone VARCHAR(30) DEFAULT NULL
);

CREATE TABLE orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    service VARCHAR(255) NOT NULL,
    quantity INT NOT NULL,
    link VARCHAR(255) NOT NULL,
    status VARCHAR(50) DEFAULT 'pending',
    FOREIGN KEY (user_id) REFERENCES users(id)
);
CREATE TABLE IF NOT EXISTS services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    api_service_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    price_usd DECIMAL(10,2) DEFAULT 0.00,
    price_tzs DECIMAL(15,2) DEFAULT 0.00,
    visible TINYINT(1) DEFAULT 1,
    UNIQUE KEY(api_service_id)
);

CREATE TABLE deposits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    setting_key VARCHAR(100) NOT NULL,
    setting_value TEXT,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS translations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lang VARCHAR(10) NOT NULL,
    translation_key VARCHAR(100) NOT NULL,
    translation_value TEXT NOT NULL,
    UNIQUE KEY(lang, translation_key)
);

CREATE TABLE IF NOT EXISTS admin_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(255) NOT NULL,
    details TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS support_tickets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    message TEXT,
    image_url VARCHAR(512),
    image_delete_url VARCHAR(512),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    is_admin_reply TINYINT(1) DEFAULT 0,
    ticket_type ENUM('support','transaction') DEFAULT 'support',
    parent_id INT DEFAULT NULL,
    seen_by_user TINYINT(1) DEFAULT 0,
    seen_by_admin TINYINT(1) DEFAULT 0,
    FOREIGN KEY (user_id) REFERENCES users(id)
);
