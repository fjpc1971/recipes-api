<?php

declare(strict_types=1);

require __DIR__ . '/../src/autoload.php';

use App\App;
use App\Http\Request;

App::fromEnvironment()->handle(Request::fromGlobals())->send();
