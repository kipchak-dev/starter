<?php

use Kipchak\Driver\Mcp\Mcp;

/**
 * The MCP endpoint an AI assistant connects to. Its tools are listed in config/kipchak.mcp.php.
 *
 * This route has no sign-in, like /status, because its one tool only reports that the API is running. A tool that
 * reads or changes data must sit behind an authentication middleware: see "Behind authentication" on
 * https://kipchak.dev/docs/driver-mcp, which also sets 'principal_attribute'.
 *
 * Every method is routed, not only POST: the endpoint answers POST, DELETE and OPTIONS itself.
 *
 * @var \Kipchak\Core\Components\App $app
 */
$app->any('/mcp', Mcp::endpoint('default'));
