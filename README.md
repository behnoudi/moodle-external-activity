# Moodle External Activity (LTI 1.3 Tool Provider)

A standalone PHP-based external activity tool implementing **LTI 1.3 (Advantage)** to integrate interactive learning content and grade synchronization with **Moodle LMS**.

---

## 📌 Features

- **LTI 1.3 / LTI Advantage** compatible.
- **OIDC Authentication Flow** (`login.php` & `launch.php`).
- **JWKS Endpoint** for cryptographic signature verification (`jwks.php`).
- **Assignment and Grade Services (AGS)** for pushing user scores back to Moodle (`score.php`).
- Modular content delivery via the `topics/` directory.

---

## 📂 Project Structure

```text
├── topics/          # Interactive activity content/topics
├── vendor/          # Composer dependencies
├── composer.json    # PHP dependency management
├── db.php           # Database connection & configurations
├── jwks.php         # Public JSON Web Key Set (JWKS) endpoint
├── launch.php       # LTI launch redirection & session initiator
├── login.php        # OIDC login initiation endpoint
├── score.php        # Grade passback script (LTI AGS)
└── README.md        # Project documentation
