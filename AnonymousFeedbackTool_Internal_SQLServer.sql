-- SQL Server Schema and Data for Anonymous Feedback Tool
-- Converted from MySQL
-- Single database shared by internal and external applications

USE anonymous_feedback_tool;
GO

-- Drop tables in reverse order of dependencies
DROP TABLE IF EXISTS dbo.login_attempts;
DROP TABLE IF EXISTS dbo.notifications;
DROP TABLE IF EXISTS dbo.feedback_co_investigators;
DROP TABLE IF EXISTS dbo.user_roles;
DROP TABLE IF EXISTS dbo.report_updates;
DROP TABLE IF EXISTS dbo.attachments;
DROP TABLE IF EXISTS dbo.audit_logs;
DROP TABLE IF EXISTS dbo.feedbacks;
DROP TABLE IF EXISTS dbo.assignment_roles;
DROP TABLE IF EXISTS dbo.provinces;
DROP TABLE IF EXISTS dbo.stages;
DROP TABLE IF EXISTS dbo.statuses;
DROP TABLE IF EXISTS dbo.categories;
DROP TABLE IF EXISTS dbo.users;
GO

-- Create users table
CREATE TABLE dbo.users (
    id CHAR(36) NOT NULL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    first_name VARCHAR(120) NULL,
    last_name VARCHAR(120) NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    ad_username VARCHAR(120) NULL,
    password_hash VARCHAR(255) NOT NULL,
    role VARCHAR(20) NOT NULL DEFAULT 'hr' CHECK (role IN ('hr', 'ethics', 'manager', 'officer')),
    employee_number VARCHAR(120) NULL,
    department_name VARCHAR(255) NULL,
    position_title VARCHAR(255) NULL,
    office_location VARCHAR(255) NULL,
    can_assign_cases BIT NOT NULL DEFAULT 0,
    is_active BIT NOT NULL DEFAULT 1,
    created_at DATETIME2 NOT NULL,
    updated_at DATETIME2 NOT NULL,
    INDEX idx_role (role),
    INDEX idx_email (email),
    INDEX idx_ad_username (ad_username),
    INDEX idx_can_assign_cases (can_assign_cases),
    INDEX idx_employee_number (employee_number),
    INDEX idx_first_name (first_name),
    INDEX idx_last_name (last_name)
);

-- Create statuses table
CREATE TABLE dbo.statuses (
    id CHAR(36) NOT NULL PRIMARY KEY,
    name VARCHAR(120) NOT NULL UNIQUE,
    is_active BIT NOT NULL DEFAULT 1,
    created_by_user_id CHAR(36) NULL,
    updated_by_user_id CHAR(36) NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME2 NOT NULL,
    updated_at DATETIME2 NOT NULL,
    CONSTRAINT fk_statuses__created_by_user_id__users FOREIGN KEY (created_by_user_id) REFERENCES dbo.users(id) ON DELETE NO ACTION,
    CONSTRAINT fk_statuses__updated_by_user_id__users FOREIGN KEY (updated_by_user_id) REFERENCES dbo.users(id) ON DELETE NO ACTION,
    INDEX idx_is_active (is_active),
    INDEX idx_created_by_user_id (created_by_user_id),
    INDEX idx_updated_by_user_id (updated_by_user_id),
    INDEX idx_sort_order (sort_order)
);

-- Create stages table
CREATE TABLE dbo.stages (
    id CHAR(36) NOT NULL PRIMARY KEY,
    name VARCHAR(120) NOT NULL UNIQUE,
    is_active BIT NOT NULL DEFAULT 1,
    created_by_user_id CHAR(36) NULL,
    updated_by_user_id CHAR(36) NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME2 NOT NULL,
    updated_at DATETIME2 NOT NULL,
    CONSTRAINT fk_stages__created_by_user_id__users FOREIGN KEY (created_by_user_id) REFERENCES dbo.users(id) ON DELETE NO ACTION,
    CONSTRAINT fk_stages__updated_by_user_id__users FOREIGN KEY (updated_by_user_id) REFERENCES dbo.users(id) ON DELETE NO ACTION,
    INDEX idx_is_active (is_active),
    INDEX idx_created_by_user_id (created_by_user_id),
    INDEX idx_updated_by_user_id (updated_by_user_id),
    INDEX idx_sort_order (sort_order)
);

