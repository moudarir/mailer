# Changelog

All notable changes to this project will be documented in this file.

---

## [1.1.0] - 2026-11-11

Added:
* Validate provided emails before sending email;
* allow to change email's priority with `Priority` enum on `MailerConfig`.

Changed:
* Change boundary and `Message-Id` header generation;
* Rename `Protocol` enum to `Transport`;
* encode attachment's filename defined by the RFC 2231.

## [1.0.0] - 2026-11-10

Initial stable release.