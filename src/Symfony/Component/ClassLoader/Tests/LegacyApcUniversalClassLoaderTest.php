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

use Symfony\Component\ClassLoader\ApcUniversalClassLoader;

/**
 * @group legacy
 */
class LegacyApcUniversalClassLoaderTest extends \PHPUnit_Framework_TestCase
{
    protected function setUp()
    {
        if (ini_get('apc.enabled') && ini_get('apc.enable_cli')) {
            apcu_clear_cache();
        } else {
            $this->markTestSkipped('APC is not enabled.');
        }
    }

    protected function tearDown()
    {
        if (ini_get('apc.enabled') && ini_get('apc.enable_cli')) {
            apcu_clear_cache();
        }
    }

    public function testConstructor()
    {
        $loader = new ApcUniversalClassLoader('test.prefix.');
        $loader->registerNamespace('LegacyApc\Namespaced', __DIR__.DIRECTORY_SEPARATOR.'Fixtures');

        $this->assertEquals($loader->findFile('\LegacyApc\Namespaced\FooBar'), apcu_fetch('test.prefix.\LegacyApc\Namespaced\FooBar'), '__construct() takes a prefix as its first argument');
    }

   /**
    * @dataProvider getLoadClassTests
    */
   public function testLoadClass($className, $testClassName, $message)
   {
       $loader = new ApcUniversalClassLoader('test.prefix.');
       $loader->registerNamespace('LegacyApc\Namespaced', __DIR__.DIRECTORY_SEPARATOR.'Fixtures');
       $loader->registerPrefix('LegacyApc_Pearlike_', __DIR__.DIRECTORY_SEPARATOR.'Fixtures');
       $loader->loadClass($testClassName);
       $this->assertTrue(class_exists($className), $message);
   }

    public function getLoadClassTests()
    {
        return array(
           array('\\LegacyApc\\Namespaced\\Foo', 'LegacyApc\\Namespaced\\Foo',   '->loadClass() loads LegacyApc\Namespaced\Foo class'),
           array('LegacyApc_Pearlike_Foo',    'LegacyApc_Pearlike_Foo',      '->loadClass() loads LegacyApc_Pearlike_Foo class'),
       );
    }

   /**
    * @dataProvider getLoadClassFromFallbackTests
    */
   public function testLoadClassFromFallback($className, $testClassName, $message)
   {
       $loader = new ApcUniversalClassLoader('test.prefix.fallback');
       $loader->registerNamespace('LegacyApc\Namespaced', __DIR__.DIRECTORY_SEPARATOR.'Fixtures');
       $loader->registerPrefix('LegacyApc_Pearlike_', __DIR__.DIRECTORY_SEPARATOR.'Fixtures');
       $loader->registerNamespaceFallbacks(array(__DIR__.DIRECTORY_SEPARATOR.'Fixtures/LegacyApc/fallback'));
       $loader->registerPrefixFallbacks(array(__DIR__.DIRECTORY_SEPARATOR.'Fixtures/LegacyApc/fallback'));
       $loader->loadClass($testClassName);
       $this->assertTrue(class_exists($className), $message);
   }

    public function getLoadClassFromFallbackTests()
    {
        return array(
           array('\\LegacyApc\\Namespaced\\Baz',    'LegacyApc\\Namespaced\\Baz',    '->loadClass() loads LegacyApc\Namespaced\Baz class'),
           array('LegacyApc_Pearlike_Baz',       'LegacyApc_Pearlike_Baz',       '->loadClass() loads LegacyApc_Pearlike_Baz class'),
           array('\\LegacyApc\\Namespaced\\FooBar', 'LegacyApc\\Namespaced\\FooBar', '->loadClass() loads LegacyApc\Namespaced\Baz class from fallback dir'),
           array('LegacyApc_Pearlike_FooBar',    'LegacyApc_Pearlike_FooBar',    '->loadClass() loads LegacyApc_Pearlike_Baz class from fallback dir'),
       );
    }

   /**
    * @dataProvider getLoadClassNamespaceCollisionTests
    */
   public function testLoadClassNamespaceCollision($namespaces, $className, $message)
   {
       $loader = new ApcUniversalClassLoader('test.prefix.collision.');
       $loader->registerNamespaces($namespaces);

       $loader->loadClass($className);

       $this->assertTrue(class_exists($className), $message);
   }