-- Create categories table
CREATE TABLE dbo.categories (
    id CHAR(36) NOT NULL PRIMARY KEY,
    name VARCHAR(120) NOT NULL UNIQUE,
    is_active BIT NOT NULL DEFAULT 1,
    created_by_user_id CHAR(36) NULL,
    updated_by_user_id CHAR(36) NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME2 NOT NULL,
    updated_at DATETIME2 NOT NULL,
    CONSTRAINT fk_categories__created_by_user_id__users FOREIGN KEY (created_by_user_id) REFERENCES dbo.users(id) ON DELETE NO ACTION,
    CONSTRAINT fk_categories__updated_by_user_id__users FOREIGN KEY (updated_by_user_id) REFERENCES dbo.users(id) ON DELETE NO ACTION,
    INDEX idx_is_active (is_active),
    INDEX idx_created_by_user_id (created_by_user_id),
    INDEX idx_updated_by_user_id (updated_by_user_id),
    INDEX idx_sort_order (sort_order)
);

-- Create provinces table
CREATE TABLE dbo.provinces (
    id CHAR(36) NOT NULL PRIMARY KEY,
    name VARCHAR(120) NOT NULL UNIQUE,
    is_active BIT NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME2 NOT NULL,
    updated_at DATETIME2 NOT NULL,
    INDEX idx_provinces_is_active (is_active),
    INDEX idx_provinces_sort_order (sort_order)
);

-- Create assignment_roles table
CREATE TABLE dbo.assignment_roles (
    id CHAR(36) NOT NULL PRIMARY KEY,
    name VARCHAR(120) NOT NULL UNIQUE,
    is_active BIT NOT NULL DEFAULT 1,
    created_by_user_id CHAR(36) NULL,
    updated_by_user_id CHAR(36) NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME2 NOT NULL,
    updated_at DATETIME2 NOT NULL,
    CONSTRAINT fk_assignment_roles__created_by_user_id__users FOREIGN KEY (created_by_user_id) REFERENCES dbo.users(id) ON DELETE NO ACTION,
    CONSTRAINT fk_assignment_roles__updated_by_user_id__users FOREIGN KEY (updated_by_user_id) REFERENCES dbo.users(id) ON DELETE NO ACTION,
    INDEX idx_assignment_roles_is_active (is_active),
    INDEX idx_assignment_roles_sort_order (sort_order)
);

-- Create user_roles mapping table
CREATE TABLE dbo.user_roles (
    id CHAR(36) NOT NULL PRIMARY KEY,
    user_id CHAR(36) NOT NULL,
    role_id CHAR(36) NOT NULL,
    created_at DATETIME2 NOT NULL,
    CONSTRAINT fk_user_roles__user_id__users FOREIGN KEY (user_id) REFERENCES dbo.users(id) ON DELETE CASCADE,
    CONSTRAINT fk_user_roles__role_id__assignment_roles FOREIGN KEY (role_id) REFERENCES dbo.assignment_roles(id) ON DELETE CASCADE,
    CONSTRAINT uk_user_roles_user_role UNIQUE (user_id, role_id),
    INDEX idx_user_roles_user_id (user_id),
    INDEX idx_user_roles_role_id (role_id)
);

