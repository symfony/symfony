<?php

/*
 * This file is part of the Symfony package.
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\DependencyInjection\Extension;

require_once __DIR__.'/../Fixtures/includes/ProjectExtension.php';

use Symfony\Component\DependencyInjection\ContainerBuilder;

class ExtensionTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @covers Symfony\Component\DependencyInjection\Extension\Extension::load
     */
    public function testLoad()
    {
        $extension = new \ProjectExtension();

        try {
            $extension->load('foo', array(), new ContainerBuilder());
            $this->fail('->load() throws an InvalidArgumentException if the tag does not exist');
        } catch (\Exception $e) {
            $this->assertInstanceOf('\InvalidArgumentException', $e, '->load() throws an InvalidArgumentException if the tag does not exist');
            $this->assertEquals('The tag "project:foo" is not defined in the "project" extension.', $e->getMessage(), '->load() throws an InvalidArgumentException if the tag does not exist');
        }

        $extension->load('bar', array('foo' => 'bar'), $config = new ContainerBuilder());
        $this->assertEquals(array('project.parameter.bar' => 'bar', 'project.parameter.foo' => 'bar'), $config->getParameterBag()->all(), '->load() calls the method tied to the given tag');
    }
}
