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

use Symfony\Component\HttpFoundation\Request;

abstract class AbstractAttributeRoutingTestCase extends AbstractWebTestCase
{
    /**
     * @dataProvider getRoutes
     */
    public function testAnnotatedController(string $path, string $expectedValue)
    {
        $client = $this->createClient(['test_case' => $this->getTestCaseApp(), 'root_config' => 'config.yml']);
        $client->request('GET', '/annotated'.$path);

        $this->assertSame(200, $client->getResponse()->getStatusCode());
        $this->assertSame($expectedValue, $client->getResponse()->getContent());

        $router = self::getContainer()->get('router');

        $this->assertSame('/annotated/create-transaction', $router->generate('symfony_framework_tests_functional_test_annotated_createtransaction'));
    }

    public static function getRoutes(): array
    {
        return [
            ['/null_request', Request::class],
            ['/null_argument', ''],
            ['/null_argument_with_route_param', ''],
            ['/null_argument_with_route_param/value', 'value'],
            ['/argument_with_route_param_and_default', 'value'],
            ['/argument_with_route_param_and_default/custom', 'custom'],
        ];
    }

    abstract protected function getTestCaseApp(): string;
}
