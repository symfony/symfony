<?php

/*
 * This file is part of the Symfony package.
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Routing\Loader;

use Symfony\Component\Routing\Loader\LoaderResolver;
use Symfony\Component\Routing\Loader\YamlFileLoader;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

class YamlFileLoaderTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @covers Symfony\Component\Routing\Loader\YamlFileLoader::supports
     */
    public function testSupports()
    {
        $loader = new YamlFileLoader();

        $this->assertTrue($loader->supports('foo.yml'), '->supports() returns true if the resource is loadable');
        $this->assertFalse($loader->supports('foo.foo'), '->supports() returns true if the resource is loadable');

        $this->assertTrue($loader->supports('foo.yml', 'yaml'), '->supports() checks the resource type if specified');
        $this->assertFalse($loader->supports('foo.yml', 'foo'), '->supports() checks the resource type if specified');
    }
}