-- Create feedbacks table
CREATE TABLE dbo.feedbacks (
    id CHAR(36) NOT NULL PRIMARY KEY,
    reference_no VARCHAR(40) NOT NULL UNIQUE,
    category_id CHAR(36) NOT NULL,
    category_other VARCHAR(255) NULL,
    description NVARCHAR(MAX) NOT NULL,
    status_id CHAR(36) NOT NULL,
    stage_id CHAR(36) NOT NULL,
    province_id CHAR(36) NULL,
    assigned_to_user_id CHAR(36) NULL,
    assigned_role_id CHAR(36) NULL,
    assigned_at DATETIME2 NULL,
    updated_by_user_id CHAR(36) NULL,
    priority VARCHAR(20) NOT NULL DEFAULT 'Normal' CHECK (priority IN ('Low', 'Normal', 'High', 'Critical')),
    anonymized_summary NVARCHAR(MAX) NULL,
    reporter_feedback NVARCHAR(MAX) NULL,
    action_taken NVARCHAR(MAX) NULL,
    outcome_comments NVARCHAR(MAX) NULL,
    internal_notes NVARCHAR(MAX) NULL,
    acknowledged_at DATETIME2 NULL,
    created_at DATETIME2 NOT NULL,
    updated_at DATETIME2 NOT NULL,
    CONSTRAINT fk_feedbacks__category_id__categories FOREIGN KEY (category_id) REFERENCES dbo.categories(id),
    CONSTRAINT fk_feedbacks__status_id__statuses FOREIGN KEY (status_id) REFERENCES dbo.statuses(id),
    CONSTRAINT fk_feedbacks__stage_id__stages FOREIGN KEY (stage_id) REFERENCES dbo.stages(id),
    CONSTRAINT fk_feedbacks__province_id__provinces FOREIGN KEY (province_id) REFERENCES dbo.provinces(id),
    CONSTRAINT fk_feedbacks__assigned_to_user_id__users FOREIGN KEY (assigned_to_user_id) REFERENCES dbo.users(id) ON DELETE SET NULL,
    CONSTRAINT fk_feedbacks__assigned_role_id__assignment_roles FOREIGN KEY (assigned_role_id) REFERENCES dbo.assignment_roles(id) ON DELETE SET NULL,
    CONSTRAINT fk_feedbacks__updated_by_user_id__users FOREIGN KEY (updated_by_user_id) REFERENCES dbo.users(id) ON DELETE NO ACTION,
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
);

IF COL_LENGTH('dbo.feedbacks', 'reporter_feedback') IS NULL
BEGIN
    ALTER TABLE dbo.feedbacks ADD reporter_feedback NVARCHAR(MAX) NULL;
END;

-- Create report_updates table
CREATE TABLE dbo.report_updates (
    id CHAR(36) NOT NULL PRIMARY KEY,
    feedback_id CHAR(36) NOT NULL,
    update_reference_no VARCHAR(40) NOT NULL UNIQUE,
    update_text NVARCHAR(MAX) NOT NULL,
    created_at DATETIME2 NOT NULL,
    CONSTRAINT fk_report_updates__feedback_id__feedbacks FOREIGN KEY (feedback_id) REFERENCES dbo.feedbacks(id) ON DELETE CASCADE,
    INDEX idx_feedback_id (feedback_id),
    INDEX idx_update_reference_no (update_reference_no),
    INDEX idx_created_at (created_at)
);

-- Create attachments table
CREATE TABLE dbo.attachments (
    id CHAR(36) NOT NULL PRIMARY KEY,
    feedback_id CHAR(36) NULL,
    report_update_id CHAR(36) NULL,
    original_name VARCHAR(255) NOT NULL,
    stored_name VARCHAR(255) NOT NULL,
    mime_type VARCHAR(150) NOT NULL,
    size_bytes INT NOT NULL,
    created_at DATETIME2 NOT NULL,
    CONSTRAINT fk_attachments__feedback_id__feedbacks FOREIGN KEY (feedback_id) REFERENCES dbo.feedbacks(id) ON DELETE CASCADE,
    CONSTRAINT fk_attachments__report_update_id__report_updates FOREIGN KEY (report_update_id) REFERENCES dbo.report_updates(id) ON DELETE NO ACTION,
    INDEX idx_feedback_id (feedback_id),
    INDEX idx_report_update_id (report_update_id),
    INDEX idx_created_at (created_at)
);

