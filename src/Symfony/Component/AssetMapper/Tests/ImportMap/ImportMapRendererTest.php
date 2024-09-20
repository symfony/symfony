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
use Symfony\Component\AssetMapper\ImportMap\ImportMapGenerator;
use Symfony\Component\AssetMapper\ImportMap\ImportMapRenderer;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\WebLink\GenericLinkProvider;

class ImportMapRendererTest extends TestCase
{
    public function testBasicRender()
    {
        $importMapGenerator = $this->createMock(ImportMapGenerator::class);
        $importMapGenerator->expects($this->once())
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
                'es-module-shim' => [
                    'path' => 'https://ga.jspm.io/npm:es-module-shims',
                    'type' => 'js',
                ],
                '/assets/implicitly-added' => [
                    'path' => '/assets/implicitly-added-d1g35t.js',
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

        $renderer = new ImportMapRenderer($importMapGenerator, $assetPackages, polyfillImportName: 'es-module-shim');
        $html = $renderer->render(['app']);

        $this->assertStringContainsString('<script type="importmap">', $html);
        // polyfill is rendered as a normal script tag
        $this->assertStringContainsString("script.src = 'https://ga.jspm.io/npm:es-module-shims';", $html);
        // and is hidden from the import map
        $this->assertStringNotContainsString('"es-module-shim"', $html);
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
        $this->assertStringContainsString('"app_css_no_preload": "data:application/javascript,document.head.appendChild%28Object.assign%28document.createElement%28%22link%22%29%2C%7Brel%3A%22stylesheet%22%2Chref%3A%22%2Fsubdirectory%2Fassets%2Fstyles%2Fapp-nopreload-d1g35t.css%22%7D', $html);
        $this->assertStringNotContainsString('<link rel="stylesheet" href="/subdirectory/assets/styles/app-nopreload-d1g35t.css">', $html);
        // remote js
        $this->assertStringContainsString('"remote_js": "https://cdn.example.com/assets/remote-d1g35t.js"', $html);
        // both the key and value are prefixed with the subdirectory
        $this->assertStringContainsString('"/subdirectory/assets/implicitly-added": "/subdirectory/assets/implicitly-added-d1g35t.js"', $html);
    }

    public function testNoPolyfill()
    {
        $renderer = new ImportMapRenderer($this->createBasicImportMapGenerator(), null, 'UTF-8', false);
        $this->assertStringNotContainsString('https://ga.jspm.io/npm:es-module-shims', $renderer->render([]));
    }

    public function testDefaultPolyfillUsedIfNotInImportmap()
    {
        $importMapGenerator = $this->createMock(ImportMapGenerator::class);
        $importMapGenerator->expects($this->once())
            ->method('getImportMapData')
            ->with(['app'])
            ->willReturn([]);

        $renderer = new ImportMapRenderer(
            $importMapGenerator,
            $this->createMock(Packages::class),
            polyfillImportName: 'es-module-shims',
        );
        $html = $renderer->render(['app']);
        $this->assertStringContainsString("script.src = 'https://ga.jspm.io/npm:es-module-shims@", $html);
        $this->assertStringContainsString("script.setAttribute('crossorigin', 'anonymous');\n    script.setAttribute('integrity', 'sha384-", $html);
    }

    public function testCustomScriptAttributes()
    {
        $renderer = new ImportMapRenderer($this->createBasicImportMapGenerator(), null, 'UTF-8', 'es-module-shims', [
            'something' => true,
            'data-turbo-track' => 'reload',
        ]);
        $html = $renderer->render([]);
        $this->assertStringContainsString('<script type="importmap" something data-turbo-track="reload">', $html);
        $this->assertStringContainsString('<script async something data-turbo-track="reload">', $html);
        $this->assertStringContainsString(<<<EOTXT
            script.src = 'https://polyfillUrl.example';
            script.setAttribute('async', 'async');
            script.setAttribute('something', 'something');
            script.setAttribute('data-turbo-track', 'reload');
        EOTXT, $html);
    }

    public function testWithEntrypoint()
    {
        $renderer = new ImportMapRenderer($this->createBasicImportMapGenerator());
        $this->assertStringContainsString("<script type=\"module\">import 'application';</script>", $renderer->render('application'));

        $renderer = new ImportMapRenderer($this->createBasicImportMapGenerator());
        $this->assertStringContainsString("<script type=\"module\">import 'application\'s';</script>", $renderer->render("application's"));

        $renderer = new ImportMapRenderer($this->createBasicImportMapGenerator());
        $html = $renderer->render(['foo', 'bar']);
        $this->assertStringContainsString("import 'foo';", $html);
        $this->assertStringContainsString("import 'bar';", $html);
    }

    private function createBasicImportMapGenerator(): ImportMapGenerator
    {
        $importMapGenerator = $this->createMock(ImportMapGenerator::class);
        $importMapGenerator->expects($this->once())
            ->method('getImportMapData')
            ->willReturn([
                'app' => [
                    'path' => 'app.js',
                    'type' => 'js',
                ],
                'es-module-shims' => [
                    'path' => 'https://polyfillUrl.example',
                    'type' => 'js',
                ],
            ])
        ;

        return $importMapGenerator;
    }

    public function testItAddsPreloadLinks()
    {
        $importMapGenerator = $this->createMock(ImportMapGenerator::class);
        $importMapGenerator->expects($this->once())
            ->method('getImportMapData')
            ->willReturn([
                'app_js_preload' => [
                    'path' => '/assets/app-preload-d1g35t.js',
                    'type' => 'js',
                    'preload' => true,
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
            ]);

        $request = Request::create('/foo');
        $requestStack = new RequestStack();
        $requestStack->push($request);

        $renderer = new ImportMapRenderer($importMapGenerator, requestStack: $requestStack);
        $renderer->render(['app']);

        $linkProvider = $request->attributes->get('_links');
        $this->assertInstanceOf(GenericLinkProvider::class, $linkProvider);
        $this->assertCount(1, $linkProvider->getLinks());
        $this->assertSame(['preload'], $linkProvider->getLinks()[0]->getRels());
        $this->assertSame(['as' => 'style'], $linkProvider->getLinks()[0]->getAttributes());
        $this->assertSame('/assets/styles/app-preload-d1g35t.css', $linkProvider->getLinks()[0]->getHref());
    }
}
