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

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Routing\Router;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Bundle\WebProfilerBundle\Tests\Functional\WebProfilerBundleKernel;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Routing\Route;

class RouterControllerTest extends WebTestCase
{
    public function testFalseNegativeTrace()
    {
        $path = '/foo/bar:123/baz';

        $kernel = new WebProfilerBundleKernel();
        $client = new KernelBrowser($kernel);
        $client->disableReboot();
        $client->getKernel()->boot();

        /** @var Router $router */
        $router = $client->getContainer()->get('router');
        $router->getRouteCollection()->add('route1', new Route($path));

        $client->request('GET', $path);

        $crawler = $client->request('GET', '/_profiler/latest?panel=router&type=request');

        $matchedRouteCell = $crawler
            ->filter('#router-logs .status-success td')
            ->reduce(function (Crawler $td) use ($path): bool {
                return $td->text() === $path;
            });

        $this->assertSame(1, $matchedRouteCell->count());
    }
}