-- Create audit_logs table
CREATE TABLE dbo.audit_logs (
    id CHAR(36) NOT NULL PRIMARY KEY,
    feedback_id CHAR(36) NULL,
    actor VARCHAR(80) NOT NULL,
    actor_user_id CHAR(36) NULL,
    action VARCHAR(200) NOT NULL,
    reference_no VARCHAR(40) NOT NULL,
    details NVARCHAR(MAX) NOT NULL,
    created_at DATETIME2 NOT NULL,
    CONSTRAINT fk_audit_logs__feedback_id__feedbacks FOREIGN KEY (feedback_id) REFERENCES dbo.feedbacks(id) ON DELETE SET NULL,
    CONSTRAINT fk_audit_logs__actor_user_id__users FOREIGN KEY (actor_user_id) REFERENCES dbo.users(id) ON DELETE SET NULL,
    INDEX idx_feedback_id (feedback_id),
    INDEX idx_actor_user_id (actor_user_id),
    INDEX idx_reference_no (reference_no),
    INDEX idx_actor (actor),
    INDEX idx_created_at (created_at),
    INDEX idx_action (action)
);

-- Create notifications table
CREATE TABLE dbo.notifications (
    id CHAR(36) NOT NULL PRIMARY KEY,
    feedback_id CHAR(36) NOT NULL,
    kind VARCHAR(20) NOT NULL,
    recipient VARCHAR(100) NOT NULL,
    sent_at DATETIME2 NOT NULL,
    CONSTRAINT fk_notifications__feedback_id__feedbacks FOREIGN KEY (feedback_id) REFERENCES dbo.feedbacks(id) ON DELETE CASCADE,
    INDEX idx_feedback_id (feedback_id),
    INDEX idx_sent_at (sent_at),
    INDEX idx_kind (kind),
    INDEX idx_recipient (recipient)
);

-- Create login_attempts table
CREATE TABLE dbo.login_attempts (
    id CHAR(36) NOT NULL PRIMARY KEY,
    ip VARCHAR(45) NOT NULL,
    success BIT NOT NULL DEFAULT 0,
    attempted_at DATETIME2 NOT NULL DEFAULT GETDATE(),
    INDEX idx_ip_time (ip, attempted_at)
);

-- Create feedback_co_investigators table
CREATE TABLE dbo.feedback_co_investigators (
    id CHAR(36) NOT NULL PRIMARY KEY,
    feedback_id CHAR(36) NOT NULL,
    user_id CHAR(36) NOT NULL,
    added_at DATETIME2 NOT NULL,
    added_by_user_id CHAR(36) NULL,
    CONSTRAINT uk_feedback_user UNIQUE (feedback_id, user_id),
    CONSTRAINT fk_feedback_co_investigators__feedback_id__feedbacks FOREIGN KEY (feedback_id) REFERENCES dbo.feedbacks(id) ON DELETE CASCADE,
    CONSTRAINT fk_feedback_co_investigators__user_id__users FOREIGN KEY (user_id) REFERENCES dbo.users(id) ON DELETE CASCADE,
    CONSTRAINT fk_feedback_co_investigators__added_by_user_id__users FOREIGN KEY (added_by_user_id) REFERENCES dbo.users(id) ON DELETE NO ACTION,
    INDEX idx_feedback_id (feedback_id),
    INDEX idx_user_id (user_id),
    INDEX idx_added_at (added_at)
);

-- INSERT DATA

-- Insert into users
INSERT INTO dbo.users VALUES 
('b9be831c-448c-11f1-8921-94105a53f029','Siyabulelag Gceba','Siyabulelag','Gceba','siyabulelag@legal-aid.co.za','siyabulelag','$2y$10$h9L/LKpkHdLgPOPUfNvBdu6LFvYPJcV1gYQ6UPnI1OdKYGm5MQUGy','hr','','','','',1,1,'2026-04-30 14:04:22','2026-04-30 14:04:22'),
('b23c6f96-448d-11f1-8921-94105a53f029','Ethics Officer','','','ethics-officer@legal-aid.co.za','ethicsofficer','$2y$10$h9L/LKpkHdLgPOPUfNvBdu6LFvYPJcV1gYQ6UPnI1OdKYGm5MQUGy','ethics','','','','',0,1,'2026-04-30 14:04:22','2026-04-30 14:04:22');

