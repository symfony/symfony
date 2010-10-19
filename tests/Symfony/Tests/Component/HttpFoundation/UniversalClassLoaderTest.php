<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\HttpFoundation;

use Symfony\Component\HttpFoundation\UniversalClassLoader;

class UniversalClassLoaderTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @covers Symfony\Component\HttpFoundation\UniversalClassLoader::loadClass
     * @dataProvider testClassProvider
     */
    public function testLoadClass($className, $testClassName, $message)
    {
        $loader = new UniversalClassLoader();
        $loader->registerNamespace('Namespaced', __DIR__ . DIRECTORY_SEPARATOR . 'Fixtures');
        $loader->registerPrefix('Pearlike_', __DIR__ . DIRECTORY_SEPARATOR . 'Fixtures');
        $loader->loadClass($testClassName);
        $this->assertTrue(class_exists($className), $message);
    }

    public static function testClassProvider()
    {
        return array(
            array('\\Namespaced\\Foo', 'Namespaced\\Foo',   '->loadClass() loads Namespaced\Foo class'),
            array('\\Pearlike_Foo',    'Pearlike_Foo',      '->loadClass() loads Pearlike_Foo class'),
            array('\\Namespaced\\Bar', '\\Namespaced\\Bar', '->loadClass() loads Namespaced\Bar class with a leading slash'),
            array('\\Pearlike_Bar',    '\\Pearlike_Bar',    '->loadClass() loads Pearlike_Bar class with a leading slash'),
        );
    }
}

