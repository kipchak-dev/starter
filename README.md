<img src=".mamluk/logo.png" width="96" alt="Kipchak" />

# Kipchak Starter

The starter project for [Kipchak](https://kipchak.dev), a PHP API Development Kit built on
[Slim Framework 4](https://www.slimframework.com/) and run on [FrankenPHP](https://frankenphp.dev/) in worker mode.
It runs on PHP 8.4 and 8.5.

It contains one versioned resource, with its route, controller, DTO and model, an OpenAPI document generated from
the code, a health check and tests. Nothing else is installed: add the drivers and middleware your API needs.

**Documentation: <https://kipchak.dev>**

> Upgrading an existing project? See the [upgrade guide](https://kipchak.dev/docs/upgrades).

---

## Create a project

With PHP 8.4 or later and Composer:

```bash
composer create-project kipchak/starter my-api
cd my-api
docker compose up --watch
```

With Docker alone:

```bash
docker run --rm -u "$(id -u):$(id -g)" -v "$PWD":/app -w /app composer create-project kipchak/starter my-api
cd my-api
docker compose up --watch
```

The API is then on <http://localhost:8080>. `--watch` restarts the worker when a file changes. To use another port,
start it with `PORT=8081 docker compose up --watch`.

**A browser application on another site**, such as a front end on <http://localhost:5173>, can call the API only
once its origin is listed in `CORS_ALLOWED_ORIGINS`: in `docker-compose.yml` for local work, and in your deployment's
environment. Separate several with commas. Until then the API refuses those calls, and the first lines of the log
say so (`CORS: no origins listed`). Calls from the same site, from servers and from curl need nothing. See
`config/kipchak.cors.php` and <https://kipchak.dev/docs/starter-project#cors>.

| Request | Response |
|---|---|
| `GET /v1/sultans` | Every sultan |
| `GET /v1/sultans/4` | One sultan; an unknown ID returns a 404 |
| `GET /openapi.json` | The OpenAPI document, generated from the code |
| `GET /status` | A health check |

---

## Project structure

```text
├── api/
│   ├── Controllers/          # Request handlers, versioned under v1/, v2/ and so on
│   ├── DataTransferObjects/  # The API contract: one DTO per shape of data, versioned with the controllers
│   ├── Middlewares/          # Middleware of your own
│   ├── Models/               # Business logic
│   ├── OpenApi/              # The top of the OpenAPI document: title, version, servers
│   └── Utils/                # Code shared across the API
├── bin/openapi               # Writes the OpenAPI document to a file
├── config/                   # One PHP file per driver or middleware, returning an array
├── drivers/drivers.php       # The drivers the API initialises, in order
├── middlewares/middlewares.php # Middleware applied to every request
├── routes/                   # Route files, loaded at boot and versioned by folder
├── html/
│   ├── index.php             # FrankenPHP worker-mode entry point
│   └── index.nonworker.php   # Entry point without worker mode
├── tests/                    # PHPUnit tests, run against the booted API
└── AGENTS.md                 # Instructions for coding assistants
```

A request follows the folders in order: a **route** names the **controller** that handles it; the controller reads
the request and calls a **model**; the model holds the business logic and returns **DTOs**; the controller returns
them in Kipchak's response envelope:

```json
{"code": 200, "status": "OK", "data": {"id": 4, "name": "Baybars", "reignFrom": 1260, "reignTo": 1277}}
```

Errors use the same envelope, from the Error middleware: throw one of Slim's HTTP exceptions, such as
`HttpNotFoundException`, and the client receives `{"code": 404, "status": "RESOURCE_NOT_FOUND", "data": "..."}`.

## OpenAPI

The OpenAPI document is generated from PHP attributes: operations on controller methods, schemas on DTOs, and the
title in `api/OpenApi/Definition.php`. An operation declares only the data of each response;
`api/Utils/OpenApi.php` states the envelope around it, and gives an error response that declares no content the error
envelope. `/openapi.json` serves it, and

```bash
composer openapi
```

writes it to `openapi.yaml` for client generation or CI. A problem found while generating the document, such as a
reference to a schema that does not exist, is logged by `/openapi.json`, and printed by `bin/openapi`, which then
exits with 1.

```bash
vendor/bin/kipchak openapi:validate --strict
```

checks the document against the OpenAPI specification and against the routes the API registers: every route has an
operation, every path parameter is declared, and every operation has an identifier, a summary and a tag. See
[the CLI](https://kipchak.dev/docs/cli#kipchak-openapi-validate).

## Tests

```bash
composer test
```

The tests boot the API as a worker does and send requests to it without a web server; see `tests/ApiTestCase.php`.

## Drivers and middleware

Drivers connect the API to other services: databases, caches, queues, object storage, AI models and more. Add one
with the Kipchak CLI:

```bash
vendor/bin/kipchak add doctrine
```

It installs the package with Composer, copies its config file into `config/`, and initialises it in
`drivers/drivers.php` after the drivers it needs, adding those too. Middleware is added the same way, into
`middlewares/middlewares.php` between its two `Error::initialise($app)` lines: the first turns an exception from a
route into a JSON error response, and the last does the same for an exception from any middleware. The CLI writes
plain code into your files and nothing at run time depends on it; you can make the same changes by hand. Each
package's page on [kipchak.dev](https://kipchak.dev/ecosystem) gives its installation, configuration and use.

The [examples repository](https://1x.ax/mamluk/kipchak/examples) has a working route for every free driver and
middleware, with a Docker Compose service for each one that needs a server.

## Worker mode

FrankenPHP's worker mode boots the API once and keeps it in memory across requests, which makes it fast, but means
anything stored in a static property or a global outlives the request. Keep request data in the request and in
local variables. [Caddy and FrankenPHP](https://kipchak.dev/docs/caddy-and-frankenphp) describes the worker loop in
`html/index.php` and how to run without worker mode.

## Metrics

Prometheus metrics, including FrankenPHP's worker and thread metrics, are served at `/metrics` on port 2019 by a
listener that answers nothing else. Set `METRICS_PORT` to use another port. Caddy's administration API is off
(`admin off`): a running API never needs to change its configuration, and the administration API has no
authentication. `tests/CaddyfileTest.php` fails if a Caddyfile turns it back on.

## Release notes

### 4.12

Caddy's administration API is off, and Prometheus metrics stay on port 2019, served by a listener that answers
`/metrics` and returns 404 for every other path. Before 4.12, both Caddyfiles set `admin :2019`, which opened the
administration API on every network interface of the container. Monitoring keeps the same port and path. To adopt the
change in an existing project, replace `admin :2019` with `admin off` as the last option of the global block, and add
the `:{$METRICS_PORT:2019}` block from `etc/caddy/Caddyfile`. Do not keep `admin :2019` beside the new block: Caddy
lets the two share the port, and some connections would still reach the administration API. The steps are on
[Upgrading](https://kipchak.dev/docs/upgrades).

### 4.11

A browser application on another site can now call the API with a sign-in token or a JSON body. The allowed sites
come from `CORS_ALLOWED_ORIGINS` (`config/kipchak.cors.php`), empty by default, which sends no CORS headers; a listed
origin is echoed, and `*` allows every origin without credentials. The list is checked when the worker starts, and
the log states the mode. The browser's preflight request is answered before the router for every path that has
routes, while `/mcp` keeps its own origin list and an unknown path still answers 404. Projects created from an
earlier version keep their own `html/index.php`; see <https://kipchak.dev/docs/upgrades> to adopt the change.

### 4.8

The commented Doctrine block in `html/index.php`, which checks the database connection with `SELECT 1` before each
request, now skips that check for health paths (`/livez` and `/status`), so a liveness probe answers while the
database is down. The comments also say to give the web worker a short MySQL read timeout with
`ini_set('mysqlnd.net_read_timeout', ...)`: PDO has no read-timeout attribute for MySQL, and without it a request
waits up to 86,400 seconds for a database that has stopped answering. Long-running commands, such as migrations, keep
the default. See the [Doctrine driver](https://kipchak.dev/docs/driver-doctrine). The block stays commented out, so
nothing changes for a project that has not enabled it.

## Licence

MIT. See [LICENSE](LICENSE).