-- Insert into statuses
INSERT INTO dbo.statuses VALUES
('b9bf9137-448c-11f1-8921-94105a53f029','Investigation pending',1,NULL,NULL,1,'2026-04-30 14:04:22','2026-04-30 14:04:22'),
('b9bf94fe-448c-11f1-8921-94105a53f029','Investigation in progress',1,NULL,NULL,2,'2026-04-30 14:04:22','2026-04-30 14:04:22'),
('b9bf962b-448c-11f1-8921-94105a53f029','Investigation completed',1,NULL,NULL,3,'2026-04-30 14:04:22','2026-04-30 14:04:22'),
('5ba75761-52b5-4e2d-b2d2-2f741d9ca721','Test Status',1,'b9be831c-448c-11f1-8921-94105a53f029','b9be831c-448c-11f1-8921-94105a53f029',4,'2026-05-11 08:02:37','2026-05-11 08:02:37');

-- Insert into stages
INSERT INTO dbo.stages VALUES
('b9bfe3ee-448c-11f1-8921-94105a53f029','Logged',1,NULL,NULL,1,'2026-04-30 14:04:22','2026-04-30 14:04:22'),
('b9bfe6e2-448c-11f1-8921-94105a53f029','Under Review',1,NULL,NULL,2,'2026-04-30 14:04:22','2026-04-30 14:04:22'),
('b9bfe860-448c-11f1-8921-94105a53f029','Awaiting Response',1,NULL,NULL,3,'2026-04-30 14:04:22','2026-04-30 14:04:22'),
('b9bfe96a-448c-11f1-8921-94105a53f029','Escalated',1,NULL,NULL,4,'2026-04-30 14:04:22','2026-04-30 14:04:22'),
('b9bfea63-448c-11f1-8921-94105a53f029','Resolved',1,NULL,NULL,5,'2026-04-30 14:04:22','2026-04-30 14:04:22'),
('b9bfec1b-448c-11f1-8921-94105a53f029','Closed',1,NULL,NULL,6,'2026-04-30 14:04:22','2026-04-30 14:04:22'),
('7848f4ca-e83d-4d79-a980-2fa1fe8396ab','Test Stage',1,'b9be831c-448c-11f1-8921-94105a53f029','b9be831c-448c-11f1-8921-94105a53f029',7,'2026-05-11 08:14:07','2026-05-11 08:14:07');

-- Insert into categories
INSERT INTO dbo.categories VALUES
('b9bf134d-448c-11f1-8921-94105a53f029','Discrimination',1,NULL,NULL,1,'2026-04-30 14:04:22','2026-04-30 14:04:22'),
('b9bf18d7-448c-11f1-8921-94105a53f029','Harassment or Bullying',1,NULL,NULL,2,'2026-04-30 14:04:22','2026-04-30 14:04:22'),
('b9bf1b42-448c-11f1-8921-94105a53f029','Unfair Workload Distribution',1,NULL,NULL,3,'2026-04-30 14:04:22','2026-04-30 14:04:22'),
('b9bf1d0f-448c-11f1-8921-94105a53f029','Managerial Misconduct',1,NULL,NULL,4,'2026-04-30 14:04:22','2026-04-30 14:04:22'),
('b9bf1eb8-448c-11f1-8921-94105a53f029','Psychological Safety Concerns',1,NULL,NULL,5,'2026-04-30 14:04:22','2026-04-30 14:04:22'),
('b9bf2196-448c-11f1-8921-94105a53f029','Other',1,NULL,NULL,6,'2026-04-30 14:04:22','2026-04-30 14:04:22'),
('389fafd6-6201-4ac8-a8df-6bc85a0f8ad9','Testing',1,'b9be831c-448c-11f1-8921-94105a53f029','b9be831c-448c-11f1-8921-94105a53f029',0,'2026-05-11 07:42:45','2026-05-11 07:42:45');

