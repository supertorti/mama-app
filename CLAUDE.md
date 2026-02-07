# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Family task manager ("Mama App") where parents assign tasks to children and award points. German-language app. PIN-based authentication (no passwords), JWT tokens for API sessions.

## Tech Stack

- **Backend:** Symfony 7.4, PHP 8.2+, Doctrine ORM 3.6, MySQL 8.0
- **Frontend:** Vanilla JS SPA in `public/index.html` (no build step), Bootstrap 5 (CDN), mobile-first/PWA-ready
- **Auth:** 4-digit PIN → JWT token (lexik/jwt-authentication-bundle)
- **Asset Management:** Symfony AssetMapper (no Node.js/npm required)

## Commands

```bash
# Development server
symfony serve
# or: php -S localhost:8000 -t public

# Static analysis (PHPStan level 9 — strictest)
vendor/bin/phpstan analyse

# Unit tests
./bin/phpunit
# Single test file:
./bin/phpunit tests/Path/To/TestFile.php

# API integration tests (curl-based)
./test-api.sh

# Database
php bin/console doctrine:migrations:migrate
php bin/console doctrine:fixtures:load

# JWT keypair (first-time setup)
php bin/console lexik:jwt:generate-keypair
```

## Architecture

### API Structure

All controllers live in `src/Controller/Api/`:
- `PinAuthController` — `POST /api/pin/check` (public, returns JWT)
- `AdminController` — admin endpoints under `/api/admin` (ROLE_ADMIN)
- `ChildController` — child endpoints under `/api/child` (ROLE_USER + voter)

### Security Layers

Firewall config in `config/packages/security.yaml`:
- `/api/pin/check` — no auth (public)
- `/api/admin/*` — requires ROLE_ADMIN
- `/api/*` — requires ROLE_USER
- `ChildAccessVoter` enforces children can only access their own data; admins can access any child

### Key Services

- `PinAuthenticationService` — PIN verification + JWT generation
- `PointService` — manages point transactions with audit trail via `PointTransaction` entity

### Entities

- `User` — both parents (is_admin=true) and children, with PIN hash and point balance
- `Task` — assigned to a user, has status enum (OPEN/COMPLETED), points value
- `PointTransaction` — audit log for all point changes

### Frontend

The entire SPA is in `public/index.html` (~1500 lines). It handles role selection, PIN entry, admin dashboard, task creation, and child task views — all in vanilla JS with Bootstrap.

## Code Conventions

- `declare(strict_types=1)` in all PHP files
- PHPStan level 9 — all code must pass strictest static analysis
- PHP 8+ attributes for Doctrine mapping and Symfony routing (no XML/YAML annotations)
- Autowiring and autoconfiguration enabled (services.yaml)
- 4 spaces indentation, LF line endings, UTF-8 (see .editorconfig)

## Test Data (Fixtures)

Loaded via `php bin/console doctrine:fixtures:load`:
- **Mama** — admin, PIN: 1234
- **Kind 1** — child, PIN: 0000
- **Kind 2** — child, PIN: 1111
