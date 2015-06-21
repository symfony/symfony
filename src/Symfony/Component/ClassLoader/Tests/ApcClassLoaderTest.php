<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ClassLoader\Tests;

use Symfony\Component\ClassLoader\ApcClassLoader;
use Symfony\Component\ClassLoader\ClassLoader;

class ApcClassLoaderTest extends \PHPUnit_Framework_TestCase
{
    protected function setUp()
    {
        if (!extension_loaded('apc')) {
            $this->markTestSkipped('The apc extension is not available.');
        }

        if (!(ini_get('apc.enabled') && ini_get('apc.enable_cli'))) {
            $this->markTestSkipped('The apc extension is available, but not enabled.');
        } else {
            apc_clear_cache('user');
        }
    }

    protected function tearDown()
    {
        if (ini_get('apc.enabled') && ini_get('apc.enable_cli')) {
            apc_clear_cache('user');
        }
    }

    public function testConstructor()
    {
        $loader = new ClassLoader();
        $loader->addPrefix('Apc\Namespaced', __DIR__.DIRECTORY_SEPARATOR.'Fixtures');

        $loader = new ApcClassLoader('test.prefix.', $loader);

        $this->assertEquals($loader->findFile('\Apc\Namespaced\FooBar'), apc_fetch('test.prefix.\Apc\Namespaced\FooBar'), '__construct() takes a prefix as its first argument');
    }

    /**
     * @dataProvider getLoadClassTests
     */
    public function testLoadClass($className, $testClassName, $message)
    {
        $loader = new ClassLoader();
        $loader->addPrefix('Apc\Namespaced', __DIR__.DIRECTORY_SEPARATOR.'Fixtures');
        $loader->addPrefix('Apc_Pearlike_', __DIR__.DIRECTORY_SEPARATOR.'Fixtures');

        $loader = new ApcClassLoader('test.prefix.', $loader);
        $loader->loadClass($testClassName);
        $this->assertTrue(class_exists($className), $message);
    }

    public function getLoadClassTests()
    {
        return array(
           array('\\Apc\\Namespaced\\Foo', 'Apc\\Namespaced\\Foo',   '->loadClass() loads Apc\Namespaced\Foo class'),
           array('Apc_Pearlike_Foo',    'Apc_Pearlike_Foo',      '->loadClass() loads Apc_Pearlike_Foo class'),
        );
    }

    /**
     * @dataProvider getLoadClassFromFallbackTests
     */
    public function testLoadClassFromFallback($className, $testClassName, $message)
    {
        $loader = new ClassLoader();
        $loader->addPrefix('Apc\Namespaced', __DIR__.DIRECTORY_SEPARATOR.'Fixtures');
        $loader->addPrefix('Apc_Pearlike_', __DIR__.DIRECTORY_SEPARATOR.'Fixtures');
        $loader->addPrefix('', array(__DIR__.DIRECTORY_SEPARATOR.'Fixtures/Apc/fallback'));

        $loader = new ApcClassLoader('test.prefix.fallback', $loader);
        $loader->loadClass($testClassName);

        $this->assertTrue(class_exists($className), $message);
    }

    public function getLoadClassFromFallbackTests()
    {
        return array(
           array('\\Apc\\Namespaced\\Baz',    'Apc\\Namespaced\\Baz',    '->loadClass() loads Apc\Namespaced\Baz class'),
           array('Apc_Pearlike_Baz',       'Apc_Pearlike_Baz',       '->loadClass() loads Apc_Pearlike_Baz class'),
           array('\\Apc\\Namespaced\\FooBar', 'Apc\\Namespaced\\FooBar', '->loadClass() loads Apc\Namespaced\Baz class from fallback dir'),
           array('Apc_Pearlike_FooBar',    'Apc_Pearlike_FooBar',    '->loadClass() loads Apc_Pearlike_Baz class from fallback dir'),
       );
    }

    /**
     * @dataProvider getLoadClassNamespaceCollisionTests
     */
    public function testLoadClassNamespaceCollision($namespaces, $className, $message)
    {
        $loader = new ClassLoader();
        $loader->addPrefixes($namespaces);

        $loader = new ApcClassLoader('test.prefix.collision.', $loader);
        $loader->loadClass($className);

        $this->assertTrue(class_exists($className), $message);
    }