-- Insert into assignment_roles
INSERT INTO dbo.assignment_roles VALUES
('43dcf43c-4cf6-11f1-a3b5-94105a53f029','HR Investigator',1,NULL,NULL,1,'2026-05-11 07:00:00','2026-05-11 07:00:00'),
('43dd12ca-4cf6-11f1-a3b5-94105a53f029','Case Manager',1,NULL,NULL,2,'2026-05-11 07:00:00','2026-05-11 07:00:00'),
('43dd14b9-4cf6-11f1-a3b5-94105a53f029','Compliance Lead',1,NULL,NULL,3,'2026-05-11 07:00:00','2026-05-11 07:00:00'),
('6f300020-4aa0-495a-95e9-32c493f3460d','HR Officer',1,'b9be831c-448c-11f1-8921-94105a53f029','b9be831c-448c-11f1-8921-94105a53f029',4,'2026-05-11 08:19:21','2026-05-11 08:19:21');

-- Insert into provinces
INSERT INTO dbo.provinces VALUES
(CONVERT(CHAR(36), NEWID()), 'Eastern Cape', 1, 1, GETDATE(), GETDATE()),
(CONVERT(CHAR(36), NEWID()), 'Free State', 1, 2, GETDATE(), GETDATE()),
(CONVERT(CHAR(36), NEWID()), 'Gauteng', 1, 3, GETDATE(), GETDATE()),
(CONVERT(CHAR(36), NEWID()), 'KwaZulu-Natal', 1, 4, GETDATE(), GETDATE()),
(CONVERT(CHAR(36), NEWID()), 'Limpopo', 1, 5, GETDATE(), GETDATE()),
(CONVERT(CHAR(36), NEWID()), 'Mpumalanga', 1, 6, GETDATE(), GETDATE()),
(CONVERT(CHAR(36), NEWID()), 'Northern Cape', 1, 7, GETDATE(), GETDATE()),
(CONVERT(CHAR(36), NEWID()), 'North West', 1, 8, GETDATE(), GETDATE()),
(CONVERT(CHAR(36), NEWID()), 'Western Cape', 1, 9, GETDATE(), GETDATE());

GO

-- ============================================================
-- VIEWS FOR K2 SMARTOBJECTS
-- ============================================================

-- ------------------------------------------------------------
-- 1. vw_hr_case_list  (summary list — already existed)
-- ------------------------------------------------------------
CREATE OR ALTER VIEW dbo.vw_hr_case_list AS
SELECT
    r.id,
    r.created_at,
    r.reference_no,
    COALESCE(r.category_other, c.name) AS category,
    s.name  AS status,
    st.name AS stage,
    r.priority,
    CASE
        WHEN ar.name IS NOT NULL AND ar.name <> '' THEN CONCAT('Role: ', ar.name)
        WHEN assignee.name IS NOT NULL AND assignee.name <> '' THEN
            CASE
                WHEN assignee.email IS NOT NULL AND assignee.email <> ''
                    THEN CONCAT(assignee.name, ' (', assignee.email, ')')
                ELSE assignee.name
            END
        ELSE 'Unassigned'
    END AS assigned_to_display
FROM dbo.feedbacks r
LEFT JOIN dbo.statuses s   ON s.id  = r.status_id
LEFT JOIN dbo.stages  st   ON st.id = r.stage_id
LEFT JOIN dbo.categories c ON c.id  = r.category_id
LEFT JOIN dbo.assignment_roles ar ON ar.id = r.assigned_role_id
LEFT JOIN dbo.users assignee       ON assignee.id = r.assigned_to_user_id;
GO

-- ------------------------------------------------------------
-- 2. vw_feedback_detail  (full case record for Get/Update)
-- ------------------------------------------------------------
CREATE OR ALTER VIEW dbo.vw_feedback_detail AS
SELECT
    f.id,
    f.reference_no,
    f.description,
    f.priority,
    f.anonymized_summary,
    f.reporter_feedback,
    f.action_taken,
    f.outcome_comments,
    f.internal_notes,
    f.acknowledged_at,
    f.assigned_at,
    f.created_at,
    f.updated_at,
    -- category
    f.category_id,
    COALESCE(f.category_other, c.name) AS category,
    f.category_other,
    -- status
    f.status_id,
    s.name  AS status,
    -- stage
    f.stage_id,
    st.name AS stage,
    -- assigned role
    f.assigned_role_id,
    ar.name AS assigned_role,
    -- assigned user
    f.assigned_to_user_id,
    assignee.name       AS assigned_to_name,
    assignee.email      AS assigned_to_email,
    -- last updated by
    f.updated_by_user_id,
    updater.name        AS updated_by_name
