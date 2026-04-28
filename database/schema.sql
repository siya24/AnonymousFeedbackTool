-- Statuses table: Configurable workflow statuses managed by HR
CREATE TABLE IF NOT EXISTS statuses (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL UNIQUE,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,

    INDEX idx_is_active (is_active),
    INDEX idx_sort_order (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Categories table: Configurable feedback categories managed by HR
-- Defined before reports so the FK constraint can be enforced at creation time
CREATE TABLE IF NOT EXISTS categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL UNIQUE,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,

    INDEX idx_is_active (is_active),
    INDEX idx_sort_order (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Reports table: Main feedback submission storage
CREATE TABLE IF NOT EXISTS reports (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reference_no VARCHAR(40) NOT NULL UNIQUE,
    category_id INT UNSIGNED NOT NULL,
    category_other VARCHAR(255) NULL COMMENT 'Free-text detail when category is Other',
    description TEXT NOT NULL,
    status_id INT UNSIGNED NOT NULL,
    priority ENUM('Low', 'Normal', 'High', 'Critical') NOT NULL DEFAULT 'Normal',
    stage VARCHAR(120) NOT NULL DEFAULT 'Logged',
    anonymized_summary TEXT NULL,
    action_taken TEXT NULL,
    outcome_comments TEXT NULL,
    internal_notes TEXT NULL,
    acknowledged_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,

    CONSTRAINT fk_reports_category FOREIGN KEY (category_id) REFERENCES categories(id),
    CONSTRAINT fk_reports_status FOREIGN KEY (status_id) REFERENCES statuses(id),

    INDEX idx_reference_no (reference_no),
    INDEX idx_category_id (category_id),
    INDEX idx_status_id (status_id),
    INDEX idx_priority (priority),
    INDEX idx_created_at (created_at),
    INDEX idx_updated_at (updated_at),
    INDEX idx_status_created (status_id, created_at),
    INDEX idx_category_status (category_id, status_id)
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
-- report_id uses SET NULL so audit history is preserved if a report is ever deleted
CREATE TABLE IF NOT EXISTS audit_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    report_id BIGINT UNSIGNED NULL,
    actor VARCHAR(80) NOT NULL,
    action VARCHAR(200) NOT NULL,
    reference_no VARCHAR(40) NOT NULL,
    details TEXT NOT NULL,
    created_at DATETIME NOT NULL,

    CONSTRAINT fk_audit_logs_report FOREIGN KEY (report_id) REFERENCES reports(id) ON DELETE SET NULL,
    INDEX idx_report_id (report_id),
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