    public function getLoadClassNamespaceCollisionTests()
    {
        return array(
           array(
               array(
                   'Apc\\NamespaceCollision\\A' => __DIR__.DIRECTORY_SEPARATOR.'Fixtures/Apc/alpha',
                   'Apc\\NamespaceCollision\\A\\B' => __DIR__.DIRECTORY_SEPARATOR.'Fixtures/Apc/beta',
               ),
               'Apc\NamespaceCollision\A\Foo',
               '->loadClass() loads NamespaceCollision\A\Foo from alpha.',
           ),
           array(
               array(
                   'Apc\\NamespaceCollision\\A\\B' => __DIR__.DIRECTORY_SEPARATOR.'Fixtures/Apc/beta',
                   'Apc\\NamespaceCollision\\A' => __DIR__.DIRECTORY_SEPARATOR.'Fixtures/Apc/alpha',
               ),
               'Apc\NamespaceCollision\A\Bar',
               '->loadClass() loads NamespaceCollision\A\Bar from alpha.',
           ),
           array(
               array(
                   'Apc\\NamespaceCollision\\A' => __DIR__.DIRECTORY_SEPARATOR.'Fixtures/Apc/alpha',
                   'Apc\\NamespaceCollision\\A\\B' => __DIR__.DIRECTORY_SEPARATOR.'Fixtures/Apc/beta',
               ),
               'Apc\NamespaceCollision\A\B\Foo',
               '->loadClass() loads NamespaceCollision\A\B\Foo from beta.',
           ),
           array(
               array(
                   'Apc\\NamespaceCollision\\A\\B' => __DIR__.DIRECTORY_SEPARATOR.'Fixtures/Apc/beta',
                   'Apc\\NamespaceCollision\\A' => __DIR__.DIRECTORY_SEPARATOR.'Fixtures/Apc/alpha',
               ),
               'Apc\NamespaceCollision\A\B\Bar',
               '->loadClass() loads NamespaceCollision\A\B\Bar from beta.',
           ),
        );
    }

    /**
     * @dataProvider getLoadClassPrefixCollisionTests
     */
    public function testLoadClassPrefixCollision($prefixes, $className, $message)
    {
        $loader = new ClassLoader();
        $loader->addPrefixes($prefixes);

        $loader = new ApcClassLoader('test.prefix.collision.', $loader);
        $loader->loadClass($className);

        $this->assertTrue(class_exists($className), $message);
    }

    public function getLoadClassPrefixCollisionTests()
    {
        return array(
           array(
               array(
                   'ApcPrefixCollision_A_' => __DIR__.DIRECTORY_SEPARATOR.'Fixtures/Apc/alpha/Apc',
                   'ApcPrefixCollision_A_B_' => __DIR__.DIRECTORY_SEPARATOR.'Fixtures/Apc/beta/Apc',
               ),
               'ApcPrefixCollision_A_Foo',
               '->loadClass() loads ApcPrefixCollision_A_Foo from alpha.',
           ),
           array(
               array(
                   'ApcPrefixCollision_A_B_' => __DIR__.DIRECTORY_SEPARATOR.'Fixtures/Apc/beta/Apc',
                   'ApcPrefixCollision_A_' => __DIR__.DIRECTORY_SEPARATOR.'Fixtures/Apc/alpha/Apc',
               ),
               'ApcPrefixCollision_A_Bar',
               '->loadClass() loads ApcPrefixCollision_A_Bar from alpha.',
           ),
           array(
               array(
                   'ApcPrefixCollision_A_' => __DIR__.DIRECTORY_SEPARATOR.'Fixtures/Apc/alpha/Apc',
                   'ApcPrefixCollision_A_B_' => __DIR__.DIRECTORY_SEPARATOR.'Fixtures/Apc/beta/Apc',
               ),
               'ApcPrefixCollision_A_B_Foo',
               '->loadClass() loads ApcPrefixCollision_A_B_Foo from beta.',
           ),
           array(
               array(
                   'ApcPrefixCollision_A_B_' => __DIR__.DIRECTORY_SEPARATOR.'Fixtures/Apc/beta/Apc',
                   'ApcPrefixCollision_A_' => __DIR__.DIRECTORY_SEPARATOR.'Fixtures/Apc/alpha/Apc',
               ),
               'ApcPrefixCollision_A_B_Bar',
               '->loadClass() loads ApcPrefixCollision_A_B_Bar from beta.',
           ),
        );
    }
}
