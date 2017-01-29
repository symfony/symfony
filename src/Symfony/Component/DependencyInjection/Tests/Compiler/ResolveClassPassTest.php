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

use Symfony\Component\DependencyInjection\Compiler\ResolveClassPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Tests\Fixtures\CaseSensitiveClass;

class ResolveClassPassTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider provideValidClassId
     */
    public function testResolveClassFromId($serviceId)
    {
        $pass = new ResolveClassPass();
        $container = new ContainerBuilder();
        $def = $container->register($serviceId);

        $pass->process($container);

        $this->assertSame($serviceId, $def->getClass());
    }

    public function provideValidClassId()
    {
        yield array('Acme\UnknownClass');
        yield array(CaseSensitiveClass::class);
    }

    /**
     * @dataProvider provideInvalidClassId
     */
    public function testWontResolveClassFromId($serviceId)
    {
        $pass = new ResolveClassPass();
        $container = new ContainerBuilder();
        $def = $container->register($serviceId);

        $pass->process($container);

        $this->assertNull($def->getClass());
    }

    public function provideInvalidClassId()
    {
        yield array(\stdClass::class);
        yield array('bar');
        yield array('\DateTime');
    }
}
