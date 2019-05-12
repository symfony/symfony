<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\Tests\Functional;

class RouteParamsMatcherTest extends AbstractWebTestCase
{
    public function testRouteMatches()
    {
        $client = $this->createClient(['test_case' => 'RouteParamsMatcher', 'root_config' => 'config.yml']);

        $client->request('GET', '/');
        $this->assertSame(401, $client->getResponse()->getStatusCode());
    }

    public function testRouteDoesNotMatch()
    {
        $this->expectException(\Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class);

        $client = $this->createClient(['test_case' => 'RouteParamsMatcher', 'root_config' => 'config.yml']);

        $client->request('GET', '/open');
    }
}
