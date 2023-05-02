<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\AssetMapper\Tests\ImportMap;

use PHPUnit\Framework\TestCase;
use Symfony\Component\AssetMapper\ImportMap\ImportMapManager;
use Symfony\Component\AssetMapper\ImportMap\ImportMapRenderer;

class ImportMapRendererTest extends TestCase
{
    public function testBasicRenderNoEntry()
    {
        $renderer = new ImportMapRenderer($this->createImportMapManager());
        $html = $renderer->render();
        $this->assertStringContainsString(<<<EOF
            <script type="importmap">
            {"imports":{}}
            </script>
            EOF,
            $html
        );
        $this->assertStringContainsString('<script async src="https://ga.jspm.io/npm:es-module-shims', $html);
    }

    public function testNoPolyfill()
    {
        $renderer = new ImportMapRenderer($this->createImportMapManager(), 'UTF-8', false);
        $this->assertStringNotContainsString('https://ga.jspm.io/npm:es-module-shims', $renderer->render());
    }

    public function testCustomScriptAttributes()
    {
        $renderer = new ImportMapRenderer($this->createImportMapManager(), 'UTF-8', 'https://polyfillUrl.example', [
            'something' => true,
            'data-turbo-track' => 'reload',
        ]);
        $html = $renderer->render();
        $this->assertStringContainsString('<script type="importmap" something data-turbo-track="reload">', $html);
        $this->assertStringContainsString('<script async src="https://polyfillUrl.example" something data-turbo-track="reload"></script>', $html);
    }

    public function testWithEntrypoint()
    {
        $renderer = new ImportMapRenderer($this->createImportMapManager());
        $this->assertStringContainsString("<script type=\"module\">import 'application';</script>", $renderer->render('application'));

        $renderer = new ImportMapRenderer($this->createImportMapManager());
        $this->assertStringContainsString("<script type=\"module\">import 'application\'s';</script>", $renderer->render("application's"));
    }

    public function testWithPreloads()
    {
        $renderer = new ImportMapRenderer($this->createImportMapManager([
            '/assets/application.js',
            'https://cdn.example.com/assets/foo.js',
        ]));
        $html = $renderer->render();
        $this->assertStringContainsString('<link rel="modulepreload" href="/assets/application.js">', $html);
        $this->assertStringContainsString('<link rel="modulepreload" href="https://cdn.example.com/assets/foo.js">', $html);
    }

    private function createImportMapManager(array $urlsToPreload = []): ImportMapManager
    {
        $importMapManager = $this->createMock(ImportMapManager::class);
        $importMapManager->expects($this->once())
            ->method('getImportMapJson')
            ->willReturn('{"imports":{}}');

        $importMapManager->expects($this->once())
            ->method('getModulesToPreload')
            ->willReturn($urlsToPreload);

        return $importMapManager;
    }
}