FROM dbo.feedbacks f
LEFT JOIN dbo.categories       c        ON c.id        = f.category_id
LEFT JOIN dbo.statuses         s        ON s.id        = f.status_id
LEFT JOIN dbo.stages           st       ON st.id       = f.stage_id
LEFT JOIN dbo.assignment_roles ar       ON ar.id       = f.assigned_role_id
LEFT JOIN dbo.users            assignee ON assignee.id = f.assigned_to_user_id
LEFT JOIN dbo.users            updater  ON updater.id  = f.updated_by_user_id;
GO

-- ------------------------------------------------------------
-- 3. vw_categories  (all categories — admin management)
-- ------------------------------------------------------------
CREATE OR ALTER VIEW dbo.vw_categories AS
SELECT
    c.id,
    c.name,
    c.is_active,
    c.sort_order,
    c.created_at,
    c.updated_at,
    creator.name AS created_by,
    updater.name AS updated_by
FROM dbo.categories c
LEFT JOIN dbo.users creator ON creator.id = c.created_by_user_id
LEFT JOIN dbo.users updater ON updater.id = c.updated_by_user_id;
GO

-- ------------------------------------------------------------
-- 4. vw_active_categories  (lookup list for forms/dropdowns)
-- ------------------------------------------------------------
CREATE OR ALTER VIEW dbo.vw_active_categories AS
SELECT id, name, sort_order
FROM dbo.categories
WHERE is_active = 1;
GO

-- ------------------------------------------------------------
-- 5. vw_statuses  (all statuses — admin management)
-- ------------------------------------------------------------
CREATE OR ALTER VIEW dbo.vw_statuses AS
SELECT
    s.id,
    s.name,
    s.is_active,
    s.sort_order,
    s.created_at,
    s.updated_at,
    creator.name AS created_by,
    updater.name AS updated_by
FROM dbo.statuses s
LEFT JOIN dbo.users creator ON creator.id = s.created_by_user_id
LEFT JOIN dbo.users updater ON updater.id = s.updated_by_user_id;
GO

-- ------------------------------------------------------------
-- 6. vw_active_statuses  (lookup list for forms/dropdowns)
-- ------------------------------------------------------------
CREATE OR ALTER VIEW dbo.vw_active_statuses AS
SELECT id, name, sort_order
FROM dbo.statuses
WHERE is_active = 1;
GO

-- ------------------------------------------------------------
-- 7. vw_stages  (all stages — admin management)
-- ------------------------------------------------------------
CREATE OR ALTER VIEW dbo.vw_stages AS
SELECT
    s.id,
    s.name,
    s.is_active,
    s.sort_order,
    s.created_at,
    s.updated_at,
    creator.name AS created_by,
    updater.name AS updated_by
FROM dbo.stages s
LEFT JOIN dbo.users creator ON creator.id = s.created_by_user_id
LEFT JOIN dbo.users updater ON updater.id = s.updated_by_user_id;
GO

-- ------------------------------------------------------------
-- 8. vw_active_stages  (lookup list for forms/dropdowns)
-- ------------------------------------------------------------
CREATE OR ALTER VIEW dbo.vw_active_stages AS
SELECT id, name, sort_order
FROM dbo.stages
WHERE is_active = 1;
GO

-- ------------------------------------------------------------
-- 9. vw_assignment_roles  (all assignment roles — admin)
-- ------------------------------------------------------------
CREATE OR ALTER VIEW dbo.vw_assignment_roles AS
SELECT
    r.id,
    r.name,
    r.is_active,
    r.sort_order,
    r.created_at,
    r.updated_at,
    creator.name AS created_by,
    updater.name AS updated_by
