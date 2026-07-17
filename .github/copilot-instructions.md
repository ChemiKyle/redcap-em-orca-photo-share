# GitHub Copilot Architecture & Style Instructions

## Tech Stack Overview
- Backend: PHP 8.1+
- Database: MySQL 5.5+ & MariaDB 10+ compatible
- Frontend: Vue.js 3 (Composition API, `<script setup>`), Bootstrap 5, FontAwesome 6 Free

## Token & Efficiency Constraints
- Keep code blocks minimal; write only the requested change, not full classes/files.
- Do not add placeholders, fake data, or boilerplate comment blocks.
- Never write tests, docs, or types unless explicitly asked.

## Backend Rules (PHP 8.1+)
- Use strict types (`declare(strict_types=1);`) in every new PHP file.
- Leverage PHP 8.1 features: constructor property promotion, enums, and readonly properties.
- Use arrow functions `fn()` for short, single-expression closures.
- Catch specific exceptions; never use a generic catch-all `Exception` block.

## Database Rules (MySQL 5.5+ & MariaDB 10+ Dual Compatibility)
- Write pure SQL ANSI-compliant syntax or use basic PDO prepared statements.
- Never use modern MySQL-only features like JSON functions or window functions (`OVER`, `PARTITION BY`).
- Avoid common table expressions (`WITH` clauses) to maintain legacy MySQL 5.5/5.6 compatibility.
- Ensure strict database compatibility rules:
    - Do not use `ROW_NUMBER()`, `RANK()`, or `LEAD() / LAG()`.
    - Do not use `JSON_CONTAINS()` or `JSON_EXTRACT()`.
    - Use standard indexed columns instead of virtual/generated columns.
- Use explicit column names in all `SELECT` queries; never use `SELECT *`.
- Bind all inputs parameters using prepared statements to enforce security and block injection leaks.

## Frontend Rules (Vue 3, Bootstrap 5, FontAwesome 6)
- **Vue 3:** Use `<script setup>` syntax. Use `ref` and `computed` instead of Option API structures.
- **Bootstrap 5:** Use native utility classes for layout/spacing. Do not inject custom CSS or Tailwind classes. Never write jQuery; use Vue directives (`v-model`, `v-on`) for DOM manipulation.
- **FontAwesome:** Use standard class syntax (e.g., `<i class="fa-solid fa-user"></i>`). Only use Free icons (Solid, Regular, Brands). Do not use Pro-only variants.
- **Multi-byte Safety:** All text processing, string manipulation, and validation functions must be multi-byte safe and explicitly handle UTF-8 encoding.
- **Prohibited Functions:** Do not use legacy single-byte string functions. For example, in JavaScript, avoid `.substr()`, `.charAt()`, or bitwise operations on strings; use `.substring()`, `.codePointAt()`, or `Intl` APIs instead.
- **Length Constraints:** When calculating string lengths or slicing strings, measure by code points rather than raw byte or code unit indices.
- **Input Validation:** Ensure all frontend forms and API responses properly sanitize and decode UTF-8 characters to prevent display corruption or security vulnerabilities. 