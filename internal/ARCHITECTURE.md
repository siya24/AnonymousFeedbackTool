# Anonymous Feedback Tool - Enterprise Architecture

## Overview

This application implements a modern, enterprise-grade MVC architecture with JWT-based authentication, role-based authorization, and clean separation of concerns through Repository and Service layers.

## Architecture Layers

### 1. **Core Framework** (`app/Core/`)

#### Router (`Router.php`)
- Pattern-based URL routing with parameter extraction
- RESTful endpoint dispatch
- Supports GET, POST, PUT, DELETE methods

#### Request/Response Helpers (`Request.php`, `Response.php`)
- HTTP request parsing (method, path, query, body)
- JSON and HTML response rendering
- Automatic content-type handling

#### Container (`Container.php`)
- Lightweight dependency injection container
- Service locator pattern
- Manages singleton instances of core services

#### Database (`Database.php`)
- PDO abstraction layer
- Automatic MySQL database creation on first run
- Connection pooling support

#### Migration (`Migration.php`)
- Runs database schema files
- Automatically executes on application bootstrap
- Supports multiple migration files (schema.sql, users.sql)

#### JWT Service (`JwtService.php`) - **NEW**
- HS256 (HMAC SHA256) token generation and validation
- Payload encoding/decoding with signature verification
- Automatic expiration handling (24-hour default)
- Bearer token extraction from Authorization header

#### Authorization (`Authorization.php`) - **NEW**
- JWT token authentication
- Role-based access control (RBAC)
- User identity and permission checking
- Exception-based access denial with HTTP status codes

### 2. **Repository Layer** (`app/Repositories/`) - **NEW**

#### FeedbackRepository
Pure data access layer. No business logic. Handles:

- Report CRUD operations
- Reference number lookups
- Filtered queries (by status, category, date)
- Attachment persistence
- Audit trail logging
- Notification recording

**Key Methods:**
```php
createReport(string $reference, string $category, string $description): int
findByReference(string $reference): ?array
listCases(array $filters): array
listPublicReports(array $filters): array
updateReport(string $reference, array $data): bool
createUpdate(int $reportId, string $updateReference, string $updateText): int
getReportAttachments(int $reportId): array
logAudit(string $actor, string $action, string $reference, string $details): int
```

### 3. **Service Layer** (`app/Services/`) - **NEW**

#### FeedbackService
Business logic layer. Orchestrates data operations and enforces rules:

- Reference number generation (AF-YYYYMMDD-XXXXXX format)
- Feedback submission workflow
- Follow-up validation and creation
- Case detail retrieval with related entities
- Public anonymized report generation
- HR case updates with validation
- Attachment storage and validation
- Status transition enforcement

**Key Methods:**
```php
submitFeedback(string $category, string $description): array
submitFollowUp(string $reference, string $updateText): array
getCaseDetails(string $reference): array
listCasesForHr(array $filters): array
getPublicReports(array $filters): array
updateCaseForHr(string $reference, array $updateData, string $hrUserId): array
storeAttachments(int $reportId, ?int $updateId, array $files): array
generateReference(string $prefix): string
```

### 4. **Controllers** (`app/Controllers/`)

#### FeedbackApiController
Public API endpoints (no authentication required):
- `POST /api/feedback` - Submit anonymous feedback
- `POST /api/feedback/update` - Submit follow-up
- `GET /api/feedback/{reference}` - Retrieve case details
- `GET /api/reports` - List public anonymized reports

Uses `FeedbackService` for all operations.

#### HrApiController - **ENHANCED**
HR/Ethics API endpoints (JWT authentication required):
- `POST /api/hr/login` - Authenticate and receive JWT token
- `POST /api/hr/logout` - Logout (client-side token removal)
- `GET /api/hr/me` - Get current user info
- `GET /api/hr/cases` - List cases (requires HR or Ethics role)
- `GET /api/hr/cases/{reference}` - Get case detail (requires HR or Ethics role)
- `POST /api/hr/cases/{reference}` - Update case (requires HR role only)

#### PageController
Web template rendering:
- `GET /` - Employee feedback interface
- `GET /hr` - HR management console (template only, client-side JS handles auth)

