# Anonymous Feedback Tool Monorepo - Technical Design

Version: 2.0  
Date: 2026-05-04  
Status: Current

## 1. Purpose

This document is the single technical design reference for both deployable apps in this repository:

- external app: public anonymous feedback intake
- internal app: intranet HR management and reporting

Both apps share the same architectural style, core domain model, and database schema, but expose different routes and capabilities.

## 2. Repository Layout

```text
AnonymousFeedbackTool/
  external/   # Public-facing app
  internal/   # Intranet HR app
  README.md
```

Each app contains its own front controller, config, MVC layers, static assets, and environment settings.

## 3. Runtime Model

### 3.1 external app

- Entry point: external/index.php
- Audience: public internet users
- Exposes:
  - public web form and follow-up flow
  - reference lookup flow
  - public lookup APIs (categories, statuses)
  - attachment download endpoint guarded by business checks
- Explicitly blocks:
  - /hr
  - /api/hr/*
  - /anonymized/*
  - /api/reports

### 3.2 internal app

- Entry point: internal/index.php
- Audience: HR, Ethics, Information Services on intranet/VPN/domain
- Exposes:
  - HR web console pages
  - HR protected APIs
  - anonymized reports page and reports API
- Access controls:
  - JWT-based HR API authorization
  - AD/domain/intranet/VPN gate for reports surfaces
  - local bypass option for reports in development only

## 4. Shared Architecture

Both apps use a custom MVC architecture (no external framework):

- Core:
  - Router: path matching and param extraction
  - Request/Response: HTTP abstraction and JSON responses
  - Container: service registration and resolution
  - Database: PDO connection creation
  - Migration: schema migration runner
  - JwtService + Authorization: token handling and auth checks
  - SmtpMailer: outbound notifications
- Layers:
  - Controllers: HTTP endpoint orchestration
  - Services: business logic
  - Repositories: SQL and persistence
  - Models: domain data mapping
  - Views: server-rendered page templates + layout

## 5. Data Model

Primary tables:

- feedbacks
- report_updates
- attachments
- categories
- statuses
- stages
- users
- notifications
- audit_logs
- login_attempts

Key relationships:

- feedbacks references categories/statuses/stages
- report_updates belongs to feedbacks
- attachments belongs to feedbacks and optionally to report_updates
- notifications/audit_logs reference feedbacks

Reference formats:

- case: AF-YYYYMMDD-XXXXXX
- update: UPD-YYYYMMDD-XXXXXX

## 6. Request Flow

1. Request reaches app front controller (external/index.php or internal/index.php)
2. bootstrap.php loads env, config, container services
3. route-level guards enforce mode/security boundaries
4. router dispatches to controller action
5. controller delegates to service/repository
6. JSON response or rendered HTML view returned

Note: scheduled notifications run through scripts/process_notifications.php, not during normal web requests.

## 7. Security Design

- Strict route separation between public and internal surfaces
- Private attachment storage path outside direct web serving
- Upload web-path requests blocked at front controller
- Input validation and controlled error responses in API controllers
- JWT auth for HR endpoints
- AD/domain/intranet/VPN checks for reports pages and reports API
- Optional ALLOW_LOCAL_REPORTS for localhost development testing

## 8. Configuration

Important environment variables:

- APP_MODE: public or full
- HR_AUTH_MODE: local, ldap, or hybrid
- JWT_SECRET
- DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD
- ATTACHMENTS_STORAGE_PATH
- INTRANET_ALLOWED_CIDRS
- ALLOW_LOCAL_REPORTS (development only)
- MAIL_FROM, SMTP_*, notification email targets

## 9. API Surface Summary

### 9.1 public/external

- GET /
- POST /api/feedback
- POST /api/feedback/update
- GET /api/feedback/{reference}
- GET /api/attachments/{id}
- GET /api/categories
- GET /api/statuses

### 9.2 internal/full

- HR pages: /hr, /hr/dashboard, /hr/cases/{reference}, /hr/categories, /hr/statuses, /hr/stages
- reports: /anonymized/reports, /api/reports
- auth: /api/hr/login, /api/hr/logout, /api/hr/me
- case management: /api/hr/cases, /api/hr/cases/{reference}
- dashboard: /api/hr/dashboard/trends
- taxonomy management: /api/hr/categories*, /api/hr/statuses*, /api/hr/stages*
- shared lookups: /api/categories, /api/statuses, /api/stages

## 10. Operational Notes

- external and internal can be deployed independently
- both can point to the same database
- background notification processing should run from internal deployment cron/task scheduler
- local development with PHP built-in server supports static-file passthrough in index.php
