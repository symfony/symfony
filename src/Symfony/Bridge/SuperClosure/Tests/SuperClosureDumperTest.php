<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\SuperClosure\Tests;

use Symfony\Bridge\SuperClosure\ClosureDumper\SuperClosureDumper;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @author Nikita Konstantinov <unk91nd@gmail.com>
 */
class SuperClosureDumperTest extends \PHPUnit_Framework_TestCase
{
    public function testThatClosureDumps()
    {
        $dumper = new SuperClosureDumper();

        $expectedCode = <<<'CODE'
function (\Symfony\Component\DependencyInjection\ContainerInterface $container) {
    return $container->get('foo');
}
CODE;

        $actualCode = $dumper->dump(function (ContainerInterface $container) {
            return $container->get('foo');
        });

        $this->assertSame($expectedCode, $actualCode);
    }

    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\DumpingClosureException
     */
    public function testThatContextDependentClosureCannotBeDumped()
    {
        $dumper = new SuperClosureDumper();

        $dumper->dump(function () use ($dumper) {
            return new \stdClass();
        });
    }
}