FROM dbo.assignment_roles r
LEFT JOIN dbo.users creator ON creator.id = r.created_by_user_id
LEFT JOIN dbo.users updater ON updater.id = r.updated_by_user_id;
GO

-- ------------------------------------------------------------
-- 10. vw_active_assignment_roles  (lookup list)
-- ------------------------------------------------------------
CREATE OR ALTER VIEW dbo.vw_active_assignment_roles AS
SELECT id, name, sort_order
FROM dbo.assignment_roles
WHERE is_active = 1;
GO

-- ------------------------------------------------------------
-- 11. vw_users  (HR/internal users — excludes password hash)
-- ------------------------------------------------------------
CREATE OR ALTER VIEW dbo.vw_users AS
SELECT
    id,
    name,
    first_name,
    last_name,
    email,
    ad_username,
    role,
    employee_number,
    department_name,
    position_title,
    office_location,
    can_assign_cases,
    is_active,
    created_at,
    updated_at
FROM dbo.users;
GO

-- ------------------------------------------------------------
-- 12. vw_active_users  (assignable users — lookup list)
-- ------------------------------------------------------------
CREATE OR ALTER VIEW dbo.vw_active_users AS
SELECT id, name, email, role, employee_number, can_assign_cases
FROM dbo.users
WHERE is_active = 1;
GO

-- ------------------------------------------------------------
-- 13. vw_report_updates  (updates with feedback context)
-- ------------------------------------------------------------
CREATE OR ALTER VIEW dbo.vw_report_updates AS
SELECT
    u.id,
    u.update_reference_no,
    u.feedback_id,
    f.reference_no AS feedback_reference_no,
    u.update_text,
    u.created_at
FROM dbo.report_updates u
INNER JOIN dbo.feedbacks f ON f.id = u.feedback_id;
GO

-- ------------------------------------------------------------
-- 14. vw_attachments  (attachments with parent references)
-- ------------------------------------------------------------
CREATE OR ALTER VIEW dbo.vw_attachments AS
SELECT
    a.id,
    a.original_name,
    a.stored_name,
    a.mime_type,
    a.size_bytes,
    a.created_at,
    a.feedback_id,
    f.reference_no  AS feedback_reference_no,
    a.report_update_id,
    ru.update_reference_no
FROM dbo.attachments a
LEFT JOIN dbo.feedbacks      f  ON f.id  = a.feedback_id
LEFT JOIN dbo.report_updates ru ON ru.id = a.report_update_id;
GO

-- ------------------------------------------------------------
-- 15. vw_audit_logs  (audit trail with actor details)
-- ------------------------------------------------------------
CREATE OR ALTER VIEW dbo.vw_audit_logs AS
SELECT
    al.id,
    al.created_at,
    al.reference_no,
    al.action,
    al.actor,
    al.actor_user_id,
    u.name  AS actor_name,
    u.email AS actor_email,
    al.feedback_id,
    al.details
FROM dbo.audit_logs al
LEFT JOIN dbo.users u ON u.id = al.actor_user_id;
GO

-- ------------------------------------------------------------
-- 16. vw_co_investigators  (co-investigators with context)
-- ------------------------------------------------------------
CREATE OR ALTER VIEW dbo.vw_co_investigators AS
SELECT
    ci.id,
    ci.added_at,
    ci.feedback_id,
    f.reference_no  AS feedback_reference_no,
    ci.user_id,
    u.name          AS investigator_name,
    u.email         AS investigator_email,
    u.role          AS investigator_role,
    ci.added_by_user_id,
    adder.name      AS added_by_name
FROM dbo.feedback_co_investigators ci
INNER JOIN dbo.feedbacks f     ON f.id     = ci.feedback_id
INNER JOIN dbo.users     u     ON u.id     = ci.user_id
LEFT JOIN  dbo.users     adder ON adder.id = ci.added_by_user_id;
GO

PRINT 'AnonymousFeedbackTool_Internal SQL Server schema created successfully.';
