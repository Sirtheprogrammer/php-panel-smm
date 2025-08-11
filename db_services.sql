-- Add this to your db.sql and run it in your MySQL database

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