### 5. **Models** (`app/Models/`)

#### FeedbackModel (Legacy - Maintained for compatibility)
Original monolithic data model. Still functional but superseded by Repository/Service pattern.

## Authentication & Authorization

### JWT Flow

1. **Login Request**
   ```
   POST /api/hr/login
   Body: { "email": "user@org.com", "password": "password" }
   ```

2. **Token Generation**
   - Server validates credentials against `users` table
   - Generates HS256-signed JWT with payload:
     ```json
     {
       "user_id": 1,
       "email": "user@org.com",
       "name": "John Doe",
       "role": "hr",
       "iat": 1640000000,
       "exp": 1640086400
     }
     ```

3. **Token Usage**
   - Client stores token in `localStorage`
   - Includes in subsequent requests:
     ```
     Authorization: Bearer eyJhbGc...
     ```

4. **Token Validation**
   - Server validates signature using `JWT_SECRET`
   - Checks expiration timestamp
   - Extracts user identity and role
   - Returns 401 if invalid/expired

### Role-Based Access Control (RBAC)

**Roles:**
- `hr` - Full case management (read, update, close)
- `ethics` - Read-only case access (view only)

**Permission Examples:**
```php
// Requires HR or Ethics role
$auth->requireAnyRole(['hr', 'ethics']);

// Requires HR role specifically
$auth->requireRole('hr');

// Check permission without throwing
if ($auth->hasRole('hr')) {
    // Update case
}
```

## Database Schema

### Users Table
```sql
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('hr', 'ethics') NOT NULL DEFAULT 'hr',
    is_active BOOLEAN DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

### Default Credentials (Change in production!)
```
Email: hr@organization.com
Password: admin@123456
Role: hr

Email: ethics@organization.com
Password: admin@123456
Role: ethics
```

### Existing Tables
- `reports` - Feedback cases
- `report_updates` - Follow-up messages
- `attachments` - File uploads
- `audit_logs` - Activity tracking
- `notifications` - Alert records

## Data Flow Diagrams

### Anonymous Feedback Submission
```
Client Form
    ↓
POST /api/feedback
    ↓
FeedbackApiController::submit()
    ↓
FeedbackService::submitFeedback()
    ↓
FeedbackRepository::createReport()
    ↓
MySQL: INSERT into reports
    ↓
FeedbackRepository::logAudit()
FeedbackRepository::logNotification()
    ↓
JSON Response with reference_no
```

### HR Case Management (with JWT)
```
Client Login Form
    ↓
POST /api/hr/login (email, password)
    ↓
HrApiController::login()
    ↓
Query users table
    ↓
Verify password_hash
    ↓
JwtService::encode() → JWT Token
    ↓
Client stores token in localStorage
    ↓
GET /api/hr/cases
    ↓
Authorization header with Bearer token
    ↓
JwtService::decode() validates token
    ↓
Authorization::authenticate() → User identity
    ↓
Authorization::requireAnyRole(['hr', 'ethics']) → Permission check
    ↓
HrApiController::listCases()
    ↓
FeedbackService::listCasesForHr()
    ↓
FeedbackRepository::listCases()
    ↓
MySQL query with filters
    ↓
JSON Response with cases array
```

## Configuration

### Environment Variables

```bash
# JWT Configuration
JWT_SECRET=your-super-secret-key-change-in-production

# Database
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=anonymous_feedback_tool
DB_USERNAME=root
DB_PASSWORD=N3wp@ss4u1

