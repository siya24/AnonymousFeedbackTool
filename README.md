# Anonymous Feedback Tool Monorepo

This repository now contains two separate deployable apps:

- `external/` - Public-facing anonymous reporting app
- `internal/` - Intranet HR and reporting app

## Deployment Model

### External app (`external/`)
Use this for internet/public hosting.

Exposes only:
- Anonymous feedback submission
- Follow-up submission
- Case lookup by reference
- Public lookup support endpoints (categories/statuses/stages)
- Attachment download endpoint (with existing auth/reference checks)

Does not expose:
- HR pages
- HR APIs
- Internal anonymized reporting page
- Internal reports API

Entry point:
- `external/index.php`

### Internal app (`internal/`)
Use this for intranet/VPN hosting.

Exposes:
- Full HR console and APIs
- Internal anonymized reports page (`/anonymized/reports`)
- Internal reports API (`/api/reports`) with domain/intranet/VPN access checks
- Full case management/configuration workflows

Entry point:
- `internal/index.php`

## How They Work Together

Both apps are complete and can point to the same backend database infrastructure.

- External app handles anonymous intake.
- Internal app handles investigation, workflow, and employee anonymized reporting.

## Repo Structure

- `.github/`
- `.gitignore`
- `external/`
- `internal/`
- `README.md`
