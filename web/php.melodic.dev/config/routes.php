<?php

declare(strict_types=1);

use MelodicWeb\Controllers\DocsController;
use MelodicWeb\Controllers\HomeController;
use MelodicWeb\Controllers\TutorialController;
use Melodic\Routing\Router;

return function (Router $router): void {
    // Marketing pages
    $router->get('/', HomeController::class, 'index');
    $router->get('/why-melodic', HomeController::class, 'whyMelodic');

    // Documentation
    $router->get('/docs', DocsController::class, 'index');
    $router->get('/docs/{page}', DocsController::class, 'show');

    // Tutorials
    $router->get('/tutorials', TutorialController::class, 'index');
    $router->get('/tutorials/{slug}', TutorialController::class, 'show');
};
