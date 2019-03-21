<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Routing\Loader;

use Symfony\Bundle\FrameworkBundle\Controller\RedirectController;
use Symfony\Bundle\FrameworkBundle\Controller\TemplateController;
use Symfony\Bundle\FrameworkBundle\Tests\TestCase;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\FileLocatorInterface;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

abstract class AbstractLoaderTest extends TestCase
{
    /** @var LoaderInterface */
    protected $loader;

    abstract protected function getLoader(): LoaderInterface;

    abstract protected function getType(): string;

    protected function setUp(): void
    {
        $this->loader = $this->getLoader();
    }

    protected function tearDown(): void
    {
        $this->loader = null;
    }

    public function getLocator(): FileLocatorInterface
    {
        return new FileLocator([__DIR__.'/../../Fixtures/Resources/config/routing']);
    }

    public function testRoutesAreLoaded()
    {
        $routeCollection = $this->loader->load('routes.'.$this->getType());

        $expectedCollection = new RouteCollection();

        $expectedCollection->add('classic_route', (new Route('/classic')));

        $expectedCollection->add('template_route', (new Route('/static'))
            ->setDefaults([
                '_controller' => TemplateController::class,
                'context' => ['foo' => 'bar'],
                'template' => 'static.html.twig',
                'maxAge' => 300,
                'sharedAge' => 100,
                'private' => true,
            ])
            ->setMethods(['GET'])
            ->setOptions(['utf8' => true])
            ->setCondition('abc')
        );
        $expectedCollection->add('redirect_route', (new Route('/redirect'))
            ->setDefaults([
                '_controller' => RedirectController::class.'::redirectAction',
                'route' => 'target_route',
                'permanent' => true,
                'ignoreAttributes' => ['attr', 'ibutes'],
                'keepRequestMethod' => true,
                'keepQueryParams' => true,
            ])
            ->setSchemes(['http'])
            ->setHost('legacy')
            ->setOptions(['utf8' => true])
        );
        $expectedCollection->add('url_redirect_route', (new Route('/redirect-url'))
            ->setDefaults([
                '_controller' => RedirectController::class.'::urlRedirectAction',
                'path' => '/url-target',
                'permanent' => true,
                'scheme' => 'http',
                'httpPort' => 1,
                'keepRequestMethod' => true,
            ])
            ->setHost('legacy')
            ->setOptions(['utf8' => true])
        );
        $expectedCollection->add('not_a_route', (new Route('/not-a-path'))
            ->setDefaults([
                '_controller' => RedirectController::class.'::redirectAction',
                'route' => '',
            ])
            ->setHost('legacy')
            ->setOptions(['utf8' => true])
        );
        $expectedCollection->add('gone_route', (new Route('/gone-path'))
            ->setDefaults([
                '_controller' => RedirectController::class.'::redirectAction',
                'route' => '',
                'permanent' => true,
            ])
            ->setOptions(['utf8' => true])
        );
        $expectedCollection->addResource(new FileResource(realpath(
            __DIR__.'/../../Fixtures/Resources/config/routing/routes.'.$this->getType()
        )));

        $this->assertEquals($expectedCollection, $routeCollection);
    }
}
