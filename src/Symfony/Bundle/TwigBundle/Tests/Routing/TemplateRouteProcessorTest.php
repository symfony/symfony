<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\TwigBundle\Tests\Routing;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\TwigBundle\Controller\TemplateController;
use Symfony\Bundle\TwigBundle\Routing\TemplateRouteProcessor;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

class TemplateRouteProcessorTest extends TestCase
{
    public function testTemplateDefaultIsRenderedByTemplateController()
    {
        $routes = $this->process([
            'faq' => new Route('/faq.html', ['_template' => 'pages/faq.html.twig', 'maxAge' => 300]),
            'blog' => new Route('/blog', ['_controller' => 'App\Controller\BlogController::index']),
        ]);

        $this->assertSame([
            '_controller' => TemplateController::class.'::templateAction',
            'template' => 'pages/faq.html.twig',
            'maxAge' => 300,
        ], $routes->get('faq')->getDefaults());
        $this->assertSame(['_controller' => 'App\Controller\BlogController::index'], $routes->get('blog')->getDefaults());
    }

    public function testControllerWinsOverTemplateDefault()
    {
        $routes = $this->process([
            'faq' => new Route('/faq.html', ['_template' => 'pages/faq.html.twig', '_controller' => 'App\Controller\PageController::faq']),
        ]);

        $this->assertSame(['_template' => 'pages/faq.html.twig', '_controller' => 'App\Controller\PageController::faq'], $routes->get('faq')->getDefaults());
    }

    public function testTemplateDefaultConflictsWithTemplateArgument()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The route "faq" must not define both the "_template" default and the "template" default.');

        $this->process([
            'faq' => new Route('/faq.html', ['_template' => 'pages/faq.html.twig', 'template' => 'pages/other.html.twig']),
        ]);
    }

    public function testTemplateDefaultConflictsWithTemplatePlaceholder()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The route "page" must not define both the "_template" default and a "{template}" placeholder.');

        $this->process([
            'page' => new Route('/pages/{template}', ['_template' => 'pages/faq.html.twig']),
        ]);
    }

    /**
     * @param array<string, Route> $routes
     */
    private function process(array $routes): RouteCollection
    {
        $collection = new RouteCollection();
        foreach ($routes as $name => $route) {
            $collection->add($name, $route);
        }

        (new TemplateRouteProcessor())($collection);

        return $collection;
    }
}
