<?php

/*
 * This file is part of the Symfony package.
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Components\DependencyInjection\Loader;

require_once __DIR__.'/../Fixtures/includes/ProjectExtension.php';

use Symfony\Components\DependencyInjection\BuilderConfiguration;

class LoaderExtensionTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @covers Symfony\Components\DependencyInjection\Loader\LoaderExtension::load
     */
    public function testLoad()
    {
        $extension = new \ProjectExtension();

        try {
            $extension->load('foo', array(), new BuilderConfiguration());
            $this->fail('->load() throws an InvalidArgumentException if the tag does not exist');
        } catch (\Exception $e) {
            $this->assertInstanceOf('\InvalidArgumentException', $e, '->load() throws an InvalidArgumentException if the tag does not exist');
            $this->assertEquals('The tag "project:foo" is not defined in the "project" extension.', $e->getMessage(), '->load() throws an InvalidArgumentException if the tag does not exist');
        }

        $config = $extension->load('bar', array('foo' => 'bar'), new BuilderConfiguration());
        $this->assertEquals(array('project.parameter.bar' => 'bar', 'project.parameter.foo' => 'bar'), $config->getParameterBag()->all(), '->load() calls the method tied to the given tag');
    }
}
