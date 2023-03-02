<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\WebProfilerBundle\Tests\Controller;

use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Bundle\WebProfilerBundle\Controller\ProfilerController;
use Symfony\Bundle\WebProfilerBundle\Csp\ContentSecurityPolicyHandler;
use Symfony\Bundle\WebProfilerBundle\Csp\NonceGenerator;
use Symfony\Bundle\WebProfilerBundle\Tests\Functional\WebProfilerBundleKernel;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DumpDataCollector;
use Symfony\Component\HttpKernel\DataCollector\ExceptionDataCollector;
use Symfony\Component\HttpKernel\DataCollector\RequestDataCollector;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Profiler\Profile;
use Symfony\Component\HttpKernel\Profiler\Profiler;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;
use Twig\Loader\LoaderInterface;
use Twig\Loader\SourceContextLoaderInterface;

class ProfilerControllerTest extends WebTestCase
{
    public function testHomeActionWithProfilerDisabled()
    {
        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('The profiler must be enabled.');

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $twig = $this->createMock(Environment::class);

        $controller = new ProfilerController($urlGenerator, null, $twig, []);
        $controller->homeAction();
    }

    public function testHomeActionRedirect()
    {
        $kernel = new WebProfilerBundleKernel();
        $client = new KernelBrowser($kernel);

        $client->request('GET', '/_profiler/');

        $this->assertSame(302, $client->getResponse()->getStatusCode());
        $this->assertSame('/_profiler/empty/search/results?limit=10', $client->getResponse()->getTargetUrl());
    }

    public function testPanelActionWithLatestTokenWhenNoTokensExist()
    {
        $kernel = new WebProfilerBundleKernel();
        $client = new KernelBrowser($kernel);

        $client->request('GET', '/_profiler/latest');

        $this->assertStringContainsString('No profiles found.', $client->getResponse()->getContent());
    }

    public function testPanelActionWithLatestToken()
    {
        $kernel = new WebProfilerBundleKernel();
        $client = new KernelBrowser($kernel);

        $client->request('GET', '/');
        $client->request('GET', '/_profiler/latest');

        $this->assertStringContainsString('kernel::homepageController', $client->getResponse()->getContent());
    }

    public function testPanelActionWithoutValidToken()
    {
        $kernel = new WebProfilerBundleKernel();
        $client = new KernelBrowser($kernel);

        $client->request('GET', '/_profiler/this-token-does-not-exist');

        $this->assertStringContainsString('Token &quot;this-token-does-not-exist&quot; not found.', $client->getResponse()->getContent());
    }

    public function testPanelActionWithWrongPanel()
    {
        $kernel = new WebProfilerBundleKernel();
        $client = new KernelBrowser($kernel);

        $client->request('GET', '/');
        $client->request('GET', '/_profiler/latest?panel=this-panel-does-not-exist');

        $this->assertSame(404, $client->getResponse()->getStatusCode());
    }

    public function testPanelActionWithValidPanelAndToken()
    {
        $kernel = new WebProfilerBundleKernel();
        $client = new KernelBrowser($kernel);

        $client->request('GET', '/');
        $crawler = $client->request('GET', '/_profiler/latest?panel=router');

        $this->assertSame('_', $crawler->filter('.metrics .metric .value')->eq(0)->text());
    }

    public function testToolbarActionWithProfilerDisabled()
    {
        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('The profiler must be enabled.');

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $twig = $this->createMock(Environment::class);

        $controller = new ProfilerController($urlGenerator, null, $twig, []);
        $controller->toolbarAction(Request::create('/_wdt/foo-token'), null);
    }

    /**
     * @dataProvider getEmptyTokenCases
     */
    public function testToolbarActionWithEmptyToken($token)
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $twig = $this->createMock(Environment::class);
        $profiler = $this->createMock(Profiler::class);

        $controller = new ProfilerController($urlGenerator, $profiler, $twig, []);

        $response = $controller->toolbarAction(Request::create('/_wdt/empty'), $token);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public static function getEmptyTokenCases()
    {
        return [
            [null],
            // "empty" is also a valid empty token case, see https://github.com/symfony/symfony/issues/10806
            ['empty'],
        ];
    }

    /**
     * @dataProvider getOpenFileCases
     */
    public function testOpeningDisallowedPaths($path, $isAllowed)
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $twig = $this->createMock(Environment::class);
        $profiler = $this->createMock(Profiler::class);

        $controller = new ProfilerController($urlGenerator, $profiler, $twig, [], null, __DIR__.'/../..');

