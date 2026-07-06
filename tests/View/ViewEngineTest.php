<?php

declare(strict_types=1);

namespace Tests\View;

use Melodic\Cache\ArrayCache;
use Melodic\View\ViewEngine;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class ViewEngineTest extends TestCase
{
    private string $viewsPath;

    protected function setUp(): void
    {
        $this->viewsPath = sys_get_temp_dir() . '/melodic_view_test_' . uniqid();
        mkdir($this->viewsPath, 0777, true);

        file_put_contents(
            $this->viewsPath . '/hello.phtml',
            '<h1>Hello, <?= $name ?></h1>'
        );

        file_put_contents(
            $this->viewsPath . '/layout.phtml',
            '<html><?= $this->renderBody() ?></html>'
        );
    }

    protected function tearDown(): void
    {
        $files = glob($this->viewsPath . '/*.phtml');

        foreach ($files as $file) {
            unlink($file);
        }

        rmdir($this->viewsPath);
    }

    public function testRenderTemplate(): void
    {
        $engine = new ViewEngine($this->viewsPath);

        $result = $engine->render('hello', ['name' => 'World']);

        $this->assertSame('<h1>Hello, World</h1>', $result);
    }

    public function testRenderWithLayout(): void
    {
        $engine = new ViewEngine($this->viewsPath);

        $result = $engine->render('hello', ['name' => 'World'], 'layout');

        $this->assertSame('<html><h1>Hello, World</h1></html>', $result);
    }

    public function testRenderThrowsForMissingTemplate(): void
    {
        $engine = new ViewEngine($this->viewsPath);

        $this->expectException(RuntimeException::class);
        $engine->render('nonexistent');
    }

    public function testRenderCachedReturnsCachedOutputOnSecondCall(): void
    {
        $cache = new ArrayCache();
        $engine = new ViewEngine($this->viewsPath, $cache);

        $first = $engine->renderCached('hello', ['name' => 'World']);
        $this->assertSame('<h1>Hello, World</h1>', $first);

        // Overwrite the template file to prove the second call uses cache
        file_put_contents(
            $this->viewsPath . '/hello.phtml',
            '<h1>Changed</h1>'
        );

        $second = $engine->renderCached('hello', ['name' => 'World']);
        $this->assertSame('<h1>Hello, World</h1>', $second);
    }

    public function testRenderCachedFallsBackToRenderWithoutCache(): void
    {
        $engine = new ViewEngine($this->viewsPath);

        $result = $engine->renderCached('hello', ['name' => 'World']);

        $this->assertSame('<h1>Hello, World</h1>', $result);
    }

    public function testRenderCachedDifferentDataProducesDifferentKeys(): void
    {
        $cache = new ArrayCache();
        $engine = new ViewEngine($this->viewsPath, $cache);

        $first = $engine->renderCached('hello', ['name' => 'Alice']);
        $second = $engine->renderCached('hello', ['name' => 'Bob']);

        $this->assertSame('<h1>Hello, Alice</h1>', $first);
        $this->assertSame('<h1>Hello, Bob</h1>', $second);
    }

    public function testRenderCachedWithLayout(): void
    {
        $cache = new ArrayCache();
        $engine = new ViewEngine($this->viewsPath, $cache);

        $first = $engine->renderCached('hello', ['name' => 'World'], 'layout');
        $this->assertSame('<html><h1>Hello, World</h1></html>', $first);

        // Overwrite template to prove cache is used
        file_put_contents(
            $this->viewsPath . '/hello.phtml',
            '<h1>Changed</h1>'
        );

        $second = $engine->renderCached('hello', ['name' => 'World'], 'layout');
        $this->assertSame('<html><h1>Hello, World</h1></html>', $second);
    }

    public function testEscapeHelperEscapesHtml(): void
    {
        $engine = new ViewEngine($this->viewsPath);

        $this->assertSame('&lt;script&gt;alert(1)&lt;/script&gt;', $engine->e('<script>alert(1)</script>'));
        $this->assertSame('&quot;quoted&quot;', $engine->e('"quoted"'));
        $this->assertSame('', $engine->e(null));
    }

    public function testEscapeHelperIsUsableInTemplates(): void
    {
        file_put_contents(
            $this->viewsPath . '/escaped.phtml',
            '<p><?= $this->e($value) ?></p>'
        );

        $engine = new ViewEngine($this->viewsPath);
        $html = $engine->render('escaped', ['value' => '<b>x</b>']);

        $this->assertSame('<p>&lt;b&gt;x&lt;/b&gt;</p>', $html);
    }

    public function testNestedPartialRenderDoesNotCorruptParentSections(): void
    {
        // A partial that opens its own section, rendered from within the parent.
        file_put_contents(
            $this->viewsPath . '/partial.phtml',
            '<?php $this->beginSection("inner") ?>INNER<?php $this->endSection() ?>PARTIAL'
        );
        // Parent defines an "outer" section, then renders the partial inline.
        file_put_contents(
            $this->viewsPath . '/parent.phtml',
            '<?php $this->beginSection("outer") ?>OUTER<?php $this->endSection() ?>'
            . '[<?= $this->render("partial") ?>]'
        );
        // Layout pulls the parent's "outer" section back out.
        file_put_contents(
            $this->viewsPath . '/mainlayout.phtml',
            '<?= $this->renderBody() ?>|<?= $this->renderSection("outer") ?>'
        );

        $engine = new ViewEngine($this->viewsPath);
        $html = $engine->render('parent', [], 'mainlayout');

        // Parent's section survives the nested render; partial body is inlined.
        $this->assertSame('[PARTIAL]|OUTER', $html);
    }

    public function testExceptionMidSectionRestoresOutputBufferLevel(): void
    {
        file_put_contents(
            $this->viewsPath . '/exploding.phtml',
            '<?php $this->beginSection("scripts"); throw new RuntimeException("boom");'
        );

        $engine = new ViewEngine($this->viewsPath);
        $level = ob_get_level();

        try {
            $engine->render('exploding');
            $this->fail('Expected RuntimeException');
        } catch (RuntimeException $e) {
            $this->assertSame('boom', $e->getMessage());
        }

        // The template buffer AND the un-ended section buffer must both be
        // closed, or every failed render leaks an output buffer level.
        $this->assertSame($level, ob_get_level());
    }
}
