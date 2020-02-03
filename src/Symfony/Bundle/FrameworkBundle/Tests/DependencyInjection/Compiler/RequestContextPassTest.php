<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\DependencyInjection\Compiler;

use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\RequestContextPass;
use Symfony\Bundle\FrameworkBundle\Tests\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class RequestContextPassTest extends TestCase
{
    public function testRouterRequestContextUrlParseTest()
    {
        $container = new ContainerBuilder();
        $container->setParameter('router.request_context.url', 'https://foo.example.com:8080/bar');
        $container->addCompilerPass(new RequestContextPass());

        $container->register('router', '\stdClass')->setPublic(true);
        $container->compile();

        $this->assertEquals('foo.example.com', $container->getParameter('router.request_context.host'));
        $this->assertEquals('https', $container->getParameter('router.request_context.scheme'));
        $this->assertEquals('/bar', $container->getParameter('router.request_context.base_url'));
        $this->assertEquals('8080', $container->getParameter('request_listener.https_port'));
    }

    public function testRouterRequestContextUrlParseWithoutBaseUrlTest()
    {
        $container = new ContainerBuilder();
        $container->setParameter('router.request_context.url', 'https://foo.example.com:8080');
        $container->addCompilerPass(new RequestContextPass());

        $container->register('router', '\stdClass')->setPublic(true);
        $container->compile();

        $this->assertEquals('foo.example.com', $container->getParameter('router.request_context.host'));
        $this->assertEquals('https', $container->getParameter('router.request_context.scheme'));
        $this->assertFalse(false, $container->hasParameter('router.request_context.base_url'));
        $this->assertEquals('8080', $container->getParameter('request_listener.https_port'));
    }
}
