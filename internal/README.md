# Anonymous Feedback Tool - Enterprise MVC Application

A secure, anonymous feedback collection platform for organizations built with PHP 8+, MySQL 5.7+, and modern web standards.

## Features

✅ **Anonymous Feedback Submission** - Employees submit feedback without revealing identity  
✅ **Secure Follow-up System** - Reference number-based case tracking  
✅ **HR Management Console** - JWT-authenticated case management dashboard  
✅ **Employee Reporting Portal** - View anonymized feedback summaries  
✅ **Audit Trail** - Complete activity logging for compliance  
✅ **Role-Based Access Control** - HR and Ethics roles with different permissions  
✅ **JWT Authentication** - Modern token-based security for API  
✅ **Responsive Design** - Bootstrap 5 with mobile-first approach  
✅ **Professional Branding** - Legal Aid South Africa colors & styling  

## Architecture

**MVC + Repository/Service Layers** - Enterprise-grade clean architecture

```
app/
├── Core/              # Framework (Router, Request, Response, JWT, Auth)
├── Controllers/       # HTTP request handlers (API & Web)
├── Services/          # Business logic layer
├── Repositories/      # Data access layer
├── Models/            # Legacy model (maintained for compatibility)
└── Views/             # HTML templates

database/
├── schema.sql         # Feedback tables
└── users.sql          # HR/Ethics users table

public/
├── assets/
│   ├── css/          # Bootstrap + custom styling
│   └── js/           # Client-side API client
└── legal_aid_logo.png

config/               # Database & app configuration
```

**Full documentation**: See [ARCHITECTURE.md](ARCHITECTURE.md)

## Quick Start

### Prerequisites
- PHP 8.0+
- MySQL 5.7+

### Installation

1. **Clone the repository**
   ```bash
   git clone https://github.com/siya24/AnonymousFeedbackTool.git
   cd AnonymousFeedbackTool
   ```

2. **Set environment variables** (optional - uses defaults if not set)
   ```bash
   export JWT_SECRET="your-32-character-secret-key"
   export DB_HOST="localhost"
   export DB_PORT="3306"
   export DB_DATABASE="anonymous_feedback_tool"
   export DB_USERNAME="root"
   export DB_PASSWORD="CHANGE_ME_DB_PASSWORD"
   ```

3. **Start PHP server**
   ```bash
   php -S localhost:8000
   ```

4. **Open browser**
   ```
   http://localhost:8000
   ```

The application will automatically create the database and tables on first run!

### Default Credentials

```
Email: hr@organization.com
Password: CHANGE_ME_PASSWORD
Role: HR

Email: ethics@organization.com  
Password: CHANGE_ME_PASSWORD
Role: Ethics (read-only)
```

⚠️ **Change these immediately in production!**

## Database Configuration

### MySQL Connection

```
Host: localhost
Port: 3306
Database: anonymous_feedback_tool
Username: root
Password: CHANGE_ME_DB_PASSWORD
```

Configure via environment variables:
```bash
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=anonymous_feedback_tool
DB_USERNAME=root
DB_PASSWORD=CHANGE_ME_DB_PASSWORD
JWT_SECRET=your-secret-key-here
```

### Tables
- `reports` - Feedback cases
- `report_updates` - Follow-up messages
- `attachments` - File uploads
- `audit_logs` - Activity tracking
- `notifications` - Alert records
- `users` - HR/Ethics users (NEW)

## API Endpoints

### Public Endpoints (No Authentication)

#### Submit Anonymous Feedback
```http
POST /api/feedback
Content-Type: multipart/form-data

{
  "category": "Discrimination|Harassment|...",
  "description": "Feedback text (max 5000 chars)",
  "attachments[]": "Optional multiple files (documents, images, audio, video, archives; max 25MB each)"
}

Response: { "reference_no": "AF-20260423-ABC123" }
```

#### Submit Follow-up
```http
POST /api/feedback/update
Content-Type: application/json

{
  "reference_no": "AF-20260423-ABC123",
  "update_text": "Additional information"
}

Response: { "update_reference_no": "UPD-20260423-XYZ" }
```

#### Get Case Details
```http
GET /api/feedback/AF-20260423-ABC123

Response: { reference_no, category, status, updates, attachments }
```

#### List Public Reports
```http
GET /api/reports?category=Discrimination&status=Investigation%20completed

Response: { data: [{ reference_no, category, status, ... }] }
```

### HR/Ethics Endpoints (JWT Authentication)

#### Login & Get Token
```http
POST /api/hr/login
Content-Type: application/json

{
  "email": "hr@organization.com",
  "password": "CHANGE_ME_PASSWORD"
}

Response: { token: "eyJhbGc...", user: { id, name, email, role } }
```

