<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Tests\Loader\Configurator;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Loader\Configurator\AbstractConfigurator;
use Symfony\Component\DependencyInjection\Loader\Configurator\InlineServiceConfigurator;
use Symfony\Component\DependencyInjection\Loader\Configurator\ReferenceConfigurator;
use Symfony\Component\DependencyInjection\Reference;

class AbstractConfiguratorTest extends TestCase
{
    public function testInlineServiceIsNotAllowedWithoutAllowServices()
    {
        $configurator = new InlineServiceConfigurator(new Definition('stdClass'));

        $this->expectException(InvalidArgumentException::class);

        AbstractConfigurator::processValue($configurator, false);
    }

    public function testServiceReferenceIsNotAllowedWithoutAllowServices()
    {
        $configurator = new ReferenceConfigurator('foo');

        $this->expectException(InvalidArgumentException::class);

        AbstractConfigurator::processValue($configurator, false);
    }

    public function testInlineServiceIsAllowedWithAllowServices()
    {
        $configurator = new InlineServiceConfigurator(new Definition('stdClass'));

        $result = AbstractConfigurator::processValue($configurator, true);

        $this->assertInstanceOf(Definition::class, $result);
    }

    public function testServiceReferenceIsAllowedWithAllowServices()
    {
        $configurator = new ReferenceConfigurator('foo');

        $result = AbstractConfigurator::processValue($configurator, true);

        $this->assertInstanceOf(Reference::class, $result);
    }

    public function testProcessClosure()
    {
        $this->assertSame(
            [\DateTime::class, 'createFromFormat'],
            AbstractConfigurator::processValue(\DateTime::createFromFormat(...)),
        );

        $this->assertSame(
            'date_create',
            AbstractConfigurator::processValue(date_create(...)),
        );
    }

    public function testProcessNonStaticNamedClosure()
    {
        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('The method "DateTime::format(...)" is not static');

        AbstractConfigurator::processValue((new \DateTime())->format(...));
    }

    public function testProcessAnonymousClosure()
    {
        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('Anonymous closure not supported. The closure must be created from a static method or a global function.');

        AbstractConfigurator::processValue(static fn () => new \DateTime());
    }
}
