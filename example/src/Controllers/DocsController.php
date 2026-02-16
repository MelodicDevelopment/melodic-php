<?php

declare(strict_types=1);

namespace Example\Controllers;

use Melodic\Controller\MvcController;
use Melodic\Http\Response;

class DocsController extends MvcController
{
	private const PAGES = [
		'getting-started' => 'Getting Started',
		'configuration' => 'Configuration',
		'routing' => 'Routing',
		'controllers' => 'Controllers',
		'dependency-injection' => 'Dependency Injection',
		'middleware' => 'Middleware',
		'data-access' => 'Data Access & CQRS',
		'services' => 'Services',
		'security' => 'Security & Authentication',
		'views' => 'Views & Templates',
	];

	public function index(): Response
	{
		$this->viewBag->title = 'Documentation';
		$this->viewBag->userContext = $this->getUserContext();
		$this->viewBag->pages = self::PAGES;
		$this->viewBag->currentPage = null;
		$this->setLayout('layouts/docs');

		return $this->view('docs/index');
	}

	public function show(string $page): Response
	{
		if (!isset(self::PAGES[$page])) {
			return $this->notFound(['error' => 'Documentation page not found']);
		}

		$this->viewBag->title = self::PAGES[$page] . ' - Docs';
		$this->viewBag->userContext = $this->getUserContext();
		$this->viewBag->pages = self::PAGES;
		$this->viewBag->currentPage = $page;
		$this->viewBag->pageTitle = self::PAGES[$page];
		$this->setLayout('layouts/docs');

		return $this->view('docs/' . $page);
	}
}
