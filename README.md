# HTMX + Slim 4 + Supabase Authentication Example

A lightweight, minimal implementation of a secure user authentication system using **PHP (Slim 4)**, **HTMX**, and **Supabase Auth (GoTrue REST API)**. 

This repository serves as a step-by-step tutorial showing how to build an ultra-slim, server-driven auth loop without a heavy JavaScript build system or a monolithic framework.

---

## Architectural Concept

Unlike traditional single-page apps (SPAs) that handle authentication state in client-side JavaScript, this project uses a **Server-Driven UI** pattern:

1. **Secure Storage:** The Supabase JWT access token is requested server-side via an HTTP client (Guzzle) and stored inside a secure, `HttpOnly` cookie. This makes it completely immune to Cross-Site Scripting (XSS) token theft.
2. **AJAX via HTML:** HTMX intercepts form submissions and updates page fragments dynamically without page reloads.
3. **State Redirection:** If the server detects a successful login, it utilizes the `HX-Redirect` response header to cleanly prompt HTMX to perform a full-window navigation to the secure dashboard.

---

## Prerequisites

Ensure you have the following installed locally:
* **PHP 8.2 or higher**
* **Composer** (PHP dependency manager)

---

## Getting Started

### 1. Clone and Install Dependencies

```bash
git clone [https://github.com/your-username/htmx-supabase-slim-auth-example.git](https://github.com/your-username/htmx-supabase-slim-auth-example.git)
cd htmx-supabase-slim-auth-example
composer install
