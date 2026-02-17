<?php

declare(strict_types=1);

namespace MelodicWeb\Controllers;

use Melodic\Controller\MvcController;
use Melodic\Http\Response;

class DocsController extends MvcController
{
    private const SECTIONS = [
        'Getting Started' => [
            'getting-started' => 'Getting Started',
            'configuration' => 'Configuration',
        ],
        'Core' => [
            'routing' => 'Routing',
            'controllers' => 'Controllers',
            'middleware' => 'Middleware',
            'dependency-injection' => 'Dependency Injection',
        ],
        'Data & Services' => [
            'data-access' => 'Data Access & CQRS',
            'services' => 'Services',
            'validation' => 'Validation',
        ],
        'Infrastructure' => [
            'error-handling' => 'Error Handling',
            'logging' => 'Logging',
            'events' => 'Events',
            'caching' => 'Caching',
            'sessions' => 'Sessions',
            'console' => 'Console',
        ],
        'Frontend' => [
            'views' => 'Views & Templates',
        ],
        'Security' => [
            'security' => 'Security & Authentication',
        ],
    ];

    private function getAllPages(): array
    {
        $pages = [];
        foreach (self::SECTIONS as $pages_in_section) {
            foreach ($pages_in_section as $slug => $title) {
                $pages[$slug] = $title;
            }
        }
        return $pages;
    }

    public function index(): Response
    {
        $this->viewBag->title = 'Documentation — Melodic PHP Framework';
        $this->viewBag->sections = self::SECTIONS;
        $this->viewBag->pages = $this->getAllPages();
        $this->viewBag->currentPage = null;
        $this->viewBag->sidebarType = 'docs';
        $this->setLayout('layouts/docs');

        return $this->view('docs/index');
    }

    public function show(string $page): Response
    {
        $pages = $this->getAllPages();

        if (!isset($pages[$page])) {
            return $this->notFound(['error' => 'Documentation page not found']);
        }

        $this->viewBag->title = $pages[$page] . ' — Melodic PHP Framework';
        $this->viewBag->sections = self::SECTIONS;
        $this->viewBag->pages = $pages;
        $this->viewBag->currentPage = $page;
        $this->viewBag->pageTitle = $pages[$page];
        $this->viewBag->sidebarType = 'docs';
        $this->setLayout('layouts/docs');

        return $this->view('docs/' . $page);
    }
}
