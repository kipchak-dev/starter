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
│   ├── OpenApi/              # The top of the OpenAPI document: title, version, servers, shared responses
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
title and shared responses in `api/OpenApi/Definition.php`. `/openapi.json` serves it, and

```bash
composer openapi
```

writes it to `openapi.yaml` for client generation or CI. A problem found while generating the document, such as a
reference to a schema that does not exist, is logged by `/openapi.json`, and printed by `bin/openapi`, which then
exits with 1.

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

## Licence

MIT. See [LICENSE](LICENSE).
