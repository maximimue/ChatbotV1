# Chatbot Frontend & PHP API

This repository bundles the multi-tenant chatbot front end, the shared administration
experience, and the PHP API that proxies requests to OpenAI for the individual hotels.
Each hotel directory contains only a thin wrapper around the shared `core/` bundle so
that branding and content can be adjusted per property without duplicating logic.

## Repository layout

| Path | Description |
| ---- | ----------- |
| `core/` | Shared chat UI, admin dashboard, FAQ editor, analytics and utility code used by every hotel. |
| `api/` | PHP endpoint that accepts chat questions, enriches them with hotel-specific FAQ content, and forwards them to OpenAI. |
| `<hotel>/` | Hotel-specific wrapper (for example `aarnhoog/`, `faehrhaus/`, `roth/`). Each directory provides its own `config.php`, `index.php`, admin entry points, and assets. |

The hotel wrappers define `$configPath` and include the corresponding script from
`core/` (for example `require __DIR__ . '/../core/index.php';`) so that the shared
bundle is executed with tenant-specific configuration. The browser-side chat client
(`core/assets/js/chat.js`) reads `$API_URL` from that configuration and talks to the
PHP API. The API, in turn, loads the same hotel configuration to resolve FAQ sources,
prompt additions, and hotel URLs before calling OpenAI, so the front end and backend
stay in sync for each tenant.【F:core/index.php†L1-L52】【F:api/ask.php†L1-L99】

## Deployment & setup

1. **Publish the shared bundle.** Deploy the `core/` directory to a location that all
   hotel folders can reference (for example `/httpdocs/core`). Copy the desired hotel
   wrapper directories (`aarnhoog/`, `faehrhaus/`, `roth/`, …) alongside it (for
   example `/httpdocs/aarnhoog`).【F:aarnhoog/index.php†L1-L6】
2. **Create hotel configuration.** Copy `core/config.sample.php` to
   `<hotel>/config.php` and edit the values. At minimum you need:
   - `$API_URL` pointing at the deployed PHP API (for example
     `https://chatbot.syltwerk.de/api/ask.php?tenant=aarnhoog`).
   - `$HOTEL_NAME`, `$HOTEL_URL`, and `$BOT_NAME` for branding.
   - `$FAQ_FILE` pointing to the Markdown knowledge base that should ground the
     answers.
   - `$LOG_DB` pointing to a writable SQLite file so that analytics and FAQ logging
     work.
   Additional options in the sample file let you set prompt extensions, colors,
   background images, and more.【F:core/config.sample.php†L1-L64】
3. **Expose the front end.** Each hotel’s `index.php` includes `core/index.php`, which
   renders the chat box, pulls shared assets from `core/assets/`, and optionally loads
   hotel-specific CSS (`$HOTEL_CSS_URL`). Make sure any custom assets referenced by a
   hotel (logos, CSS, background images) live inside that hotel directory or on a
   publicly reachable URL so that `chatbot_asset_url()` can resolve them when the page
   is rendered.【F:core/index.php†L16-L51】【F:core/init.php†L48-L118】
4. **Deploy the PHP API.** Publish the contents of `api/` under a web-accessible path
   such as `/httpdocs/api`. Configure the OpenAI credentials either via server
   environment variables (`OPENAI_API_KEY`, `OPENAI_MODEL`) or by editing
   `api/config.php`. The endpoint accepts `POST /api/ask.php?tenant=<hotel>` requests
   with a JSON body `{ "question": "..." }` and returns an `answer` plus optional
   `sources`.【F:api/config.php†L1-L11】【F:api/ask.php†L1-L107】

## Branding, prompt, and color configuration

All configurable values are surfaced in the admin interface (`<hotel>/admin.php`),
which writes changes back to the hotel’s `config.php`. You can also edit the file
manually when automating deployments. The most commonly adjusted keys are:

- **Prompt tailoring:** `$PROMPT_EXTRA` appends tenant-specific guidance to the system
  prompt so each hotel can highlight its tone or offers.【F:core/config.sample.php†L66-L70】【F:api/ask.php†L40-L64】
- **Branding assets:** `$LOGO_PATH`, `$BACKGROUND_IMAGE_URL`, and `$HOTEL_CSS_URL`
  control the header logo, optional background image, and additional CSS loaded into
  the chat shell. `$BOT_NAME` sets the label shown for bot messages.【F:core/config.sample.php†L8-L28】【F:core/index.php†L16-L47】
- **Color palette:** `THEME_COLOR_BASE`, `THEME_COLOR_SURFACE`,
  `THEME_COLOR_PRIMARY`, `THEME_COLOR_PRIMARY_CONTRAST`, and
  `THEME_COLOR_TEXT` feed the core design tokens that all other chat colors are
  derived from.【F:core/config.sample.php†L20-L40】【F:core/admin.php†L92-L140】
  Legacy `CHAT_*` keys remain supported for older hotel configs but are no
  longer surfaced in the admin UI.【F:core/config.sample.php†L32-L40】【F:core/admin.php†L128-L140】

Changes made via the admin UI are persisted in `config.php`; editing the file by hand
is useful when seeding defaults before handing access to hotel operators.

## Style overrides and asset handling

`core/partials/style_overrides.php` emits a `<style>` block whenever color or
background values are provided. It translates the configuration keys above into CSS
variables on `:root` and outputs a `body { background-image: … }` rule when
`$BACKGROUND_IMAGE_URL` is set.【F:core/partials/style_overrides.php†L1-L38】 The helper
`chatbot_asset_url()` normalizes relative paths, absolute paths, and full URLs so that
assets referenced in configuration (logos, backgrounds, custom CSS) resolve correctly
regardless of whether they live inside the hotel directory, the core bundle, or an
external CDN.【F:core/init.php†L48-L118】

## Operations & usage

- **Admin tools:** The admin dashboard (`admin.php`) exposes three tabs—**Analysis**
  for reporting on chat activity, **FAQ** for editing the Markdown knowledge base, and
  **Settings** for updating the configuration keys listed above. Authentication is
  managed per hotel via the credentials defined in `config.php` or the optional
  `admin_users.json` file.【F:core/admin.php†L1-L170】
- **Analytics & logging:** Ensure `$LOG_DB` points to a writable SQLite database. The
  logging routines in `core/init.php` automatically create the `logs` table and the
  analytics helpers aggregate question/answer statistics, hourly/daily usage, and top
  topics for operators.【F:core/init.php†L30-L71】【F:core/analytics_helpers.php†L1-L88】
- **FAQ management:** The FAQ editor writes directly to the Markdown file defined by
  `$FAQ_FILE`, providing immediate context updates for the API’s retrieval step and the
  chat responses.【F:core/admin.php†L126-L170】【F:api/ask.php†L20-L43】

With the shared core deployed once and lightweight wrappers for each hotel, you can
add new properties quickly while keeping the chat experience, API integration, and
operational tooling consistent across tenants.
