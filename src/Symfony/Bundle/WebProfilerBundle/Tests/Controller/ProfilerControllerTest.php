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

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\WebProfilerBundle\Controller\ProfilerController;
use Symfony\Bundle\WebProfilerBundle\Csp\ContentSecurityPolicyHandler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DumpDataCollector;
use Symfony\Component\HttpKernel\DataCollector\ExceptionDataCollector;
use Symfony\Component\HttpKernel\DataCollector\RequestDataCollector;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Profiler\Profile;
use Symfony\Component\HttpKernel\Profiler\Profiler;
use Twig\Environment;
use Twig\Loader\LoaderInterface;
use Twig\Loader\SourceContextLoaderInterface;

class ProfilerControllerTest extends TestCase
{
    /**
     * @dataProvider getEmptyTokenCases
     */
    public function testEmptyToken($token)
    {
        $urlGenerator = $this->getMockBuilder('Symfony\Component\Routing\Generator\UrlGeneratorInterface')->getMock();
        $twig = $this->getMockBuilder('Twig\Environment')->disableOriginalConstructor()->getMock();
        $profiler = $this
            ->getMockBuilder('Symfony\Component\HttpKernel\Profiler\Profiler')
            ->disableOriginalConstructor()
            ->getMock();

        $controller = new ProfilerController($urlGenerator, $profiler, $twig, []);

        $response = $controller->toolbarAction(Request::create('/_wdt/empty'), $token);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function getEmptyTokenCases()
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
        $urlGenerator = $this->getMockBuilder('Symfony\Component\Routing\Generator\UrlGeneratorInterface')->getMock();
        $twig = $this->getMockBuilder('Twig\Environment')->disableOriginalConstructor()->getMock();
        $profiler = $this
            ->getMockBuilder('Symfony\Component\HttpKernel\Profiler\Profiler')
            ->disableOriginalConstructor()
            ->getMock();

        $controller = new ProfilerController($urlGenerator, $profiler, $twig, [], null, __DIR__.'/../..');

        try {
            $response = $controller->openAction(Request::create('/_wdt/open', Request::METHOD_GET, ['file' => $path]));
            $this->assertEquals(200, $response->getStatusCode());
            $this->assertTrue($isAllowed);
        } catch (NotFoundHttpException $e) {
            $this->assertFalse($isAllowed);
        }
    }

    public function getOpenFileCases()
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
        $twig = $this->getMockBuilder('Twig\Environment')->disableOriginalConstructor()->getMock();
        $profiler = $this
            ->getMockBuilder('Symfony\Component\HttpKernel\Profiler\Profiler')
            ->disableOriginalConstructor()
            ->getMock();

        $profiler
            ->expects($this->exactly(2))
            ->method('loadProfile')
            ->willReturnCallback(function ($token) {
                return 'found' == $token ? new Profile($token) : null;
            })
        ;

        $controller = $this->createController($profiler, $twig, $withCsp);

        $response = $controller->toolbarAction(Request::create('/_wdt/found'), 'found');
        $this->assertEquals(200, $response->getStatusCode());

        $response = $controller->toolbarAction(Request::create('/_wdt/notFound'), 'notFound');
        $this->assertEquals(404, $response->getStatusCode());
    }

    /**
     * @dataProvider provideCspVariants
     */
    public function testSearchResult($withCsp)
    {
        $twig = $this->getMockBuilder('Twig\Environment')->disableOriginalConstructor()->getMock();
        $profiler = $this
            ->getMockBuilder('Symfony\Component\HttpKernel\Profiler\Profiler')
            ->disableOriginalConstructor()
            ->getMock();

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
            ]));

        $response = $controller->searchResultsAction($request, 'empty');
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function provideCspVariants()
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
        $profiler = $this->createMock(Profiler::class);
        $profiler
            ->expects($this->atLeastOnce())
            ->method('loadProfile')
            ->with($profile->getToken())
            ->willReturn($profile);

        $profiler
            ->expects($this->atLeastOnce())
            ->method('has')
            ->with($this->logicalXor($collectorsNames = array_keys($profile->getCollectors())))
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

    public function defaultPanelProvider(): \Generator
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

        // Test exception priority
        $dumpDataCollector = $this->createMock(DumpDataCollector::class);
        $dumpDataCollector
            ->expects($this->atLeastOnce())
            ->method('getName')
            ->willReturn('dump');
        $dumpDataCollector
            ->expects($this->atLeastOnce())
            ->method('getDumpsCount')
            ->willReturn(1);
        $profile = new Profile('xxxxxx');
        $profile->setCollectors([$exceptionDataCollector, $dumpDataCollector]);
        yield [$exceptionDataCollector->getName(), $profile];

        // Test exception priority when defined afterwards
        $profile = new Profile('xxxxxx');
        $profile->setCollectors([$dumpDataCollector, $exceptionDataCollector]);
        yield [$exceptionDataCollector->getName(), $profile];

        // Test dump
        $profile = new Profile('xxxxxx');
        $profile->addCollector($dumpDataCollector);
        yield [$dumpDataCollector->getName(), $profile];
    }

    private function createController($profiler, $twig, $withCSP, array $templates = []): ProfilerController
    {
        $urlGenerator = $this->getMockBuilder('Symfony\Component\Routing\Generator\UrlGeneratorInterface')->getMock();

        if ($withCSP) {
            $nonceGenerator = $this->getMockBuilder('Symfony\Bundle\WebProfilerBundle\Csp\NonceGenerator')->getMock();
            $nonceGenerator->method('generate')->willReturn('dummy_nonce');

            return new ProfilerController($urlGenerator, $profiler, $twig, $templates, new ContentSecurityPolicyHandler($nonceGenerator));
        }

        return new ProfilerController($urlGenerator, $profiler, $twig, $templates);
    }
}
