<?php

declare(strict_types=1);

namespace MelodicWeb\Controllers;

use Melodic\Controller\MvcController;
use Melodic\Http\Response;

class TutorialController extends MvcController
{
    private const TUTORIALS = [
        'build-a-rest-api' => 'Build a REST API',
        'build-a-blog' => 'Build a Blog',
        'adding-authentication' => 'Adding Authentication',
        'custom-middleware' => 'Writing Custom Middleware',
        'testing-your-app' => 'Testing Your Application',
    ];

    public function index(): Response
    {
        $this->viewBag->title = 'Tutorials — Melodic PHP Framework';
        $this->viewBag->tutorials = self::TUTORIALS;
        $this->viewBag->pages = self::TUTORIALS;
        $this->viewBag->currentPage = null;
        $this->viewBag->sidebarType = 'tutorials';
        $this->setLayout('layouts/docs');

        return $this->view('tutorials/index');
    }

    public function show(string $slug): Response
    {
        if (!isset(self::TUTORIALS[$slug])) {
            return $this->notFound(['error' => 'Tutorial not found']);
        }

        $this->viewBag->title = self::TUTORIALS[$slug] . ' — Melodic PHP Framework';
        $this->viewBag->tutorials = self::TUTORIALS;
        $this->viewBag->pages = self::TUTORIALS;
        $this->viewBag->currentPage = $slug;
        $this->viewBag->pageTitle = self::TUTORIALS[$slug];
        $this->viewBag->sidebarType = 'tutorials';
        $this->setLayout('layouts/docs');

        return $this->view('tutorials/' . $slug);
    }
}
