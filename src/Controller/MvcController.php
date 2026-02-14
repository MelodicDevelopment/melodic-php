<?php

declare(strict_types=1);

namespace Melodic\Controller;

use Melodic\Http\Response;
use Melodic\Security\UserContextInterface;
use Melodic\View\ViewBag;
use Melodic\View\ViewEngine;

class MvcController extends Controller
{
    protected ViewBag $viewBag;
    protected ?string $layout = null;

    public function __construct(
        private readonly ViewEngine $viewEngine,
    ) {
        $this->viewBag = new ViewBag();
    }

    protected function view(string $template, array $data = []): Response
    {
        $data['viewBag'] = $this->viewBag;

        $html = $this->viewEngine->render($template, $data, $this->layout);

        return new Response(
            statusCode: 200,
            body: $html,
            headers: ['Content-Type' => 'text/html; charset=UTF-8'],
        );
    }

    public function viewBag(): ViewBag
    {
        return $this->viewBag;
    }

    public function setLayout(string $layout): void
    {
        $this->layout = $layout;
    }

    protected function getUserContext(): ?UserContextInterface
    {
        return $this->request->getAttribute('userContext');
    }
}
