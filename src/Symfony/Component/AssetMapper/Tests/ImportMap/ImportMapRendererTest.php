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
use Symfony\Component\Asset\Packages;
use Symfony\Component\AssetMapper\ImportMap\ImportMapManager;
use Symfony\Component\AssetMapper\ImportMap\ImportMapRenderer;

class ImportMapRendererTest extends TestCase
{
    public function testBasicRender()
    {
        $importMapManager = $this->createMock(ImportMapManager::class);
        $importMapManager->expects($this->once())
            ->method('getImportMapData')
            ->with(['app'])
            ->willReturn([
                'app_js_preload' => [
                    'path' => '/assets/app-preload-d1g35t.js',
                    'type' => 'js',
                    'preload' => true,
                ],
                'app_js_no_preload' => [
                    'path' => '/assets/app-nopreload-d1g35t.js',
                    'type' => 'js',
                ],
                'app_css_preload' => [
                    'path' => '/assets/styles/app-preload-d1g35t.css',
                    'type' => 'css',
                    'preload' => true,
                ],
                'app_css_no_preload' => [
                    'path' => '/assets/styles/app-nopreload-d1g35t.css',
                    'type' => 'css',
                ],
                'remote_js' => [
                    'path' => 'https://cdn.example.com/assets/remote-d1g35t.js',
                    'type' => 'js',
                ],
            ]);

        $assetPackages = $this->createMock(Packages::class);
        $assetPackages->expects($this->any())
            ->method('getUrl')
            ->willReturnCallback(function ($path) {
                // try to imitate the behavior of the real service
                if (str_starts_with($path, 'http') || str_starts_with($path, '/')) {
                    return $path;
                }

                return '/subdirectory/'.$path;
            });

        $renderer = new ImportMapRenderer($importMapManager, $assetPackages);
        $html = $renderer->render(['app']);

        $this->assertStringContainsString('<script type="importmap">', $html);
        $this->assertStringContainsString('https://ga.jspm.io/npm:es-module-shims', $html);
        $this->assertStringContainsString('import \'app\';', $html);

        // preloaded js file
        $this->assertStringContainsString('"app_js_preload": "/subdirectory/assets/app-preload-d1g35t.js",', $html);
        $this->assertStringContainsString('<link rel="modulepreload" href="/subdirectory/assets/app-preload-d1g35t.js">', $html);
        // non-preloaded js file
        $this->assertStringContainsString('"app_js_no_preload": "/subdirectory/assets/app-nopreload-d1g35t.js",', $html);
        $this->assertStringNotContainsString('<link rel="modulepreload" href="/assets/subdirectory/app-nopreload-d1g35t.js">', $html);
        // preloaded css file
        $this->assertStringContainsString('"app_css_preload": "data:application/javascript,', $html);
        $this->assertStringContainsString('<link rel="stylesheet" href="/subdirectory/assets/styles/app-preload-d1g35t.css">', $html);
        // non-preloaded CSS file
        $this->assertStringContainsString('"app_css_no_preload": "data:application/javascript,const%20d%3Ddocument%2Cl%3Dd.createElement%28%22link%22%29%3Bl.rel%3D%22stylesheet%22%2Cl.href%3D%22%2Fsubdirectory%2Fassets%2Fstyles%2Fapp-nopreload-d1g35t.css%22%2C%28d.head%7C%7Cd.getElementsByTagName%28%22head%22%29%5B0%5D%29.appendChild%28l%29', $html);
        $this->assertStringNotContainsString('<link rel="stylesheet" href="/subdirectory/assets/styles/app-nopreload-d1g35t.css">', $html);
        // remote js
        $this->assertStringContainsString('"remote_js": "https://cdn.example.com/assets/remote-d1g35t.js"', $html);
    }

    public function testNoPolyfill()
    {
        $renderer = new ImportMapRenderer($this->createBasicImportMapManager(), null, 'UTF-8', false);
        $this->assertStringNotContainsString('https://ga.jspm.io/npm:es-module-shims', $renderer->render([]));
    }

    public function testCustomScriptAttributes()
    {
        $renderer = new ImportMapRenderer($this->createBasicImportMapManager(), null, 'UTF-8', 'https://polyfillUrl.example', [
            'something' => true,
            'data-turbo-track' => 'reload',
        ]);
        $html = $renderer->render([]);
        $this->assertStringContainsString('<script type="importmap" something data-turbo-track="reload">', $html);
        $this->assertStringContainsString('<script async src="https://polyfillUrl.example" something data-turbo-track="reload"></script>', $html);
    }

    public function testWithEntrypoint()
    {
        $renderer = new ImportMapRenderer($this->createBasicImportMapManager());
        $this->assertStringContainsString("<script type=\"module\">import 'application';</script>", $renderer->render('application'));

        $renderer = new ImportMapRenderer($this->createBasicImportMapManager());
        $this->assertStringContainsString("<script type=\"module\">import 'application\'s';</script>", $renderer->render("application's"));

        $renderer = new ImportMapRenderer($this->createBasicImportMapManager());
        $html = $renderer->render(['foo', 'bar']);
        $this->assertStringContainsString("import 'foo';", $html);
        $this->assertStringContainsString("import 'bar';", $html);
    }

    private function createBasicImportMapManager(): ImportMapManager
    {
        $importMapManager = $this->createMock(ImportMapManager::class);
        $importMapManager->expects($this->once())
            ->method('getImportMapData')
            ->willReturn([
                'app' => [
                    'path' => 'app.js',
                    'type' => 'js',
                ],
            ])
        ;

        return $importMapManager;
    }
}
