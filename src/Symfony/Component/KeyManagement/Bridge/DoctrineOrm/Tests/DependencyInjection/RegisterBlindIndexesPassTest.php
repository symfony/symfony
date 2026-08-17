<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement\Bridge\DoctrineOrm\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\KeyManagement\BlindIndex\Email;
use Symfony\Component\KeyManagement\BlindIndex\EmailDomain;
use Symfony\Component\KeyManagement\Bridge\DoctrineOrm\DependencyInjection\RegisterBlindIndexesPass;
use Symfony\Component\KeyManagement\Bridge\DoctrineOrm\EventListener\BlindIndexListener;

class RegisterBlindIndexesPassTest extends TestCase
{
    public function testTheIndexesAreKeyedByClass()
    {
        $container = $this->createContainer();
        $container->register('app.email_index', Email::class)->addTag('key_management.blind_index');
        $container->register('app.domain_index', EmailDomain::class)->addTag('key_management.blind_index');

        (new RegisterBlindIndexesPass())->process($container);

        $this->assertSame([Email::class, EmailDomain::class], array_keys($this->indexesOf($container)));
    }

    public function testTheClassIsResolvedFromTheParameterBag()
    {
        $container = $this->createContainer();
        $container->setParameter('app.index_class', Email::class);
        $container->register('app.email_index', '%app.index_class%')->addTag('key_management.blind_index');

        (new RegisterBlindIndexesPass())->process($container);

        $this->assertSame([Email::class], array_keys($this->indexesOf($container)));
    }

    /**
     * The attribute names a class, so two services sharing one leave it with no way to say which it
     * meant. Refused at compile time rather than resolved by chance.
     */
    public function testTwoIndexesOfTheSameClassAreRefused()
    {
        $container = $this->createContainer();
        $container->register('app.first_index', Email::class)->addTag('key_management.blind_index');
        $container->register('app.second_index', Email::class)->addTag('key_management.blind_index');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(\sprintf('Services "app.first_index" and "app.second_index" are both blind indexes of class "%s"', Email::class));

        (new RegisterBlindIndexesPass())->process($container);
    }

    public function testTheListenerIsRemovedWhenNoIndexIsRegistered()
    {
        $container = $this->createContainer();

        (new RegisterBlindIndexesPass())->process($container);

        $this->assertFalse($container->hasDefinition('key_management.blind_index_listener'));
    }

    public function testNothingHappensWithoutTheListener()
    {
        $container = new ContainerBuilder();
        $container->register('app.email_index', Email::class)->addTag('key_management.blind_index');

        (new RegisterBlindIndexesPass())->process($container);

        $this->assertSame(['service_container', 'app.email_index'], array_keys($container->getDefinitions()));
    }

    /**
     * @return array<class-string, \Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument>
     */
    private function indexesOf(ContainerBuilder $container): array
    {
        $locator = $container->getDefinition('key_management.blind_index_listener')->getArgument(0);

        return $container->getDefinition((string) $locator)->getArgument(0);
    }

    private function createContainer(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->register('key_management.blind_index_listener', BlindIndexListener::class)
            ->setArguments([new ServiceLocator([])]);

        return $container;
    }
}
