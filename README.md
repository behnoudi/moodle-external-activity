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
