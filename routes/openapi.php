<?php

use Api\Controllers;

/**
 * @var \Kipchak\Core\Components\App $app
 */
$app->get('/openapi.json', [Controllers\OpenApi::class, 'get']);
