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
use Symfony\Component\KeyManagement\BlindIndex;
use Symfony\Component\KeyManagement\BlindIndex\Projection\Email;
use Symfony\Component\KeyManagement\BlindIndex\Projection\EmailDomain;
use Symfony\Component\KeyManagement\Bridge\DoctrineOrm\DependencyInjection\RegisterBlindIndexesPass;
use Symfony\Component\KeyManagement\Bridge\DoctrineOrm\EventListener\BlindIndexListener;

class RegisterBlindIndexesPassTest extends TestCase
{
    public function testTheIndexesAreKeyedByTheProjectionTheyDeriveThrough()
    {
        $container = $this->createContainer();
        $container->register('app.email_index', BlindIndex::class)->addTag('key_management.blind_index', ['projection' => Email::class]);
        $container->register('app.domain_index', BlindIndex::class)->addTag('key_management.blind_index', ['projection' => EmailDomain::class]);

        (new RegisterBlindIndexesPass())->process($container);

        $this->assertSame([Email::class, EmailDomain::class], array_keys($this->indexesOf($container)));
    }

    public function testTheProjectionIsResolvedFromTheParameterBag()
    {
        $container = $this->createContainer();
        $container->setParameter('app.projection_class', Email::class);
        $container->register('app.email_index', BlindIndex::class)->addTag('key_management.blind_index', ['projection' => '%app.projection_class%']);

        (new RegisterBlindIndexesPass())->process($container);

        $this->assertSame([Email::class], array_keys($this->indexesOf($container)));
    }

    public function testATagWithoutAProjectionIsRefused()
    {
        $container = $this->createContainer();
        $container->register('app.email_index', BlindIndex::class)->addTag('key_management.blind_index');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The "key_management.blind_index" tag of service "app.email_index" must carry a "projection"');

        (new RegisterBlindIndexesPass())->process($container);
    }

    /**
     * This is the shape `ResolveInstanceofConditionalsPass` leaves behind.
     *
     * It adds the autoconfigured tag beside an explicit one rather than in its place, so a service
     * tagged by hand and autoconfigured carries the bare entry too.
     */
    public function testTheBareTagOfAutoconfigurationSitsBesideAnExplicitOne()
    {
        $container = $this->createContainer();
        $container->register('app.email_index', BlindIndex::class)
            ->addTag('key_management.blind_index', ['projection' => Email::class])
            ->addTag('key_management.blind_index');

        (new RegisterBlindIndexesPass())->process($container);

        $this->assertSame([Email::class], array_keys($this->indexesOf($container)));
    }

    public function testAnIndexTaggedForTwoProjectionsIsRefused()
    {
        $container = $this->createContainer();
        $container->register('app.email_index', BlindIndex::class)
            ->addTag('key_management.blind_index', ['projection' => Email::class])
            ->addTag('key_management.blind_index', ['projection' => EmailDomain::class]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Service "app.email_index" is tagged "key_management.blind_index" for the projections');

        (new RegisterBlindIndexesPass())->process($container);
    }

    public function testTwoIndexesOverTheSameProjectionAreRefused()
    {
        $container = $this->createContainer();
        $container->register('app.first_index', BlindIndex::class)->addTag('key_management.blind_index', ['projection' => Email::class]);
        $container->register('app.second_index', BlindIndex::class)->addTag('key_management.blind_index', ['projection' => Email::class]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(\sprintf('Services "app.first_index" and "app.second_index" are both blind indexes over the projection "%s"', Email::class));

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
        $container->register('app.email_index', BlindIndex::class)->addTag('key_management.blind_index', ['projection' => Email::class]);

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
