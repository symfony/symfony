<?php

/*
 * This file is part of the Symfony package.
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\DependencyInjection\Dumper;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Dumper\Dumper;

class DumperTest extends \PHPUnit_Framework_TestCase
{
    public function testDump()
    {
        $builder = new ContainerBuilder();
        $dumper = new ProjectDumper($builder);
        try {
            $dumper->dump();
            $this->fail('->dump() returns a LogicException if the dump() method has not been overriden by a children class');
        } catch (\Exception $e) {
            $this->assertInstanceOf('\LogicException', $e, '->dump() returns a LogicException if the dump() method has not been overriden by a children class');
            $this->assertEquals('You must extend this abstract class and implement the dump() method.', $e->getMessage(), '->dump() returns a LogicException if the dump() method has not been overriden by a children class');
        }
    }
}

class ProjectDumper extends Dumper
{
}
