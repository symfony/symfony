<?php

namespace Symfony\Tests\Component\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\Compiler\GenerateLookupMethodClassesPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class GenerateLookupMethodClassesPassTest extends \PHPUnit_Framework_TestCase
{
    protected function setUp()
    {
        $this->dir = sys_get_temp_dir().'/lookup_method_classes';

        if (!is_dir($this->dir) && false === @mkdir($this->dir, 0777, true)) {
            $this->markTestIncomplete('Cache dir could not be created.');
        }

        $this->container = new ContainerBuilder();
        $this->container->setParameter('kernel.cache_dir', sys_get_temp_dir());
    }

    protected function tearDown()
    {
        foreach (new \DirectoryIterator($this->dir) as $file) {
            if ('.' === $file->getFileName()) {
                continue;
            }

            @unlink($file->getPathName());
        }

        @rmdir($this->dir);
    }

    public function testProcess()
    {
        $defFoobar = $this->container
            ->register('foobar', 'Symfony\Tests\Component\DependencyInjection\Compiler\FooBarService')
            ->setPublic(false)
        ;
        $def = $this->container
            ->register('test', 'Symfony\Tests\Component\DependencyInjection\Compiler\LookupMethodTestClass')
            ->setLookupMethod('getFooBar', new Reference('foobar'))
        ;

        $this->process();

        $service = $this->container->get('test');
        $this->assertInstanceOf('Symfony\Component\DependencyInjection\LookupMethodClasses\LookupMethodTestClass', $service);
        $this->assertInstanceOf('Symfony\Tests\Component\DependencyInjection\Compiler\FooBarService', $service->getFooBar());
        $this->assertEquals($this->dir.'/LookupMethodTestClass.php', $def->getFile());
        $this->assertEquals('Symfony\Component\DependencyInjection\LookupMethodClasses\LookupMethodTestClass', $def->getClass());
        $this->assertTrue($defFoobar->isPublic());
    }

    private function process()
    {
        $pass = new GenerateLookupMethodClassesPass();
        $pass->process($this->container);
    }
}

class FooBarService {}

abstract class LookupMethodTestClass
{
    abstract public function getFooBar();
}
