<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Loader\Configurator;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Loader\Configurator\AbstractConfigurator;

class AbstractConfiguratorTest extends TestCase
{
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