# Application
APP_NAME="Anonymous Feedback Tool"
APP_BASE_URL=http://localhost:8000
```

### Defaults (if env vars not set)

| Variable | Default |
|----------|---------|
| JWT_SECRET | `your-super-secret-jwt-key-change-in-production` |
| DB_HOST | `localhost` |
| DB_PORT | `3306` |
| DB_DATABASE | `anonymous_feedback_tool` |
| DB_USERNAME | `root` |
| DB_PASSWORD | `N3wp@ss4u1` |

## Security Considerations

### JWT Security
- **Secret Key**: Must be at least 32 characters for production
- **HTTPS Only**: Always use HTTPS in production (Bearer tokens vulnerable to MITM)
- **Token Expiration**: Default 24 hours; adjust as needed
- **Algorithm**: HS256 (HMAC); could upgrade to RS256 for key rotation

### Password Security
- **Hashing**: Uses `password_hash()` with bcrypt
- **Verification**: Uses `password_verify()` with timing-safe comparison
- **Defaults**: Change default credentials immediately in production

### Access Control
- Anonymous endpoints: No authentication required
- HR endpoints: Require valid JWT token + appropriate role
- Audit trail: Logs all HR actions with user identity

### Data Protection
- CORS: Can be configured for specific domains
- CSRF: Use SameSite cookie flags in production
- SQL Injection: PDO prepared statements used throughout
- XSS: Output escaping in JSON responses

## Deployment Guide

### Production Checklist

1. **Database**
   - [ ] Update `DB_PASSWORD` to strong value
   - [ ] Restrict MySQL user to localhost only
   - [ ] Enable SSL connections
   - [ ] Regular backups scheduled

2. **JWT**
   - [ ] Set `JWT_SECRET` to random 32+ character string
   - [ ] Use `openssl rand -base64 32` to generate
   - [ ] Rotate JWT secret periodically

3. **Users**
   - [ ] Change all default user passwords
   - [ ] Create strong email/password pairs
   - [ ] Enable 2FA if supported by hosting

4. **Application**
   - [ ] Set `APP_BASE_URL` to production domain
   - [ ] Enable HTTPS/SSL certificates
   - [ ] Set PHP `display_errors = Off`
   - [ ] Configure proper file permissions (755 for dirs, 644 for files)

5. **Monitoring**
   - [ ] Enable PHP error logging
   - [ ] Monitor audit logs for suspicious activity
   - [ ] Set up alerts for failed authentication attempts
   - [ ] Regular security audits

## Development Guide

### Adding New Endpoints

1. **Create Repository Method** (if data access needed)
   ```php
   // app/Repositories/FeedbackRepository.php
   public function newMethod($params) {
       // Pure data access, no business logic
   }
   ```

2. **Create Service Method** (orchestrate business logic)
   ```php
   // app/Services/FeedbackService.php
   public function newBusinessLogic($params) {
       // Call repository methods
       // Apply business rules
       // Return formatted result
   }
   ```

3. **Create Controller Method** (handle HTTP request)
   ```php
   // app/Controllers/Api/FeedbackApiController.php
   public function endpoint(array $params = []): void
   {
       try {
           // Validate input
           // Call service
           // Return JSON response
       } catch (\RuntimeException $e) {
           Response::json(['error' => $e->getMessage()], $code);
       }
   }
   ```

4. **Register Route** (in index.php)
   ```php
   $router->add('GET', '/api/resource/{id}', [ControllerClass::class, 'endpoint']);
   ```

### Testing Locally

```bash
# Start PHP server
php -S localhost:8000

# Test anonymous endpoint
curl -X POST http://localhost:8000/api/feedback \
  -H "Content-Type: application/json" \
  -d '{"category":"Discrimination","description":"Issue description"}'

# Test HR login
curl -X POST http://localhost:8000/api/hr/login \
  -H "Content-Type: application/json" \
  -d '{"email":"hr@organization.com","password":"admin@123456"}'

# Use returned token
TOKEN="eyJhbGc..."
curl -X GET http://localhost:8000/api/hr/cases \
  -H "Authorization: Bearer $TOKEN"
```

## API Documentation

See [README.md](README.md) for complete API endpoint reference.

## Future Enhancements

- [ ] Implement refresh token rotation
- [ ] Add email notifications for case updates
- [ ] Create admin dashboard for user management
- [ ] Implement encryption at rest for sensitive fields
- [ ] Add multi-factor authentication (MFA)
- [ ] Create mobile app with offline support
- [ ] Implement machine learning for case categorization
- [ ] Add webhook support for external integrations
- [ ] Create data export functionality (PDF, Excel)
- [ ] Implement advanced search with full-text indexing

---

**Last Updated**: April 23, 2026  
**Architecture Version**: 2.0 (MVC + Repository/Service + JWT Auth)
