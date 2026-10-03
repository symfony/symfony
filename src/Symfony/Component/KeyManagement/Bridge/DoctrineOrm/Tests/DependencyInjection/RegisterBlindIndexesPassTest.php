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
use Symfony\Component\KeyManagement\Bridge\DoctrineOrm\DependencyInjection\RegisterBlindIndexesPass;
use Symfony\Component\KeyManagement\Bridge\DoctrineOrm\EventListener\BlindIndexListener;

class RegisterBlindIndexesPassTest extends TestCase
{
    public function testTheIndexesAreKeyedByTheNameTheyCarry()
    {
        $container = $this->createContainer();
        $container->register('app.email_index', BlindIndex::class)->addTag('key_management.blind_index', ['index' => 'email']);
        $container->register('app.domain_index', BlindIndex::class)->addTag('key_management.blind_index', ['index' => 'email-domain']);

        (new RegisterBlindIndexesPass())->process($container);

        $this->assertSame(['email', 'email-domain'], array_keys($this->indexesOf($container)));
    }

    /**
     * Two columns holding an address are what keying by projection made impossible.
     */
    public function testTwoIndexesMayDeriveThroughOneProjection()
    {
        $container = $this->createContainer();
        $container->register('app.user_email_index', BlindIndex::class)->addTag('key_management.blind_index', ['index' => 'user-email']);
        $container->register('app.contact_email_index', BlindIndex::class)->addTag('key_management.blind_index', ['index' => 'contact-email']);

        (new RegisterBlindIndexesPass())->process($container);

        $this->assertSame(['user-email', 'contact-email'], array_keys($this->indexesOf($container)));
    }

    public function testTheNameIsResolvedFromTheParameterBag()
    {
        $container = $this->createContainer();
        $container->setParameter('app.index_name', 'email');
        $container->register('app.email_index', BlindIndex::class)->addTag('key_management.blind_index', ['index' => '%app.index_name%']);

        (new RegisterBlindIndexesPass())->process($container);

        $this->assertSame(['email'], array_keys($this->indexesOf($container)));
    }

    public function testATagWithoutANameIsRefused()
    {
        $container = $this->createContainer();
        $container->register('app.email_index', BlindIndex::class)->addTag('key_management.blind_index');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The "key_management.blind_index" tag of service "app.email_index" must carry an "index"');

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
            ->addTag('key_management.blind_index', ['index' => 'email'])
            ->addTag('key_management.blind_index');

        (new RegisterBlindIndexesPass())->process($container);

        $this->assertSame(['email'], array_keys($this->indexesOf($container)));
    }

    public function testAnIndexTaggedUnderTwoNamesIsRefused()
    {
        $container = $this->createContainer();
        $container->register('app.email_index', BlindIndex::class)
            ->addTag('key_management.blind_index', ['index' => 'email'])
            ->addTag('key_management.blind_index', ['index' => 'email-domain']);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Service "app.email_index" is tagged "key_management.blind_index" under the names');

        (new RegisterBlindIndexesPass())->process($container);
    }

    public function testTwoIndexesUnderTheSameNameAreRefused()
    {
        $container = $this->createContainer();
        $container->register('app.first_index', BlindIndex::class)->addTag('key_management.blind_index', ['index' => 'email']);
        $container->register('app.second_index', BlindIndex::class)->addTag('key_management.blind_index', ['index' => 'email']);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Services "app.first_index" and "app.second_index" are both blind indexes named "email"');

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
        $container->register('app.email_index', BlindIndex::class)->addTag('key_management.blind_index', ['index' => 'email']);

        (new RegisterBlindIndexesPass())->process($container);

        $this->assertSame(['service_container', 'app.email_index'], array_keys($container->getDefinitions()));
    }

    /**
     * @return array<string, \Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument>
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
