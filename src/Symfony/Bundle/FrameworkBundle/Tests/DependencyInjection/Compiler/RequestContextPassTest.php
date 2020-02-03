<?php
/**
 * Created by PhpStorm.
 * User: Danil Pyatnitsev
 * Date: 03.02.2020
 * Time: 22:12
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
        $this->assertEquals(false, $container->hasParameter('router.request_context.base_url'));
        $this->assertEquals('8080', $container->getParameter('request_listener.https_port'));
    }
}