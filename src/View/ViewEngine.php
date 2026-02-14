<?php

declare(strict_types=1);

namespace Melodic\View;

use RuntimeException;

class ViewEngine
{
    private string $bodyContent = '';

    /** @var array<string, string> */
    private array $sections = [];

    private ?string $currentSection = null;

    public function __construct(
        private readonly string $viewsPath,
    ) {}

    public function render(string $template, array $data = [], ?string $layout = null): string
    {
        $templatePath = $this->viewsPath . '/' . $template . '.phtml';

        if (!file_exists($templatePath)) {
            throw new RuntimeException("View template not found: {$templatePath}");
        }

        $content = $this->renderTemplate($templatePath, $data);

        if ($layout !== null) {
            $this->bodyContent = $content;
            $layoutPath = $this->viewsPath . '/' . $layout . '.phtml';

            if (!file_exists($layoutPath)) {
                throw new RuntimeException("Layout template not found: {$layoutPath}");
            }

            $content = $this->renderTemplate($layoutPath, $data);
            $this->bodyContent = '';
        }

        return $content;
    }

    public function renderBody(): string
    {
        return $this->bodyContent;
    }

    public function renderSection(string $name): string
    {
        return $this->sections[$name] ?? '';
    }

    public function beginSection(string $name): void
    {
        $this->currentSection = $name;
        ob_start();
    }

    public function endSection(): void
    {
        if ($this->currentSection === null) {
            throw new RuntimeException('No section has been started.');
        }

        $this->sections[$this->currentSection] = ob_get_clean();
        $this->currentSection = null;
    }

    private function renderTemplate(string $path, array $data): string
    {
        extract($data);

        ob_start();

        try {
            include $path;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }

        return ob_get_clean();
    }
}
