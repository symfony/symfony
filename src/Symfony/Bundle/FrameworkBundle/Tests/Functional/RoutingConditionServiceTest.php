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

    public function testContextParametersAreResetBetweenRequests()
    {
        $client = static::createClient(['test_case' => 'RoutingConditionService']);
        $client->disableReboot();

        $client->request('GET', '/allowed/manually-tagged');
        $context = $client->getContainer()->get('router')->getContext();
        $context->setParameter('tenant', 'acme');

        $client->request('GET', '/allowed/manually-tagged');

        $this->assertSame(200, $client->getResponse()->getStatusCode());
        $this->assertFalse($context->hasParameter('tenant'));
        $this->assertSame('en', $context->getParameter('_locale'));
    }

    public function testContextPortsAreResetBetweenRequests()
    {
        $client = static::createClient(['test_case' => 'RoutingConditionService']);
        $client->disableReboot();
        $context = $client->getContainer()->get('router')->getContext();

        $client->request('GET', 'http://localhost:8080/allowed/manually-tagged');
        $client->request('GET', 'https://localhost:8443/allowed/manually-tagged');

        $this->assertSame(200, $client->getResponse()->getStatusCode());
        $this->assertSame(80, $context->getHttpPort());
        $this->assertSame(8443, $context->getHttpsPort());

        $client->request('GET', 'http://localhost/allowed/manually-tagged');

        $this->assertSame(200, $client->getResponse()->getStatusCode());
        $this->assertSame(80, $context->getHttpPort());
        $this->assertSame(443, $context->getHttpsPort());
    }

    public function testContextIsResetOutsideOfRequests()
    {
        static::bootKernel(['test_case' => 'RoutingConditionService']);
        $container = static::getContainer();
        $context = $container->get('router')->getContext();
        $initialContext = clone $context;

        $context
            ->setBaseUrl('/tenant')
            ->setPathInfo('/foo')
            ->setMethod('POST')
            ->setHost('tenant.example.com')
            ->setScheme('https')
            ->setHttpPort(8080)
            ->setHttpsPort(8443)
            ->setQueryString('foo=bar')
            ->setParameter('tenant', 'acme');

        $container->get('services_resetter')->reset();

        $this->assertEquals($initialContext, $context);
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
