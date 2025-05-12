<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Tests\DependencyInjection\CompilerPass;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Doctrine\DependencyInjection\CompilerPass\RegisterDatePointTypePass;
use Symfony\Bridge\Doctrine\Types\DatePointType;
use Symfony\Component\Clock\DatePoint;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class RegisterDatePointTypePassTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(DatePoint::class)) {
            self::markTestSkipped('The DatePoint class is not available.');
        }
    }

    public function testRegistered()
    {
        $container = new ContainerBuilder();
        $container->setParameter('doctrine.dbal.connection_factory.types', ['foo' => 'bar']);
        (new RegisterDatePointTypePass())->process($container);

        $expected = [
            'foo' => 'bar',
            'date_point' => ['class' => DatePointType::class],
        ];
        $this->assertSame($expected, $container->getParameter('doctrine.dbal.connection_factory.types'));
    }
}
