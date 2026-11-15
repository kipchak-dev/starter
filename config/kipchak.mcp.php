<?php

use function Kipchak\Core\env;

/**
 * Model Context Protocol (MCP) servers. Each server is one endpoint an AI assistant connects to. Route it in a file
 * under routes/:
 *
 *     $app->any('/mcp', \Kipchak\Driver\Mcp\Mcp::endpoint('default'));
 *
 * Route every HTTP method (any), not only POST: the endpoint answers POST, DELETE and OPTIONS itself.
 *
 * A tool that reads or changes data must sit behind an authentication middleware on that route. See
 * https://kipchak.dev/docs/driver-mcp for the route behind authentication, with 'principal_attribute' set.
 */
return [
    'servers' => [
        'default' => [
            // How the server introduces itself to the assistant.
            'name' => env('MCP_NAME', 'Kipchak API'),
            'version' => '1.0.0',
            'instructions' => null,

            // Classes whose public methods carry #[McpTool], #[McpResource], #[McpResourceTemplate] or #[McpPrompt].
            // They are built by the Kipchak container, so their constructors can ask for any service.
            'tools' => [
                Api\Mcp\StatusTools::class,
            ],

            // Or directories, relative to the project root, to scan for those attributes.
            'discovery' => [],

            // The host names clients use to reach this API, without the port. A request for any other host is
            // refused with 403 and a warning in the log. Set MCP_HOST when the API is deployed.
            'allowed_hosts' => [env('MCP_HOST', 'localhost')],

            // Origins of browser-based clients, such as 'https://app.example.com'. Clients outside a browser send
            // no Origin and need nothing here.
            'allowed_origins' => [],

            // Sessions are off: each request stands alone, so the API needs no shared store however many copies
            // of it run. To keep sessions, name a PSR-16 cache from another driver, for example
            // 'drivers.cache.valkey.cache', and set 'principal_attribute' as well.
            'sessions' => null,
            'session_ttl' => 3600,

            // The request attribute your authentication middleware sets to the caller's identity. Sessions are
            // bound to it, so one caller cannot use another's session. Required when 'sessions' is set.
            'principal_attribute' => null,

            // When that attribute is a verified token (kipchak/middleware-auth-jwks and auth-jwt set an object of claims),
            // the claim that identifies the caller.
            'principal_claim' => 'sub',

            // The largest request body accepted, in bytes. Larger bodies are refused with 413.
            'max_body_bytes' => 4194304,
        ],
    ],
];
