# Anonymous Feedback Tool - Internal Deployment Architecture

## Overview

The internal deployment provides full HR operations, including authentication, case management, dashboard analytics, configuration APIs, and controlled public report access for intranet or VPN users.

## Runtime Surface

### Front controller

The entry point is internal/index.php. It:
- serves static files when running under the PHP built-in server
- blocks direct access to /uploads
- enforces APP_MODE route restrictions for /hr and /api/hr when not in full mode
- protects /anonymized/reports and /api/reports to domain, intranet, or VPN clients (with optional localhost bypass)
- registers both public and HR route groups

### Public and mixed routes

- GET /
- GET /api/reports
- GET /api/attachments/{id}
- GET /api/categories
- GET /api/statuses
- GET /api/stages
- GET /anonymized/reports

### HR web routes

- GET /hr
- GET /hr/cases/{reference}
- GET /hr/dashboard
- GET /hr/categories
- GET /hr/statuses
- GET /hr/stages

### HR API routes

- POST /api/hr/login
- POST /api/hr/logout
- GET /api/hr/me
- GET /api/hr/cases
- GET /api/hr/cases/{reference}
- POST /api/hr/cases/{reference}
- GET /api/hr/personnel
- GET /api/hr/dashboard/trends
- GET, POST, PUT, DELETE /api/hr/categories and /api/hr/categories/{id}
- GET, POST, PUT, DELETE /api/hr/statuses and /api/hr/statuses/{id}
- GET, POST, PUT, DELETE /api/hr/stages and /api/hr/stages/{id}

## Application Layers

### Core

- app/Core/Router.php: method and path dispatch with parameter extraction
- app/Core/Request.php: input parsing for JSON, form, query, path, method
- app/Core/Response.php: standardized JSON responses
- app/Core/Container.php: lightweight service container
- app/Core/Database.php and app/Core/Migration.php: PDO initialization and schema migration on bootstrap
- app/Core/JwtService.php: HS256 token encode and decode with expiration verification
- app/Core/Authorization.php: bearer authentication and role-based access checks
- app/Core/SmtpMailer.php: SMTP transport for notification emails

### Controllers

- app/Controllers/Api/FeedbackApiController.php: public reports and attachment download
- app/Controllers/Api/HrApiController.php: login, profile, case listing and updates, personnel list, dashboard trends
- app/Controllers/Api/HrCategoryApiController.php: category configuration CRUD
- app/Controllers/Api/HrStatusApiController.php: status configuration CRUD
- app/Controllers/Api/HrStageApiController.php: stage configuration CRUD
- app/Controllers/Web/PageController.php: HR pages and reporting pages

### Repositories and services

- app/Repositories/FeedbackRepository.php: persistence for feedbacks, updates, attachments, notifications, audit logs
- app/Repositories/CategoryRepository.php, StatusRepository.php, StageRepository.php: lookup and configuration data operations
- app/Services/FeedbackService.php: case lifecycle logic and attachment workflows
- app/Services/NotificationService.php: immediate and scheduled notifications with recipient role resolution and dedup logging
- app/Services/LdapAuthService.php: LDAP bind and profile retrieval for local, ldap, and hybrid auth modes

## Authentication and Authorization

### Auth modes

Configured via HR_AUTH_MODE with supported values:
- local
- ldap
- hybrid

Current login behavior in hybrid mode:
- local authentication is attempted first
- LDAP authentication is attempted second when local fails

### JWT and role checks

- JWTs are HS256 signed and include user_id, email, name, role, iat, exp
- Authorization service validates bearer tokens and enforces role checks
- Console roles: hr, ethics, manager, officer
- Case write roles: hr, ethics, officer
- Configuration roles: hr, officer

## Data Model Summary

Primary tables:
- users
- login_attempts
- categories
- statuses
- stages
- feedbacks
- report_updates
- attachments
- notifications
- audit_logs

Current schema conventions:
- primary keys are CHAR(36)
- feedback cases are stored in feedbacks
- assignment metadata exists on feedbacks (assigned_to_user_id, assigned_at)
- role values include hr, ethics, manager, officer

## Upload and File Access

- Files are stored under a private attachments storage path (default resolves to anonymous_feedback_private_uploads at repository root).
- Direct /uploads web access is blocked by the front controller.
- Attachment download is mediated through API endpoint logic with access checks.

## Notification Behavior

- immediate notifications can be sent on new submissions, follow-ups, and assignment changes when enabled
- scheduled notifications are processed by scripts/process_notifications.php
- recipient resolution is role-first from active users with environment fallback
- DEV_NOTIFICATION_EMAIL can override outgoing recipients for development
- placeholder recipient domains are blocked before send

## Security Notes

- role-based access is enforced at controller boundaries
- login rate limiting uses login_attempts
- public reports endpoints in internal deployment are access-gated to domain, intranet, or VPN clients
- direct upload-path traversal is prevented by route blocking and basename-based stored filename handling