#### List Cases (Requires HR or Ethics role)
```http
GET /api/hr/cases?status=Investigation%20pending
Authorization: Bearer {JWT_TOKEN}

Response: { data: [{ id, reference_no, category, status, ... }] }
```

#### Get Case Detail (Requires HR or Ethics role)
```http
GET /api/hr/cases/AF-20260423-ABC123
Authorization: Bearer {JWT_TOKEN}

Response: { data: { report, updates, attachments, audit } }
```

#### Update Case (Requires HR role only)
```http
POST /api/hr/cases/AF-20260423-ABC123
Content-Type: application/json
Authorization: Bearer {JWT_TOKEN}

{
  "priority": "High",
  "status": "Investigation completed",
  "outcome_comments": "Resolution details"
}

Response: { success: true, message: "..." }
```

#### Get Current User
```http
GET /api/hr/me
Authorization: Bearer {JWT_TOKEN}

Response: { user: { id, name, email, role } }
```

#### Logout
```http
POST /api/hr/logout
Authorization: Bearer {JWT_TOKEN}

Response: { message: "Logged out" }
Note: Client removes JWT from localStorage
```

## Security Features

✅ **JWT Token-Based Auth** - Stateless, scalable authentication  
✅ **Password Hashing** - bcrypt with automatic salting  
✅ **Role-Based Access** - HR and Ethics roles with different permissions  
✅ **SQL Injection Prevention** - PDO prepared statements  
✅ **Audit Logging** - Track all actions for compliance  
✅ **File Upload Validation** - Extension and size checks  
✅ **CORS Ready** - Can be configured for specific domains  

## Testing

### Test Anonymous Submission
```bash
curl -X POST http://localhost:8000/api/feedback \
  -H "Content-Type: application/json" \
  -d '{
    "category": "Discrimination",
    "description": "Test feedback"
  }'
```

### Test HR Login
```bash
curl -X POST http://localhost:8000/api/hr/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "hr@organization.com",
    "password": "CHANGE_ME_PASSWORD"
  }'
```

### Test Protected Endpoint
```bash
TOKEN="eyJhbGc..." # from login response
curl -X GET http://localhost:8000/api/hr/cases \
  -H "Authorization: Bearer $TOKEN"
```

## Frontend

### Employee Interface
- **New Feedback**: Submit anonymous feedback with attachments
- **Follow-up**: Retrieve case details and submit updates using reference number
- **Reporting**: View anonymized summaries of reported issues

### HR Console
- **Login**: Authenticate with email and password (generates JWT token)
- **Case Management**: View, filter, and search feedback cases
- **Case Details**: Read full case information with updates and attachments
- **Case Update**: Update priority, status, notes, and outcomes (HR only)

JavaScript handles JWT token storage and all API interactions.

## Development

### Project Structure

```php
// 1. Create Repository method (data access)
class FeedbackRepository {
    public function getData() { ... }
}

// 2. Create Service method (business logic)
class FeedbackService {
    public function processData() {
        return $this->repository->getData();
    }
}

// 3. Create Controller method (HTTP handler)
class FeedbackApiController {
    public function endpoint(array $params): void {
        $result = $this->service->processData();
        Response::json($result);
    }
}

// 4. Register route
$router->add('GET', '/api/endpoint', [FeedbackApiController::class, 'endpoint']);
```

### Adding New Endpoints

1. Add method to `FeedbackRepository` (if database access needed)
2. Add method to `FeedbackService` (business logic layer)
3. Add method to appropriate Controller (HTTP handler)
4. Register route in `index.php`

## Troubleshooting

| Issue | Solution |
|-------|----------|
| Database connection error | Ensure MySQL running, check credentials in config/database.php |
| JWT token invalid | Check JWT_SECRET env var, verify token not expired (24 hrs) |
| Permission denied | Verify user role in users table, check endpoint requires correct role |
| File upload fails | Ensure uploads/ directory exists with write permissions |
| 401 Unauthorized | Include valid JWT token in Authorization: Bearer header |

## Compliance

This application is designed for GDPR and labor law compliance:

- ✅ Anonymity guaranteed (no identity collection)
- ✅ Secure reference-based tracking
- ✅ Complete audit trail
- ✅ Retaliation protection built-in
- ✅ Data retention configurable

## Support

For issues, feature requests, or questions:
1. Check [ARCHITECTURE.md](ARCHITECTURE.md) for technical details
2. Review API endpoint examples above
3. Test locally before production deployment
4. Enable error logging in production

## License

MIT License - See LICENSE file for details

## Legal

**Legal Aid South Africa** - Your voice. For justice.

This application is designed for organizations to securely collect anonymous feedback from employees. All data is handled with strict confidentiality and protection against retaliation.

---

**Version**: 2.0 (MVC + Repository/Service + JWT Auth)  
**Last Updated**: April 23, 2026  
**Repository**: https://github.com/siya24/AnonymousFeedbackTool


