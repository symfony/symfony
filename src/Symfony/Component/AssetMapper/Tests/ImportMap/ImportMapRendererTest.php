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

        $assetPackages = $this->createStub(Packages::class);
        $assetPackages
            ->method('getUrl')
            ->willReturnCallback(static function ($path) {
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
        $this->assertStringContainsString('"app_css_no_preload": "data:application/javascript,document.head.appendChild(Object.assign(document.createElement(\'link\'),{rel:\'stylesheet\',href:\'/subdirectory/assets/styles/app-nopreload-d1g35t.css\'}))', $html);
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
            $this->createStub(Packages::class),
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
        $this->assertStringContainsString('<script something data-turbo-track="reload">', $html);
        $this->assertStringContainsString("script.src = 'https://polyfillUrl.example';", $html);
        $this->assertStringContainsString("script.setAttribute('something', 'something');", $html);
        $this->assertStringContainsString("script.setAttribute('data-turbo-track', 'reload');", $html);
    }

    public function testPolyfillBodyIsStableAcrossRequestsWithDifferentNonces()
    {
        // Two renders with distinct CSP nonces must produce byte-identical HTML except for the
        // literal `nonce="..."` attribute on the wrapper <script> tags. Anything else differing
        // means the per-request value leaked into the rendered body and would break Turbo's
        // <head> element signature check plus any body-keyed HTTP cache.
        $renderer1 = new ImportMapRenderer($this->createBasicImportMapGenerator(), null, 'UTF-8', 'es-module-shims');
        $renderer2 = new ImportMapRenderer($this->createBasicImportMapGenerator(), null, 'UTF-8', 'es-module-shims');

        $html1 = $renderer1->render([], ['nonce' => 'aaaaaaaa']);
        $html2 = $renderer2->render([], ['nonce' => 'bbbbbbbb']);

        $stripWrapperNonce = static fn (string $html): string => preg_replace('/ nonce="[^"]*"/', '', $html);
        $this->assertSame($stripWrapperNonce($html1), $stripWrapperNonce($html2));

        // Sanity-check that the runtime propagation hook is in the rendered body, otherwise CSP
        // would block the dynamically-created polyfill <script> on strict policies without
        // 'strict-dynamic'.
        $this->assertStringContainsString('document.currentScript?.nonce', $html1);
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
