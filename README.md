# HTMX + Slim 4 + Supabase Authentication Example

A lightweight, minimal implementation of a secure user authentication system using **PHP (Slim 4)**, **HTMX**, and **Supabase Auth (GoTrue REST API)**. (Web Component integration coming soon!)

This repository serves as a step-by-step tutorial showing how to build an ultra-slim, server-driven auth loop without a heavy JavaScript build system or a monolithic framework.

---

## But why?
I see a web coming in which AI agent users outnumber human users. The web site architecture du jour is SPA (Single-page Applications) that are largely driven by client-side JavaScript (see [React](https://react.dev), [Vue](https://vuejs.org), [Svelte](https://svelte.dev), [Angular](https://angular.dev), etc.). We have optimized the web for client-side JavaScript execution (in the name of pleasant human-machine interaction or user interface), assuming the client is always a human using a browser engine like V8. This is problematic for for future AI web Agents (and incidentally, right now, the number of tokens they have to burn through in order to understand on online app).

SPAs have a **tendency** to create illegible, unsemantic HTML that is hard for AI agents to parse. Technically, an engineer can write flawless, semantic HTML inside a React or Svelte component. The real issue isn’t that SPAs can't be semantic; it’s that the SPA architecture fundamentally divorces semantic HTML from the data lifecycle. In an SPA, the HTML sent from the server is often a hollow shell (<div id="app"></div>). The actual semantic structure is generated imperatively in the browser after API calls fetch raw JSON data. An AI agent visiting an SPA cannot simply read the source; it must run a full browser simulation, wait for hydrated JavaScript execution, and scrape an ephemeral DOM. Moreover, by moving the application state entirely back to the server, you eliminate client-side racing conditions. There is no client-side state engine to desynchronize from the UI. Because HTMX relies on declarative HTML elements (hx-post), the DOM structure and its functional capability are delivered simultaneously as a singular hypermedia unit. If the button exists on the screen, its behavior exists instantly.

**Hypermedia as the Engine of Application State (HATEOAS)**: When an AI agent hits a JSON API, it has to guess what to do next based on external documentation. But when an app returns HTML with explicit forms, actions, and targets (hx-post="/auth/login"), the HTML itself tells the agent exactly what capabilities are available at that exact moment. HTML is self-documenting for an agent; JSON would require additional metadata to describe the API. Why? It's already built into the HTML spec!

**Deterministic vs. Non-Deterministic Interaction**: SPAs rely heavily on client-side state machines, local storage, and complex event listeners attached to generic <div> tags. AI agents could excel at reading structured document flows but struggle with non-deterministic UI states caused by asynchronous client-side race conditions.

**Web Components Encapsulate Semantics**

Standard HTML has a finite vocabulary (`<article>`, `<form>`, `<button>`). If you are building a complex UI—say, a live data graph or an interactive checkout container—standard tags hit a ceiling.

Web Components allow you to create custom, highly descriptive elements like <secure-checkout> or <data-spindle-diagram>. This creates a custom domain-specific language (DSL) directly in the markup. An AI agent scanning the DOM instantly understands the exact operational boundary and purpose of that element.

**HTMX Unifies the Network State**

While Web Components manage the capsule, HTMX manages the behavior. Instead of writing custom JavaScript fetch() requests inside your Web Component's shadow root, you use standard HTMX attributes on or inside the custom element.

An AI agent can more readily understand what this custom element is, what triggers it, and exactly what it alters.

```HTML
<user-profile-card id="profile">
    <button hx-get="/api/user/123" hx-target="#profile" hx-swap="outerHTML">
        Refresh Profile Data
    </button>
</user-profile-card>
```

By pairing Web Components and HTMX, you get the absolute best of both worlds: Web Components give the AI agent unambiguous structural meaning, while HTMX gives the agent clear, declarative execution paths—all without a single line of client-side application state logic. Because HTMX only sends partial HTML back and forth, such elements can even be incorporated into typical chat agents (although that is a generally boring use of LLMs on the web).

Combining Web Components with HTMX creates an incredibly powerful paradigm for an AI-agent-centric web.

**A Note about the new [WebMCP Standard](https://webmcp.dev)**
Finally, this style of hypermedia system makes utilizing the [WebMCP Standard](https://webmcp.dev) in parallel simpler. WebMCP allows an AI agent to query a server's capabilities directly over a standardized protocol.

In a WebMCP-enabled future, a hypermedia backend (Slim+PHP is just an example; bun+express or python flask) wouldn't just serve HTML endpoints to HTMX; it would expose those exact same routing capabilities as formal Tools and Prompts via an MCP server endpoint.

1. Discovery: An AI agent lands on your app. Via WebMCP, it asks the server: "What tools do you expose?"

2. Capability Mapping: The hypermedia backend responds: "I have a tool called login which requires an email and password, and a tool called fetch_dashboard."

3. Execution: The agent executes the tool natively via the protocol, and the server can respond with the exact HTMX-ready hypermedia fragment needed to update the interface.

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
# Clone the repository
git clone https://github.com/russellbits/htmx-supabase-slim-auth-example.git
cd htmx-supabase-slim-auth-example\

# Install backend dependencies via Composer
composer install

# Initialize your local environment file
cp .env.example .env

# Open .env and add your Supabase credentials

# Start the PHP built-in development server
php -S localhost:8080 -t public
```
