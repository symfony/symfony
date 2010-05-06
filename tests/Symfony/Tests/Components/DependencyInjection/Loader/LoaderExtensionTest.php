<?php

/*
 * This file is part of the Symfony package.
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Components\DependencyInjection\Loader;

require_once __DIR__.'/../../../../../fixtures/Symfony/Components/DependencyInjection/includes/ProjectExtension.php';

class LoaderExtensionTest extends \PHPUnit_Framework_TestCase
{
    public function testLoad()
    {
        $extension = new \ProjectExtension();

        try
        {
            $extension->load('foo', array());
            $this->fail('->load() throws an InvalidArgumentException if the tag does not exist');
        }
        catch (\Exception $e)
        {
            $this->assertInstanceOf('\InvalidArgumentException', $e, '->load() throws an InvalidArgumentException if the tag does not exist');
            $this->assertEquals('The tag "foo" is not defined in the "http://www.example.com/schema/project" extension.', $e->getMessage(), '->load() throws an InvalidArgumentException if the tag does not exist');
        }

        $config = $extension->load('bar', array('foo' => 'bar'));
        $this->assertEquals(array('project.parameter.bar' => 'bar'), $config->getParameters(), '->load() calls the method tied to the given tag');
    }
}
