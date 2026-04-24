-- Create users table for HR/Ethics roles
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('hr', 'ethics') NOT NULL DEFAULT 'hr',
    is_active BOOLEAN DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_role (role),
    INDEX idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default HR user (password: admin@123456)
INSERT IGNORE INTO users (name, email, password_hash, role) VALUES
('System HR', 'hr@organization.com', '$2y$12$Wd47evruG1zT4kQ7HjNH8ey9j24mdzluryRZwqWcennxueynQBFVW', 'hr'),
('Ethics Officer', 'ethics@organization.com', '$2y$12$Wd47evruG1zT4kQ7HjNH8ey9j24mdzluryRZwqWcennxueynQBFVW', 'ethics');

-- One-time correction for previously seeded invalid hash; does not overwrite custom passwords.
UPDATE users
SET password_hash = '$2y$12$Wd47evruG1zT4kQ7HjNH8ey9j24mdzluryRZwqWcennxueynQBFVW'
WHERE email IN ('hr@organization.com', 'ethics@organization.com')
    AND password_hash = '$2y$10$rOLJlN/Eg0Y/PjWLBrC.oOvfF.Ov3Nm6u9KxfHvCbGF/LF4tGvkm2';

-- Seed default categories (INSERT IGNORE skips if already exists)
INSERT IGNORE INTO categories (name, is_active, sort_order, created_at, updated_at) VALUES
('Discrimination',                1, 1, NOW(), NOW()),
('Harassment or Bullying',        1, 2, NOW(), NOW()),
('Unfair Workload Distribution',  1, 3, NOW(), NOW()),
('Managerial Misconduct',         1, 4, NOW(), NOW()),
('Psychological Safety Concerns', 1, 5, NOW(), NOW()),
('Other',                         1, 6, NOW(), NOW());

-- Seed default statuses (INSERT IGNORE skips if already exists)
INSERT IGNORE INTO statuses (name, is_active, sort_order, created_at, updated_at) VALUES
('Investigation pending',      1, 1, NOW(), NOW()),
('Investigation in progress',  1, 2, NOW(), NOW()),
('Investigation completed',    1, 3, NOW(), NOW());
