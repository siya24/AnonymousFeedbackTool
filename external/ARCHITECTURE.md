# Anonymous Feedback Tool - External Deployment Architecture

## Overview

The external deployment is the internet-facing employee portal. It accepts anonymous submissions and follow-ups, provides category and status lists, and never exposes HR console or HR APIs.

## Runtime Surface

### Front controller

The entry point is external/index.php. It:
- serves static files when running under the PHP built-in server
- blocks direct access to /uploads
- returns 404 for any internal-only paths (/hr, /api/hr, /anonymized, /api/reports)
- registers only public employee routes

### Registered routes

- GET /
- POST /api/feedback
- POST /api/feedback/update
- GET /api/feedback/{reference}
- GET /api/attachments/{id}
- GET /api/categories
- GET /api/statuses

## Application Layers

### Core

- app/Core/Router.php: method and path dispatch with parameter extraction
- app/Core/Request.php: input parsing for JSON, form, query, path, method
- app/Core/Response.php: standardized JSON responses
- app/Core/Container.php: lightweight service container
- app/Core/Database.php and app/Core/Migration.php: PDO initialization and schema migration on bootstrap
- app/Core/SmtpMailer.php: SMTP transport for notification emails

### Controllers

- app/Controllers/Api/FeedbackApiController.php: submission, follow-up, case lookup, attachment download
- app/Controllers/Api/CategoryApiController.php: active categories list
- app/Controllers/Api/StatusApiController.php: active statuses list
- app/Controllers/Web/PageController.php: external home page

### Repositories and services

- app/Repositories/FeedbackRepository.php: persistence for feedbacks, updates, attachments, notifications, audit logs
- app/Services/FeedbackService.php: submission and follow-up business logic, reference generation, attachment handling
- app/Services/NotificationService.php: immediate and scheduled notifications with recipient role resolution and dedup logging
- app/Services/LdapAuthService.php exists in codebase but is not exposed through external routes

## Data Model Summary

The external deployment uses the same core schema as the internal deployment.

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
- feedback cases are stored in feedbacks (not reports)
- role values include hr, ethics, manager, officer

## Upload and File Access

- Files are stored under a private attachments storage path (default resolves to anonymous_feedback_private_uploads at repository root).
- Direct /uploads web access is blocked by the front controller.
- Attachment download is mediated through API endpoint logic, not direct filesystem paths.

## Security Controls in External Mode

- no HR API surface is routable from external deployment
- JWT-protected HR operations are unavailable in this deployment
- SQL access is repository-driven via prepared statements
- direct upload-path traversal is prevented by route blocking and basename-based stored filename handling

## Notification Behavior

- immediate notifications can be sent on new submissions and follow-ups when enabled
- scheduled notifications are processed by scripts/process_notifications.php (scheduler-driven)
- recipient resolution is role-first from active users with environment fallback
- DEV_NOTIFICATION_EMAIL can override outgoing recipients for development

## Operational Notes

- This deployment should be used only for employee-facing access.
- Internal case management and configuration are intentionally excluded from this surface.
