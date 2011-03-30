<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\ClassLoader;

use Symfony\Component\ClassLoader\ClassNotFoundException;

class ClassNotFoundExceptionTest extends \PHPUnit_Framework_TestCase
{
    public function testGenerateException()
    {
        // basic sanity test
        $e = new ClassNotFoundException('Foo');
        $this->assertEquals('Class "Foo" could not be autoloaded: No possible paths could be found for the class or namespace. Check the class name or your autoloader configuration.', $e->getMessage());
    }

    /**
     * A rare test of a private method, due to the overall class's use of
     * the global autoloaders (making them difficult to test).
     *
     * This is meant to just be a sanity check, not to strictly test the
     * exact messages.
     *
     * @dataProvider getCreateMessageParameters
     */
    public function testCreateMessage($paths, $universal, $other)
    {
        $e = new ClassNotFoundException('Foo');
        $method = new \ReflectionMethod(
          'Symfony\Component\ClassLoader\ClassNotFoundException', 'constructMessage'
        );
        $method->setAccessible(true);

        $r = $method->invoke($e, 'Foo', $paths, $universal, $other);
        $this->assertContains('Class "Foo" could not be autoloaded', $r);
    }

    public function getCreateMessageParameters()
    {
        return array(
            // no universal autoloaders
            array(
                array(),
                0,
                1,
            ),

            // universal autoloaders + other autoloaders
            array(
                array(),
                1,
                2,
            ),

            // only universal, no paths
            array(
                array(),
                2,
                0,
            ),

            // only universal, 1 path, file does not exist
            array(
                array('/path/to/file'),
                1,
                0
            ),

            // only universal, 1 path, file exists
            array(
                array(__FILE__),
                1,
                0,
            ),

            // only universal, multiple paths
            array(
                array('/path/to/foo', '/path/to/bar'),
                1,
                0
            ),
        );
    }
}