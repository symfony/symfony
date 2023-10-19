<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Tests\Compiler;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\PhpUnit\ExpectDeprecationTrait;
use Symfony\Component\DependencyInjection\Compiler\AutowireRequiredPropertiesPass;
use Symfony\Component\DependencyInjection\Compiler\ResolveClassPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;

require_once __DIR__.'/../Fixtures/includes/autowiring_classes.php';
require_once __DIR__.'/../Fixtures/includes/autowiring_classes_74.php';

class AutowireRequiredPropertiesPassTest extends TestCase
{
    use ExpectDeprecationTrait;

    /**
     * @group legacy
     */
    public function testInjection()
    {
        $this->expectDeprecation('Since symfony/dependency-injection 6.3: Using the "@required" annotation on property "Symfony\Component\DependencyInjection\Tests\Compiler\PropertiesInjection::$plop" is deprecated, use the "Symfony\Contracts\Service\Attribute\Required" attribute instead.');

        $container = new ContainerBuilder();
        $container->register(Bar::class);
        $container->register(A::class);
        $container->register(B::class);
        $container->register(PropertiesInjection::class)->setAutowired(true);

        (new ResolveClassPass())->process($container);
        (new AutowireRequiredPropertiesPass())->process($container);

        $properties = $container->getDefinition(PropertiesInjection::class)->getProperties();

        $this->assertArrayHasKey('plop', $properties);
        $this->assertEquals(Bar::class, (string) $properties['plop']);
    }

    public function testAttribute()
    {
        $container = new ContainerBuilder();
        $container->register(Foo::class);

        $container->register('property_injection', AutowireProperty::class)
            ->setAutowired(true);

        (new ResolveClassPass())->process($container);
        (new AutowireRequiredPropertiesPass())->process($container);

        $properties = $container->getDefinition('property_injection')->getProperties();

        $this->assertArrayHasKey('foo', $properties);
        $this->assertEquals(Foo::class, (string) $properties['foo']);
    }
}
