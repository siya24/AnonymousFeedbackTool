-- Reports table: Main feedback submission storage
CREATE TABLE IF NOT EXISTS reports (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reference_no VARCHAR(40) NOT NULL UNIQUE,
    category VARCHAR(120) NOT NULL,
    description TEXT NOT NULL,
    status ENUM('Investigation pending', 'Investigation in progress', 'Investigation completed') NOT NULL DEFAULT 'Investigation pending',
    priority ENUM('Low', 'Normal', 'High', 'Critical') NOT NULL DEFAULT 'Normal',
    stage VARCHAR(120) NOT NULL DEFAULT 'Logged',
    anonymized_summary TEXT NULL,
    action_taken TEXT NULL,
    outcome_comments TEXT NULL,
    internal_notes TEXT NULL,
    acknowledged_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    
    INDEX idx_reference_no (reference_no),
    INDEX idx_status (status),
    INDEX idx_category (category),
    INDEX idx_priority (priority),
    INDEX idx_created_at (created_at),
    INDEX idx_updated_at (updated_at),
    INDEX idx_status_created (status, created_at),
    INDEX idx_category_status (category, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Report updates table: Follow-up messages and additional information
CREATE TABLE IF NOT EXISTS report_updates (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    report_id BIGINT UNSIGNED NOT NULL,
    update_reference_no VARCHAR(40) NOT NULL UNIQUE,
    update_text TEXT NOT NULL,
    created_at DATETIME NOT NULL,
    
    CONSTRAINT fk_report_updates_report FOREIGN KEY (report_id) REFERENCES reports(id) ON DELETE CASCADE,
    INDEX idx_report_id (report_id),
    INDEX idx_update_reference_no (update_reference_no),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Attachments table: File uploads for reports and updates
CREATE TABLE IF NOT EXISTS attachments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    report_id BIGINT UNSIGNED NULL,
    report_update_id BIGINT UNSIGNED NULL,
    original_name VARCHAR(255) NOT NULL,
    stored_name VARCHAR(255) NOT NULL,
    mime_type VARCHAR(150) NOT NULL,
    size_bytes INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL,
    
    CONSTRAINT fk_attachments_report FOREIGN KEY (report_id) REFERENCES reports(id) ON DELETE CASCADE,
    CONSTRAINT fk_attachments_update FOREIGN KEY (report_update_id) REFERENCES report_updates(id) ON DELETE CASCADE,
    INDEX idx_report_id (report_id),
    INDEX idx_report_update_id (report_update_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Audit logs table: Complete activity trail for compliance
CREATE TABLE IF NOT EXISTS audit_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    actor VARCHAR(80) NOT NULL,
    action VARCHAR(200) NOT NULL,
    reference_no VARCHAR(40) NOT NULL,
    details TEXT NOT NULL,
    created_at DATETIME NOT NULL,
    
    INDEX idx_reference_no (reference_no),
    INDEX idx_actor (actor),
    INDEX idx_created_at (created_at),
    INDEX idx_action (action)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Notifications table: Alert tracking
CREATE TABLE IF NOT EXISTS notifications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    report_id BIGINT UNSIGNED NOT NULL,
    kind VARCHAR(20) NOT NULL,
    recipient VARCHAR(100) NOT NULL,
    sent_at DATETIME NOT NULL,
    
    CONSTRAINT fk_notifications_report FOREIGN KEY (report_id) REFERENCES reports(id) ON DELETE CASCADE,
    INDEX idx_report_id (report_id),
    INDEX idx_sent_at (sent_at),
    INDEX idx_kind (kind),
    INDEX idx_recipient (recipient)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