        try {
            $response = $controller->openAction(Request::create('/_wdt/open', Request::METHOD_GET, ['file' => $path]));
            $this->assertEquals(200, $response->getStatusCode());
            $this->assertTrue($isAllowed);
        } catch (NotFoundHttpException $e) {
            $this->assertFalse($isAllowed);
        }
    }

    public static function getOpenFileCases()
    {
        return [
            ['README.md', true],
            ['composer.json', true],
            ['Controller/ProfilerController.php', true],
            ['.gitignore', false],
            ['../TwigBundle/README.md', false],
            ['Controller/../README.md', false],
            ['Controller/./ProfilerController.php', false],
        ];
    }

    /**
     * @dataProvider provideCspVariants
     */
    public function testReturns404onTokenNotFound($withCsp)
    {
        $twig = $this->createMock(Environment::class);
        $profiler = $this->createMock(Profiler::class);

        $profiler
            ->expects($this->exactly(2))
            ->method('loadProfile')
            ->willReturnCallback(fn ($token) => 'found' == $token ? new Profile($token) : null)
        ;

        $controller = $this->createController($profiler, $twig, $withCsp);

        $response = $controller->toolbarAction(Request::create('/_wdt/found'), 'found');
        $this->assertEquals(200, $response->getStatusCode());

        $response = $controller->toolbarAction(Request::create('/_wdt/notFound'), 'notFound');
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testSearchBarActionWithProfilerDisabled()
    {
        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('The profiler must be enabled.');

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $twig = $this->createMock(Environment::class);

        $controller = new ProfilerController($urlGenerator, null, $twig, []);
        $controller->searchBarAction(Request::create('/_profiler/search_bar'));
    }

    public function testSearchBarActionDefaultPage()
    {
        $kernel = new WebProfilerBundleKernel();
        $client = new KernelBrowser($kernel);

        $crawler = $client->request('GET', '/_profiler/search_bar');

        $this->assertSame(200, $client->getResponse()->getStatusCode());

        foreach (['ip', 'status_code', 'url', 'token', 'start', 'end'] as $searchCriteria) {
            $this->assertSame('', $crawler->filter(sprintf('form input[name="%s"]', $searchCriteria))->text());
        }
    }

    /**
     * @dataProvider provideCspVariants
     */
    public function testSearchResultsAction($withCsp)
    {
        $twig = $this->createMock(Environment::class);
        $profiler = $this->createMock(Profiler::class);

        $controller = $this->createController($profiler, $twig, $withCsp);

        $tokens = [
            [
                'token' => 'token1',
                'ip' => '127.0.0.1',
                'method' => 'GET',
                'url' => 'http://example.com/',
                'time' => 0,
                'parent' => null,
                'status_code' => 200,
            ],
            [
                'token' => 'token2',
                'ip' => '127.0.0.1',
                'method' => 'GET',
                'url' => 'http://example.com/not_found',
                'time' => 0,
                'parent' => null,
                'status_code' => 404,
            ],
        ];
        $profiler
            ->expects($this->once())
            ->method('find')
            ->willReturn($tokens);

        $request = Request::create('/_profiler/empty/search/results', 'GET', [
            'limit' => 2,
            'ip' => '127.0.0.1',
            'method' => 'GET',
            'url' => 'http://example.com/',
        ]);

        $twig->expects($this->once())
            ->method('render')
            ->with($this->stringEndsWith('results.html.twig'), $this->equalTo([
                'token' => 'empty',
                'profile' => null,
                'tokens' => $tokens,
                'ip' => '127.0.0.1',
                'method' => 'GET',
                'status_code' => null,
                'url' => 'http://example.com/',
                'start' => null,
                'end' => null,
                'limit' => 2,
                'panel' => null,
                'request' => $request,
                'csp_script_nonce' => $withCsp ? 'dummy_nonce' : null,
                'csp_style_nonce' => $withCsp ? 'dummy_nonce' : null,
            ]));

        $response = $controller->searchResultsAction($request, 'empty');
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testSearchActionWithProfilerDisabled()
    {
        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('The profiler must be enabled.');

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $twig = $this->createMock(Environment::class);

        $controller = new ProfilerController($urlGenerator, null, $twig, []);
        $controller->searchBarAction(Request::create('/_profiler/search'));
    }

    public function testSearchActionWithToken()
    {
        $kernel = new WebProfilerBundleKernel();
        $client = new KernelBrowser($kernel);

        $client->request('GET', '/');
        $token = $client->getResponse()->headers->get('x-debug-token');
        $client->request('GET', '/_profiler/search?token='.$token);

        $this->assertSame(302, $client->getResponse()->getStatusCode());
        $this->assertSame('/_profiler/'.$token, $client->getResponse()->getTargetUrl());
    }

    public function testSearchActionWithoutToken()
    {
        $kernel = new WebProfilerBundleKernel();
        $client = new KernelBrowser($kernel);
        $client->followRedirects();

        $client->request('GET', '/');
        $token = $client->getResponse()->headers->get('x-debug-token');
        $client->request('GET', '/_profiler/search?ip=&method=GET&status_code=&url=&token=&start=&end=&limit=10');

        $this->assertStringContainsString('results found', $client->getResponse()->getContent());
        $this->assertStringContainsString(sprintf('<a href="/_profiler/%s">%s</a>', $token, $token), $client->getResponse()->getContent());
    }

    public function testPhpinfoActionWithProfilerDisabled()
    {
        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('The profiler must be enabled.');

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $twig = $this->createMock(Environment::class);

        $controller = new ProfilerController($urlGenerator, null, $twig, []);
        $controller->phpinfoAction(Request::create('/_profiler/phpinfo'));
    }

    public function testPhpinfoAction()
    {
        $kernel = new WebProfilerBundleKernel();
        $client = new KernelBrowser($kernel);

        $client->request('GET', '/_profiler/phpinfo');

        $this->assertStringContainsString('PHP License', $client->getResponse()->getContent());
    }

    public static function provideCspVariants()
    {
        return [
            [true],
            [false],
        ];
    }

    /**
     * @dataProvider defaultPanelProvider
     */
    public function testDefaultPanel(string $expectedPanel, Profile $profile)
    {
        $this->assertDefaultPanel($expectedPanel, $profile);
    }

    public static function defaultPanelProvider(): \Generator
    {
        // Test default behavior
        $profile = new Profile('xxxxxx');
        $profile->addCollector($requestDataCollector = new RequestDataCollector());
        yield [$requestDataCollector->getName(), $profile];

        // Test exception
        $profile = new Profile('xxxxxx');
        $profile->addCollector($exceptionDataCollector = new ExceptionDataCollector());
        $exceptionDataCollector->collect(new Request(), new Response(), new \DomainException());
        yield [$exceptionDataCollector->getName(), $profile];
    }

    private function createController($profiler, $twig, $withCSP, array $templates = []): ProfilerController
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);

        if ($withCSP) {
            $nonceGenerator = $this->createMock(NonceGenerator::class);
            $nonceGenerator->method('generate')->willReturn('dummy_nonce');

            return new ProfilerController($urlGenerator, $profiler, $twig, $templates, new ContentSecurityPolicyHandler($nonceGenerator));
        }

        return new ProfilerController($urlGenerator, $profiler, $twig, $templates);
    }

    public function testDumpPanelExceptionPriority()
    {
        $exceptionDataCollector = new ExceptionDataCollector();
        $exceptionDataCollector->collect(new Request(), new Response(), new \DomainException());

        $dumpDataCollector = $this->createDumpDataCollector();

        $profile = new Profile('xxxxxx');
        $profile->setCollectors([$exceptionDataCollector, $dumpDataCollector]);

        $this->assertDefaultPanel($exceptionDataCollector->getName(), $profile);
    }

    public function testDumpPanelWhenDefinedAfterwards()
    {
        $exceptionDataCollector = new ExceptionDataCollector();
        $exceptionDataCollector->collect(new Request(), new Response(), new \DomainException());

        $dumpDataCollector = $this->createDumpDataCollector();
        $dumpDataCollector
            ->expects($this->atLeastOnce())
            ->method('getDumpsCount')
            ->willReturn(1)
        ;

        $profile = new Profile('xxxxxx');
        $profile->setCollectors([$dumpDataCollector, $exceptionDataCollector]);

        $this->assertDefaultPanel($exceptionDataCollector->getName(), $profile);
    }

    public function testDumpPanel()
    {
        $dumpDataCollector = $this->createDumpDataCollector();
        $dumpDataCollector
            ->expects($this->atLeastOnce())
            ->method('getDumpsCount')
            ->willReturn(1)
        ;

        $profile = new Profile('xxxxxx');
        $profile->addCollector($dumpDataCollector);

        $this->assertDefaultPanel($dumpDataCollector->getName(), $profile);
    }

    private function createDumpDataCollector(): MockObject&DumpDataCollector
    {
        $dumpDataCollector = $this->createMock(DumpDataCollector::class);
        $dumpDataCollector
            ->expects($this->atLeastOnce())
            ->method('getName')
            ->willReturn('dump');

        return $dumpDataCollector;
    }

    private function assertDefaultPanel(string $expectedPanel, Profile $profile)
    {
        $profiler = $this->createMock(Profiler::class);
        $profiler
            ->expects($this->atLeastOnce())
            ->method('loadProfile')
            ->with($profile->getToken())
            ->willReturn($profile);

        $collectorsNames = array_keys($profile->getCollectors());

        $profiler
            ->expects($this->atLeastOnce())
            ->method('has')
            ->with($this->logicalXor(...$collectorsNames))
            ->willReturn(true);

        $expectedTemplate = 'expected_template.html.twig';

        if (Environment::MAJOR_VERSION > 1) {
            $loader = $this->createMock(LoaderInterface::class);
            $loader
                ->expects($this->atLeastOnce())
                ->method('exists')
                ->with($this->logicalXor($expectedTemplate, 'other_template.html.twig'))
                ->willReturn(true);
        } else {
            $loader = $this->createMock(SourceContextLoaderInterface::class);
        }

        $twig = $this->createMock(Environment::class);
        $twig
            ->expects($this->atLeastOnce())
            ->method('getLoader')
            ->willReturn($loader);
        $twig
            ->expects($this->once())
            ->method('render')
            ->with($expectedTemplate);

        $this
            ->createController($profiler, $twig, false, array_map(function (string $collectorName) use ($expectedPanel, $expectedTemplate): array {
                if ($collectorName === $expectedPanel) {
                    return [$expectedPanel, $expectedTemplate];
                }

                return [$collectorName, 'other_template.html.twig'];
            }, $collectorsNames))
            ->panelAction(new Request(), $profile->getToken());
    }
}
