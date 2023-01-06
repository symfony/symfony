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
use Symfony\Bridge\Doctrine\DependencyInjection\CompilerPass\RegisterUidTypePass;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class RegisterUidTypePassTest extends TestCase
{
    public function testRegistered()
    {
        $container = new ContainerBuilder();
        $container->setParameter('doctrine.dbal.connection_factory.types', ['foo' => 'bar']);
        (new RegisterUidTypePass())->process($container);

        $expected = [
            'foo' => 'bar',
            'uuid' => ['class' => UuidType::class],
            'ulid' => ['class' => UlidType::class],
        ];
        $this->assertSame($expected, $container->getParameter('doctrine.dbal.connection_factory.types'));
    }

    public function testRegisteredDontFail()
    {
        $container = new ContainerBuilder();
        (new RegisterUidTypePass())->process($container);

        $this->expectNotToPerformAssertions();
    }
}
