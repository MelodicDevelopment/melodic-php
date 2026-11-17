<?php

declare(strict_types=1);

namespace Melodic\Controller;

use Melodic\Http\RedirectResponse;
use Melodic\Http\Response;
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

    /** @param array<string, mixed> $data */
    protected function view(string $template, array $data = []): Response
    {
        $data['viewBag'] = $this->viewBag;

        // With CsrfMiddleware active, templates get `$csrfToken` and a ready
        // hidden input as `$csrfField` (never overriding caller-supplied data).
        $csrfToken = isset($this->request) ? $this->csrfToken() : null;

        if ($csrfToken !== null) {
            $data['csrfToken'] ??= $csrfToken;
            $data['csrfField'] ??= '<input type="hidden" name="csrf_token" value="'
                . htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') . '">';
        }

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

    /** The CSRF token for this request (set by CsrfMiddleware), if any. */
    protected function csrfToken(): ?string
    {
        $token = $this->request->getAttribute('csrfToken');

        return is_string($token) ? $token : null;
    }

    protected function redirect(string $url, int $statusCode = 302): RedirectResponse
    {
        return new RedirectResponse($url, $statusCode);
    }
}
