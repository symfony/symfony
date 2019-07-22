<?php

namespace Symfony\Component\HttpKernel\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\RegisterLocaleAwareServicesPass;
use Symfony\Component\HttpKernel\EventListener\LocaleAwareListener;
use Symfony\Contracts\Translation\LocaleAwareInterface;

class RegisterLocaleAwareServicesPassTest extends TestCase
{
    public function testCompilerPass()
    {
        $container = new ContainerBuilder();

        $container->register('locale_aware_listener', LocaleAwareListener::class)
                  ->setPublic(true)
                  ->setArguments([null, null]);

        $container->register('some_locale_aware_service', LocaleAwareInterface::class)
                  ->setPublic(true)
                  ->addTag('kernel.locale_aware');

        $container->register('another_locale_aware_service', LocaleAwareInterface::class)
                  ->setPublic(true)
                  ->addTag('kernel.locale_aware');

        $container->addCompilerPass(new RegisterLocaleAwareServicesPass());
        $container->compile();

        $this->assertEquals(
            [
                new IteratorArgument([
                    0 => new Reference('some_locale_aware_service'),
                    1 => new Reference('another_locale_aware_service'),
                ]),
                null,
            ],
            $container->getDefinition('locale_aware_listener')->getArguments()
        );
    }

    public function testListenerUnregisteredWhenNoLocaleAwareServices()
    {
        $container = new ContainerBuilder();

        $container->register('locale_aware_listener', LocaleAwareListener::class)
                  ->setPublic(true)
                  ->setArguments([null, null]);

        $container->addCompilerPass(new RegisterLocaleAwareServicesPass());
        $container->compile();

        $this->assertFalse($container->hasDefinition('locale_aware_listener'));
    }
}
