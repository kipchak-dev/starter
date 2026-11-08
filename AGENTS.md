# AGENTS.md

Instructions for coding assistants working on this API. It is built with Kipchak, an API Development Kit for
PHP 8.4+ on Slim Framework 4, and runs on FrankenPHP in worker mode. Kipchak is not Laravel or Symfony: there are
no facades, global helpers or hidden conventions, and everything an endpoint does is visible in this project's code.

Documentation: <https://kipchak.dev>. An index for language models: <https://kipchak.dev/llms.txt>.
Assistants that read Agent Skills can install the Kipchak skill, which covers Kipchak in general:
<https://kipchak.dev/docs/ai-assistants>. This file describes this project.

## Commands

- `composer install`: install dependencies.
- `composer test`: run the tests. Run them after every change.
- `composer openapi`: write the OpenAPI document to `openapi.yaml`; exits with 1 if the document has problems.
- `docker compose up --watch`: run the API on <http://localhost:8080>, restarting the worker when a file changes.
- `vendor/bin/kipchak add <name>`: install a Kipchak driver or middleware (`doctrine`, `auth-key`), copy its config into
  `config/` and initialise it, with the drivers it requires. Use it rather than editing `drivers/drivers.php` or
  `middlewares/middlewares.php` by hand. `--dry-run` shows the changes first; it lists any settings, such as API keys,
  that must then be supplied.
- `vendor/bin/kipchak make:resource Orders --fields=id:int,reference:string [--crud]`: write a resource in this
  project's conventions (DTO, model, controller, routes, test). `make:endpoint POST '/v1/orders/{id:[0-9]+}/cancel'
  Orders::cancel` adds an operation and its route to an existing controller; `make:dto`, `make:controller` and
  `make:middleware` write one class each.
- `vendor/bin/kipchak openapi:validate --strict`: check the OpenAPI document against the specification and against
  the registered routes. It exits with 0 on this project; run it after changing an endpoint.

## Where things live

| Path | Contains |
|---|---|
| `routes/` | Route files. Every `.php` file is loaded at boot. Versioned routes go in `routes/v1/`, `routes/v2/`. |
| `api/Controllers/` | Controllers, namespace `Api\Controllers`; versioned ones in `v1/` (`Api\Controllers\v1`). |
| `api/DataTransferObjects/` | DTOs, versioned like the controllers. They define the API contract. |
| `api/Models/` | Business logic. |
| `api/OpenApi/Definition.php` | OpenAPI title, version and servers. |
| `api/Utils/` | Code shared across the API. |
| `api/Middlewares/` | Middleware of this project's own (PSR-15). |
| `config/` | One PHP file per driver or middleware, returning an array. Read with `Kipchak\Driver\Config\Config::get('kipchak.api')`. |
| `drivers/drivers.php` | Initialises drivers, in order. Config first, then Logger. |
| `middlewares/middlewares.php` | Middleware applied to every request. Slim runs the last added first. `Error::initialise($app)` is the first and the last line; add middleware between them. |
| `tests/` | PHPUnit tests. Extend `Tests\ApiTestCase` and call `$this->request('GET', '/path')`. |

## Adding an endpoint

Follow `GET /v1/sultans` (`routes/v1/sultans.php`, `api/Controllers/v1/Sultans.php`,
`api/DataTransferObjects/v1/MamlukSultan.php`, `api/Models/Sultans.php`):

1. **DTO.** Create or extend the DTO in `api/DataTransferObjects/v1/`, as a `final readonly` class with constructor
   properties. Give the class `#[OA\Schema(schema: 'Name')]` and each property `#[Field(...)]` with a description and
   an example. `Field` (`Kipchak\Core\Components\Dto\Field`) takes everything `OA\Property` takes. Write a rule once as
   its `minLength`, `maxLength`, `minimum`, `maximum`, `pattern` or `enum`: the rule appears in the OpenAPI document and
   is enforced on request bodies. For a rule an attribute cannot state, such as one that depends on the date, add
   `#[Check([self::class, 'method'])]` and say what it does in the description. The server-set `id` stays
   `#[OA\Property(...)]`. The same DTO describes the request body and the response.
2. **Model.** Put the logic in `api/Models/`. Map stored rows into DTOs with Valinor
   (`(new MapperBuilder())->mapper()->map(Dto::class, Source::array($data))`), which checks their types. It does not
   enforce `Field` rules, so tightening a rule leaves old rows readable.
3. **Controller.** Add a method to a controller in `api/Controllers/v1/` that extends
   `Kipchak\Core\Components\Controllers\Base`, with the signature
   `(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface`. Describe it
   with `#[OA\Get]`, `#[OA\Post]` and so on: path, `operationId`, summary, tags, parameters, request body and
   responses. Refer to schemas with `ref: '#/components/schemas/Name'`; never define a DTO's fields inline.
   Read a request body with `Kipchak\Core\Components\Dto\Input::from($request, Dto::class, ['id' => 0])`, giving any
   values the server sets. It enforces the `Field` rules and answers an invalid body with a 400 that lists every
   problem at once, in `data` and by field in `meta.errors`. Do not catch that exception.
   Return `Kipchak\Core\Components\Http\Response::json($response, $data, 200)`, which wraps `$data` in the
   envelope `{"code", "status", "data"}`. Declare only the data in a response, such as
   `new OA\JsonContent(ref: '#/components/schemas/Name')`, and give an error response a description but no
   content: `Api\Utils\OpenApi` states the envelope around each one.
4. **Route.** Register it in a file in `routes/v1/`: `$group->get('/path/{id:[0-9]+}', [Controller::class, 'method'])`.
5. **Test.** Add a test in `tests/` for the success and error responses, and run `composer test`. The OpenAPI tests
   check that every route is documented and that the document is valid, so a route added without its operation
   fails them.

## Rules

- Start every PHP file in `api/` and `tests/` with `declare(strict_types=1);`. Make classes `final` unless they are
  meant to be extended, and type every property, parameter and return value.
- Keep controllers thin: read the request, call a model, return a response.
- Report errors by throwing Slim's HTTP exceptions (`HttpNotFoundException`, `HttpBadRequestException`,
  `HttpUnauthorizedException`, `HttpForbiddenException`). The Error middleware turns them into JSON responses with
  the same envelope. Other exceptions become a 500 whose message is hidden from the client.
- Worker mode keeps the API in memory between requests. Never keep request data in a static property, a global or
  a singleton. A static property may only cache something that is the same for every request, as
  `Api\Controllers\OpenApi` does.
- Use a Kipchak driver for a service when one exists, rather than a client library directly: the drivers are
  listed on <https://kipchak.dev/ecosystem>. Install it with Composer, copy its config file into `config/`, and
  initialise it in `drivers/drivers.php` after Config and Logger. Read it with the driver's `get()` method, for
  example `Kipchak\Driver\Doctrine\Doctrine::get('entitymanager.primary')`.
- Read settings from `config/` files and environment variables through `Kipchak\Core\env('NAME', 'default')`,
  never by calling `getenv()` in controllers or models.
- Use the logger in controllers through `$this->logger` (Monolog, PSR-3).
- An API change is a contract change: update the DTO and the controller's attributes together, then the tests.
