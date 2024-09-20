<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Functional;

class RoutingConditionServiceTest extends AbstractWebTestCase
{
    /**
     * @dataProvider provideRoutes
     */
    public function testCondition(int $code, string $path)
    {
        $client = static::createClient(['test_case' => 'RoutingConditionService']);

        $client->request('GET', $path);
        $this->assertSame($code, $client->getResponse()->getStatusCode());
    }

    public static function provideRoutes(): iterable
    {
        yield 'allowed by an autoconfigured service' => [
            200,
            '/allowed/manually-tagged',
        ];

        yield 'allowed by a manually tagged service' => [
            200,
            '/allowed/auto-configured',
        ];

        yield 'allowed by a manually tagged non aliased service' => [
            200,
            '/allowed/auto-configured-non-aliased',
        ];

        yield 'denied by an autoconfigured service' => [
            404,
            '/denied/manually-tagged',
        ];

        yield 'denied by a manually tagged service' => [
            404,
            '/denied/auto-configured',
        ];

        yield 'denied by a manually tagged non aliased service' => [
            404,
            '/denied/auto-configured-non-aliased',
        ];

        yield 'denied by an overridden service' => [
            404,
            '/denied/overridden',
        ];
    }
}
