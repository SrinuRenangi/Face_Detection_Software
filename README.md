# Edifies School Attendance Management System

A lightweight, enterprise-grade, server-rendered attendance product built on **PHP 8.5**, **CodeIgniter 4**, and **MariaDB 11.4**. Designed for affordable local deployment on mini-PCs or standard cPanel web hosting without requiring microservices, cloud dependencies, or heavy JavaScript runtime overhead.

---

## 1. Unified Architecture: One App, One Database, Three Access Identities

The system serves three distinct identity principals through a single unified codebase:

1. **ADMIN (`ROLE_ADMIN`):** Full human administrative control center.
   - Staff Directory management & PIN issuance.
   - Daily, monthly, and individual staff attendance reports with photo audit inspection.
   - Attendance Review Queue for unconfirmed punches.
   - Kiosk Scanner Device management, single-use pairing code generation, and revocation.
2. **MANAGER (`ROLE_MANAGER`):** Scoped operational staff access.
   - Read-only Staff Directory viewing.
   - Attendance Kiosk launching.
   - Strictly restricted from reports, employee mutations, review queue, and device pairing.
3. **KIOSK DEVICE (`DEVICE PRINCIPAL`):** Registered physical scanner terminal (smartphones, tablets, desktop webcams).
   - Authenticated via single-use 128-bit Admin pairing codes.
   - Authorized using cryptographically secure `SameSite=Strict` HttpOnly cookies (`edifies_kiosk_token`).
   - Completely isolated from human user sessions.
   - Touch-first responsive interface with camera preview and verified 6-digit PIN fallback.

---

## 2. Central Access Gateway & Routing

All access enters through a single unified entry gateway:

| Route | Role / Access Principal | Function |
| :--- | :--- | :--- |
| `/` | **Central Access Gateway** | Server-side role dispatch, human login form & scanner pairing card |
| `/login` | **Admin / Manager** | Human staff authentication with role-based automatic redirection |
| `/admin/*` | **ROLE_ADMIN Only** | Administrative control center, reports, settings, and device admin |
| `/admin/employees` | **ROLE_MANAGER / ADMIN** | Staff directory (Manager has read-only access; Admin has mutations) |
| `/kiosk/pair` | **Device Principal** | Physical scanner terminal pairing with single-use 128-bit pairing code |
| `/attendance` | **Paired Scanner / Staff** | Dedicated touchscreen kiosk with live camera preview & PIN attendance |

---

## 3. Technology Stack

- **Backend Runtime:** PHP 8.5.12
- **Framework:** CodeIgniter v4.7.6
- **Database:** MariaDB 11.4 LTS (Standard Port 3306 / Dev Port 3307)
- **Frontend:** Server-Rendered HTML, Clean CSS, Vanilla ES6 JavaScript, PWA Service Worker (`sw.js`)
- **Cryptography:** AES-256 (CI4 Encryption Service), SHA-256 (Credential Hashing), CSPRNG Token Generation
- **Test Framework:** PHPUnit 10.5.66 (100% automated regression coverage across 260 tests, 1092 assertions)

---

## 4. Setup & Installation

### Prerequisites
- PHP 8.2 or higher (PHP 8.5 recommended) with `mysqli`, `curl`, `mbstring`, `intl`, `openssl`, `gd` extensions enabled.
- MariaDB 10.6+ or 11.4 LTS.
- Composer 2.x.

### Installation Steps

1. **Clone the repository:**
   ```bash
   git clone https://github.com/SrinuRenangi/Face_Detection_Software.git
   cd Face_Detection_Software
   ```

2. **Install composer dependencies:**
   ```bash
   composer install --no-dev --optimize-autoloader
   ```

3. **Configure environment:**
   ```bash
   cp .env.example .env
   # Edit .env with your MariaDB database credentials and generate encryption key:
   php spark key:generate
   ```

4. **Run database migrations:**
   ```bash
   php spark migrate
   ```

5. **Start the local server:**
   ```bash
   php -S 127.0.0.1:8088 -t public
   ```

6. **Run regression tests:**
   ```bash
   vendor/bin/phpunit
   ```

---

## 5. Security & Isolation Guarantee

- **Credential Containment:** Device credentials (`edifies_kiosk_token`) are transported exclusively via HttpOnly cookies and never logged, rendered in HTML, or returned in JSON responses.
- **Biometric Integration Boundary:** Experimental face recognition models remain isolated in research lab and behind strict feature flags. Production attendance operates via verified Staff PINs and real-time private photo snapshots.
- **Audit Logging:** Every login attempt, pairing event, token rotation, and attendance confirmation is recorded in an immutable ledger (`admin_audit_logs`).

---

## 6. License

Proprietary &bull; Edifies School Attendance System. All rights reserved.
