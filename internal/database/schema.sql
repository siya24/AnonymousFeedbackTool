-- Users table: HR and Ethics Officer accounts
-- Defined first because audit_logs references it via FK
CREATE TABLE IF NOT EXISTS users (
    id CHAR(36) NOT NULL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    first_name VARCHAR(120) NULL,
    last_name VARCHAR(120) NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    ad_username VARCHAR(120) NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('hr', 'ethics', 'manager', 'officer') NOT NULL DEFAULT 'hr',
    employee_number VARCHAR(120) NULL,
    department_name VARCHAR(255) NULL,
    position_title VARCHAR(255) NULL,
    office_location VARCHAR(255) NULL,
    can_assign_cases TINYINT(1) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,

    INDEX idx_role (role),
    INDEX idx_first_name (first_name),
    INDEX idx_last_name (last_name),
    INDEX idx_email (email),
    INDEX idx_ad_username (ad_username),
    INDEX idx_employee_number (employee_number),
    INDEX idx_can_assign_cases (can_assign_cases)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Login attempts table: Rate limiting for HR login
CREATE TABLE IF NOT EXISTS login_attempts (
    id CHAR(36) NOT NULL PRIMARY KEY,
    ip VARCHAR(45) NOT NULL,
    success TINYINT(1) NOT NULL DEFAULT 0,
    attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_ip_time (ip, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Statuses table: Configurable workflow statuses managed by HR
CREATE TABLE IF NOT EXISTS statuses (
    id CHAR(36) NOT NULL PRIMARY KEY,
    name VARCHAR(120) NOT NULL UNIQUE,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by_user_id CHAR(36) NULL,
    updated_by_user_id CHAR(36) NULL,
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,

    CONSTRAINT fk_statuses__created_by_user_id__users FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_statuses__updated_by_user_id__users FOREIGN KEY (updated_by_user_id) REFERENCES users(id) ON DELETE SET NULL,

    INDEX idx_is_active (is_active),
    INDEX idx_created_by_user_id (created_by_user_id),
    INDEX idx_updated_by_user_id (updated_by_user_id),
    INDEX idx_sort_order (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Stages table: Configurable internal workflow stages managed by HR
CREATE TABLE IF NOT EXISTS stages (
    id CHAR(36) NOT NULL PRIMARY KEY,
    name VARCHAR(120) NOT NULL UNIQUE,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by_user_id CHAR(36) NULL,
    updated_by_user_id CHAR(36) NULL,
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,

    CONSTRAINT fk_stages__created_by_user_id__users FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_stages__updated_by_user_id__users FOREIGN KEY (updated_by_user_id) REFERENCES users(id) ON DELETE SET NULL,

    INDEX idx_is_active (is_active),
    INDEX idx_created_by_user_id (created_by_user_id),
    INDEX idx_updated_by_user_id (updated_by_user_id),
    INDEX idx_sort_order (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Provinces table: Configurable province lookup for case updates
CREATE TABLE IF NOT EXISTS provinces (
    id CHAR(36) NOT NULL PRIMARY KEY,
    name VARCHAR(120) NOT NULL UNIQUE,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,

    INDEX idx_provinces_is_active (is_active),
    INDEX idx_provinces_sort_order (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Categories table: Configurable feedback categories managed by HR
-- Defined before feedbacks so the FK constraint can be enforced at creation time
CREATE TABLE IF NOT EXISTS categories (
    id CHAR(36) NOT NULL PRIMARY KEY,
    name VARCHAR(120) NOT NULL UNIQUE,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by_user_id CHAR(36) NULL,
    updated_by_user_id CHAR(36) NULL,
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,

    CONSTRAINT fk_categories__created_by_user_id__users FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_categories__updated_by_user_id__users FOREIGN KEY (updated_by_user_id) REFERENCES users(id) ON DELETE SET NULL,

    INDEX idx_is_active (is_active),
    INDEX idx_created_by_user_id (created_by_user_id),
    INDEX idx_updated_by_user_id (updated_by_user_id),
    INDEX idx_sort_order (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Assignment roles table: Configurable case assignment targets managed by HR
CREATE TABLE IF NOT EXISTS assignment_roles (
    id CHAR(36) NOT NULL PRIMARY KEY,
    name VARCHAR(120) NOT NULL UNIQUE,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by_user_id CHAR(36) NULL,
    updated_by_user_id CHAR(36) NULL,
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,

    CONSTRAINT fk_assignment_roles__created_by_user_id__users FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_assignment_roles__updated_by_user_id__users FOREIGN KEY (updated_by_user_id) REFERENCES users(id) ON DELETE SET NULL,

    INDEX idx_assignment_roles_is_active (is_active),
    INDEX idx_assignment_roles_sort_order (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User roles mapping: links users to assignment roles
CREATE TABLE IF NOT EXISTS user_roles (
    id CHAR(36) NOT NULL PRIMARY KEY,
    user_id CHAR(36) NOT NULL,
    role_id CHAR(36) NOT NULL,
    created_at DATETIME NOT NULL,

    CONSTRAINT fk_user_roles__user_id__users FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_user_roles__role_id__assignment_roles FOREIGN KEY (role_id) REFERENCES assignment_roles(id) ON DELETE CASCADE,

    UNIQUE KEY uk_user_roles_user_role (user_id, role_id),
    INDEX idx_user_roles_user_id (user_id),
    INDEX idx_user_roles_role_id (role_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Feedbacks table: Main feedback submission storage
CREATE TABLE IF NOT EXISTS feedbacks (
    id CHAR(36) NOT NULL PRIMARY KEY,
    reference_no VARCHAR(40) NOT NULL UNIQUE,
    category_id CHAR(36) NOT NULL,
    category_other VARCHAR(255) NULL COMMENT 'Free-text detail when category is Other',
    description TEXT NOT NULL,
    status_id CHAR(36) NOT NULL,
    stage_id CHAR(36) NOT NULL,
    province_id CHAR(36) NULL,
    assigned_to_user_id CHAR(36) NULL,
    assigned_role_id CHAR(36) NULL,
    assigned_at DATETIME NULL,
    updated_by_user_id CHAR(36) NULL,
    priority ENUM('Low', 'Normal', 'High', 'Critical') NOT NULL DEFAULT 'Normal',
    anonymized_summary TEXT NULL,
    reporter_feedback TEXT NULL,
    action_taken TEXT NULL,
    outcome_comments TEXT NULL,
    internal_notes TEXT NULL,
    acknowledged_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,

    CONSTRAINT fk_feedbacks__category_id__categories FOREIGN KEY (category_id) REFERENCES categories(id),
    CONSTRAINT fk_feedbacks__status_id__statuses FOREIGN KEY (status_id) REFERENCES statuses(id),
    CONSTRAINT fk_feedbacks__stage_id__stages FOREIGN KEY (stage_id) REFERENCES stages(id),
    CONSTRAINT fk_feedbacks__province_id__provinces FOREIGN KEY (province_id) REFERENCES provinces(id),
    CONSTRAINT fk_feedbacks__assigned_to_user_id__users FOREIGN KEY (assigned_to_user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_feedbacks__assigned_role_id__assignment_roles FOREIGN KEY (assigned_role_id) REFERENCES assignment_roles(id) ON DELETE SET NULL,
    CONSTRAINT fk_feedbacks__updated_by_user_id__users FOREIGN KEY (updated_by_user_id) REFERENCES users(id) ON DELETE SET NULL,

    INDEX idx_reference_no (reference_no),
    INDEX idx_category_id (category_id),
    INDEX idx_status_id (status_id),
    INDEX idx_stage_id (stage_id),
    INDEX idx_province_id (province_id),
    INDEX idx_assigned_to_user_id (assigned_to_user_id),
    INDEX idx_assigned_role_id (assigned_role_id),
    INDEX idx_updated_by_user_id (updated_by_user_id),
    INDEX idx_priority (priority),
    INDEX idx_created_at (created_at),
    INDEX idx_updated_at (updated_at),
    INDEX idx_status_created (status_id, created_at),
    INDEX idx_category_status (category_id, status_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Report updates table: Follow-up messages and additional information
CREATE TABLE IF NOT EXISTS report_updates (
    id CHAR(36) NOT NULL PRIMARY KEY,
    feedback_id CHAR(36) NOT NULL,
    update_reference_no VARCHAR(40) NOT NULL UNIQUE,
    update_text TEXT NOT NULL,
    created_at DATETIME NOT NULL,

    CONSTRAINT fk_report_updates__feedback_id__feedbacks FOREIGN KEY (feedback_id) REFERENCES feedbacks(id) ON DELETE CASCADE,

    INDEX idx_feedback_id (feedback_id),
    INDEX idx_update_reference_no (update_reference_no),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Attachments table: File uploads for feedbacks and updates
CREATE TABLE IF NOT EXISTS attachments (
    id CHAR(36) NOT NULL PRIMARY KEY,
    feedback_id CHAR(36) NULL,
    report_update_id CHAR(36) NULL,
    original_name VARCHAR(255) NOT NULL,
    stored_name VARCHAR(255) NOT NULL,
    mime_type VARCHAR(150) NOT NULL,
    size_bytes INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL,

    CONSTRAINT fk_attachments__feedback_id__feedbacks FOREIGN KEY (feedback_id) REFERENCES feedbacks(id) ON DELETE CASCADE,
    CONSTRAINT fk_attachments__report_update_id__report_updates FOREIGN KEY (report_update_id) REFERENCES report_updates(id) ON DELETE CASCADE,

    INDEX idx_feedback_id (feedback_id),
    INDEX idx_report_update_id (report_update_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Audit logs table: Complete activity trail for compliance
-- feedback_id uses SET NULL so audit history is preserved if a feedback record is ever deleted
CREATE TABLE IF NOT EXISTS audit_logs (
    id CHAR(36) NOT NULL PRIMARY KEY,
    feedback_id CHAR(36) NULL,
    actor VARCHAR(80) NOT NULL,
    actor_user_id CHAR(36) NULL,
    action VARCHAR(200) NOT NULL,
    reference_no VARCHAR(40) NOT NULL,
    details TEXT NOT NULL,
    created_at DATETIME NOT NULL,

    CONSTRAINT fk_audit_logs__feedback_id__feedbacks FOREIGN KEY (feedback_id) REFERENCES feedbacks(id) ON DELETE SET NULL,
    CONSTRAINT fk_audit_logs__actor_user_id__users FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE SET NULL,

    INDEX idx_feedback_id (feedback_id),
    INDEX idx_actor_user_id (actor_user_id),
    INDEX idx_reference_no (reference_no),
    INDEX idx_actor (actor),
    INDEX idx_created_at (created_at),
    INDEX idx_action (action)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Notifications table: Alert tracking
CREATE TABLE IF NOT EXISTS notifications (
    id CHAR(36) NOT NULL PRIMARY KEY,
    feedback_id CHAR(36) NOT NULL,
    kind VARCHAR(20) NOT NULL,
    recipient VARCHAR(100) NOT NULL,
    sent_at DATETIME NOT NULL,

    CONSTRAINT fk_notifications__feedback_id__feedbacks FOREIGN KEY (feedback_id) REFERENCES feedbacks(id) ON DELETE CASCADE,

    INDEX idx_feedback_id (feedback_id),
    INDEX idx_sent_at (sent_at),
    INDEX idx_kind (kind),
    INDEX idx_recipient (recipient)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Feedback co-investigators table: Support for multiple investigators on a case
-- Primary investigator is stored in feedbacks.assigned_to_user_id
-- Additional investigators/co-investigators are stored here
CREATE TABLE IF NOT EXISTS feedback_co_investigators (
    id CHAR(36) NOT NULL PRIMARY KEY,
    feedback_id CHAR(36) NOT NULL,
    user_id CHAR(36) NOT NULL,
    added_at DATETIME NOT NULL,
    added_by_user_id CHAR(36) NULL,

    CONSTRAINT fk_feedback_co_investigators__feedback_id__feedbacks FOREIGN KEY (feedback_id) REFERENCES feedbacks(id) ON DELETE CASCADE,
    CONSTRAINT fk_feedback_co_investigators__user_id__users FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_feedback_co_investigators__added_by_user_id__users FOREIGN KEY (added_by_user_id) REFERENCES users(id) ON DELETE SET NULL,

    UNIQUE KEY uk_feedback_user (feedback_id, user_id),
    INDEX idx_feedback_id (feedback_id),
    INDEX idx_user_id (user_id),
    INDEX idx_added_at (added_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

