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

    /**
     * @dataProvider namespaceCollisionClassProvider
     */
    public function testLoadClassNamespaceCollision($namespaces, $className, $message)
    {
        $loader = new UniversalClassLoader();
        $loader->registerNamespaces($namespaces);

        $loader->loadClass($className);
        $this->assertTrue(class_exists($className), $message);
    }

    public static function namespaceCollisionClassProvider()
    {
        return array(
            array(
                array(
                    'NamespaceCollision\\A' => __DIR__ . DIRECTORY_SEPARATOR . 'Fixtures/alpha',
                    'NamespaceCollision\\A\\B' => __DIR__ . DIRECTORY_SEPARATOR . 'Fixtures/beta',
                ),
                'NamespaceCollision\A\Foo',
                '->loadClass() loads NamespaceCollision\A\Foo from alpha.',
            ),
            array(
                array(
                    'NamespaceCollision\\A\\B' => __DIR__ . DIRECTORY_SEPARATOR . 'Fixtures/beta',
                    'NamespaceCollision\\A' => __DIR__ . DIRECTORY_SEPARATOR . 'Fixtures/alpha',
                ),
                'NamespaceCollision\A\Bar',
                '->loadClass() loads NamespaceCollision\A\Bar from alpha.',
            ),
            array(
                array(
                    'NamespaceCollision\\A' => __DIR__ . DIRECTORY_SEPARATOR . 'Fixtures/alpha',
                    'NamespaceCollision\\A\\B' => __DIR__ . DIRECTORY_SEPARATOR . 'Fixtures/beta',
                ),
                'NamespaceCollision\A\B\Foo',
                '->loadClass() loads NamespaceCollision\A\B\Foo from beta.',
            ),
            array(
                array(
                    'NamespaceCollision\\A\\B' => __DIR__ . DIRECTORY_SEPARATOR . 'Fixtures/beta',
                    'NamespaceCollision\\A' => __DIR__ . DIRECTORY_SEPARATOR . 'Fixtures/alpha',
                ),
                'NamespaceCollision\A\B\Bar',
                '->loadClass() loads NamespaceCollision\A\B\Bar from beta.',
            ),
        );
    }

    /**
     * @dataProvider prefixCollisionClassProvider
     */
    public function testLoadClassPrefixCollision($prefixes, $className, $message)
    {
        $loader = new UniversalClassLoader();
        $loader->registerPrefixes($prefixes);

        $loader->loadClass($className);
        $this->assertTrue(class_exists($className), $message);
    }

    public static function prefixCollisionClassProvider()
    {
        return array(
            array(
                array(
                    'PrefixCollision_A_' => __DIR__ . DIRECTORY_SEPARATOR . 'Fixtures/alpha',
                    'PrefixCollision_A_B_' => __DIR__ . DIRECTORY_SEPARATOR . 'Fixtures/beta',
                ),
                'PrefixCollision_A_Foo',
                '->loadClass() loads PrefixCollision_A_Foo from alpha.',
            ),
            array(
                array(
                    'PrefixCollision_A_B_' => __DIR__ . DIRECTORY_SEPARATOR . 'Fixtures/beta',
                    'PrefixCollision_A_' => __DIR__ . DIRECTORY_SEPARATOR . 'Fixtures/alpha',
                ),
                'PrefixCollision_A_Bar',
                '->loadClass() loads PrefixCollision_A_Bar from alpha.',
            ),
            array(
                array(
                    'PrefixCollision_A_' => __DIR__ . DIRECTORY_SEPARATOR . 'Fixtures/alpha',
                    'PrefixCollision_A_B_' => __DIR__ . DIRECTORY_SEPARATOR . 'Fixtures/beta',
                ),
                'PrefixCollision_A_B_Foo',
                '->loadClass() loads PrefixCollision_A_B_Foo from beta.',
            ),
            array(
                array(
                    'PrefixCollision_A_B_' => __DIR__ . DIRECTORY_SEPARATOR . 'Fixtures/beta',
                    'PrefixCollision_A_' => __DIR__ . DIRECTORY_SEPARATOR . 'Fixtures/alpha',
                ),
                'PrefixCollision_A_B_Bar',
                '->loadClass() loads PrefixCollision_A_B_Bar from beta.',
            ),
        );
    }
}
