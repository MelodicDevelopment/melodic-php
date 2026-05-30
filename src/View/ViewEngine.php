<?php

declare(strict_types=1);

namespace Melodic\View;

use Melodic\Cache\CacheInterface;
use RuntimeException;

class ViewEngine
{
    private string $bodyContent = '';

    /** @var array<string, string> */
    private array $sections = [];

    private ?string $currentSection = null;

    public function __construct(
        private readonly string $viewsPath,
        private readonly ?CacheInterface $cache = null,
    ) {}

    /** @param array<string, mixed> $data */
    public function render(string $template, array $data = [], ?string $layout = null): string
    {
        // Snapshot render state so a nested render() (e.g. a partial rendered from
        // within a template) can't clobber the parent's body/sections. Restored
        // in finally so state is always returned to the caller's context.
        $previousBody = $this->bodyContent;
        $previousSections = $this->sections;
        $previousCurrentSection = $this->currentSection;

        $this->bodyContent = '';
        $this->sections = [];
        $this->currentSection = null;

        try {
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
            }

            return $content;
        } finally {
            $this->bodyContent = $previousBody;
            $this->sections = $previousSections;
            $this->currentSection = $previousCurrentSection;
        }
    }

    /**
     * Escape a value for safe HTML output. Melodic does NOT auto-escape template
     * output, so use this in .phtml as `<?= $this->e($value) ?>` for any
     * user-supplied data to prevent XSS.
     */
    public function e(?string $value): string
    {
        return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
    }

    /** @param array<string, mixed> $data */
    public function renderCached(string $template, array $data = [], ?string $layout = null, int $ttl = 3600): string
    {
        if ($this->cache === null) {
            return $this->render($template, $data, $layout);
        }

        $cacheKey = 'view:' . $template . ':' . ($layout ?? '') . ':' . md5(serialize($data));

        $cached = $this->cache->get($cacheKey);

        if ($cached !== null) {
            return $cached;
        }

        $content = $this->render($template, $data, $layout);
        $this->cache->set($cacheKey, $content, $ttl);

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

    /** @param array<string, mixed> $data */
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
