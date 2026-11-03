<?php

use Api\Controllers;

/**
 * Every file in routes/ is loaded at boot. Version routes by folder, as routes/v1/ does.
 *
 * @var \Kipchak\Core\Components\App $app
 */
$app->get('/status', [Controllers\Status::class, 'get']);
