# AnonymousFeedbackTool (MVC + MySQL)

This project is a PHP MVC application with:

- Frontend pages for employee submission and HR management
- Backend REST-style API for feedback, follow-up, public reporting, and HR case handling
- MySQL persistence using PDO

## Architecture

- app/Core: bootstrap, container, router, request/response, DB, migration
- app/Controllers/Web: page controllers
- app/Controllers/Api: API controllers
- app/Models: data access model
- app/Views: frontend templates
- public/assets: CSS and JavaScript frontend assets
- database/schema.sql: MySQL schema

## MySQL Credentials

Default DB config (as requested):

- username: root
- password: N3wp@ss4u1
- database: anonymous_feedback_tool

Config file:

- config/database.php

You can override with environment variables:

- DB_HOST
- DB_PORT
- DB_DATABASE
- DB_USERNAME
- DB_PASSWORD

## Run

1. Ensure MySQL is running and root password is set to N3wp@ss4u1 (or set env vars).
2. Run the app from repository root:

   php -S localhost:8000

3. Open in browser:

   - Home: http://localhost:8000/
   - HR Console: http://localhost:8000/hr

## API Endpoints

- POST /api/feedback
- POST /api/feedback/update
- GET /api/feedback/{reference}
- GET /api/reports
- POST /api/hr/login
- POST /api/hr/logout
- GET /api/hr/cases
- GET /api/hr/cases/{reference}
- POST /api/hr/cases/{reference}

## Notes

- HR login password defaults to ChangeMe123! unless HR_CONSOLE_PASSWORD is set.
- Attachments are stored in uploads.
- The first app request auto-runs schema creation.