    public function getLoadClassNamespaceCollisionTests()
    {
        return array(
           array(
               array(
                   'LegacyApc\\NamespaceCollision\\A' => __DIR__.DIRECTORY_SEPARATOR.'Fixtures/LegacyApc/alpha',
                   'LegacyApc\\NamespaceCollision\\A\\B' => __DIR__.DIRECTORY_SEPARATOR.'Fixtures/LegacyApc/beta',
               ),
               'LegacyApc\NamespaceCollision\A\Foo',
               '->loadClass() loads NamespaceCollision\A\Foo from alpha.',
           ),
           array(
               array(
                   'LegacyApc\\NamespaceCollision\\A\\B' => __DIR__.DIRECTORY_SEPARATOR.'Fixtures/LegacyApc/beta',
                   'LegacyApc\\NamespaceCollision\\A' => __DIR__.DIRECTORY_SEPARATOR.'Fixtures/LegacyApc/alpha',
               ),
               'LegacyApc\NamespaceCollision\A\Bar',
               '->loadClass() loads NamespaceCollision\A\Bar from alpha.',
           ),
           array(
               array(
                   'LegacyApc\\NamespaceCollision\\A' => __DIR__.DIRECTORY_SEPARATOR.'Fixtures/LegacyApc/alpha',
                   'LegacyApc\\NamespaceCollision\\A\\B' => __DIR__.DIRECTORY_SEPARATOR.'Fixtures/LegacyApc/beta',
               ),
               'LegacyApc\NamespaceCollision\A\B\Foo',
               '->loadClass() loads NamespaceCollision\A\B\Foo from beta.',
           ),
           array(
               array(
                   'LegacyApc\\NamespaceCollision\\A\\B' => __DIR__.DIRECTORY_SEPARATOR.'Fixtures/LegacyApc/beta',
                   'LegacyApc\\NamespaceCollision\\A' => __DIR__.DIRECTORY_SEPARATOR.'Fixtures/LegacyApc/alpha',
               ),
               'LegacyApc\NamespaceCollision\A\B\Bar',
               '->loadClass() loads NamespaceCollision\A\B\Bar from beta.',
           ),
       );
    }

   /**
    * @dataProvider getLoadClassPrefixCollisionTests
    */
   public function testLoadClassPrefixCollision($prefixes, $className, $message)
   {
       $loader = new ApcUniversalClassLoader('test.prefix.collision.');
       $loader->registerPrefixes($prefixes);

       $loader->loadClass($className);
       $this->assertTrue(class_exists($className), $message);
   }

    public function getLoadClassPrefixCollisionTests()
    {
        return array(
           array(
               array(
                   'LegacyApcPrefixCollision_A_' => __DIR__.DIRECTORY_SEPARATOR.'Fixtures/LegacyApc/alpha/LegacyApc',
                   'LegacyApcPrefixCollision_A_B_' => __DIR__.DIRECTORY_SEPARATOR.'Fixtures/LegacyApc/beta/LegacyApc',
               ),
               'LegacyApcPrefixCollision_A_Foo',
               '->loadClass() loads LegacyApcPrefixCollision_A_Foo from alpha.',
           ),
           array(
               array(
                   'LegacyApcPrefixCollision_A_B_' => __DIR__.DIRECTORY_SEPARATOR.'Fixtures/LegacyApc/beta/LegacyApc',
                   'LegacyApcPrefixCollision_A_' => __DIR__.DIRECTORY_SEPARATOR.'Fixtures/LegacyApc/alpha/LegacyApc',
               ),
               'LegacyApcPrefixCollision_A_Bar',
               '->loadClass() loads LegacyApcPrefixCollision_A_Bar from alpha.',
           ),
           array(
               array(
                   'LegacyApcPrefixCollision_A_' => __DIR__.DIRECTORY_SEPARATOR.'Fixtures/LegacyApc/alpha/LegacyApc',
                   'LegacyApcPrefixCollision_A_B_' => __DIR__.DIRECTORY_SEPARATOR.'Fixtures/LegacyApc/beta/LegacyApc',
               ),
               'LegacyApcPrefixCollision_A_B_Foo',
               '->loadClass() loads LegacyApcPrefixCollision_A_B_Foo from beta.',
           ),
           array(
               array(
                   'LegacyApcPrefixCollision_A_B_' => __DIR__.DIRECTORY_SEPARATOR.'Fixtures/LegacyApc/beta/LegacyApc',
                   'LegacyApcPrefixCollision_A_' => __DIR__.DIRECTORY_SEPARATOR.'Fixtures/LegacyApc/alpha/LegacyApc',
               ),
               'LegacyApcPrefixCollision_A_B_Bar',
               '->loadClass() loads LegacyApcPrefixCollision_A_B_Bar from beta.',
           ),
       );
    }
}
