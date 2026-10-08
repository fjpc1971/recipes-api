<?php

declare(strict_types=1);

require __DIR__ . '/../src/autoload.php';

use App\App;
use App\Http\Request;

// Punto de entrada de la aplicación: construye dependencias, enruta y gestiona errores.
App::fromEnvironment()->handle(Request::fromGlobals())->send();
