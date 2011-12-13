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

use Symfony\Component\ClassLoader\ClassCollectionLoader;

class ClassCollectionLoaderTest extends \PHPUnit_Framework_TestCase
{
    public function testFixNamespaceDeclarations()
    {
        $source = "<?php\n"
            ."\n"
            ."namespace Foo;\n"
            ."class Foo {}\n"
            ."namespace   Bar ;\n"
            ."class Foo {}\n"
            ."namespace Foo\Bar;\n"
            ."class Foo {}\n"
            ."namespace Foo\Bar\Bar\n"
            ."{\n"
            ."    class Foo {}\n"
            ."}\n"
            ."namespace\n"
            ."{\n"
            ."    class Foo {}\n"
            ."}\n"
        ;

        $expected = "<?php\n"
            . "\n"
            . "namespace Foo\n"
            . "{\n"
            . "class Foo {}\n"
            . "}\n"
            . "namespace   Bar \n"
            . "{\n"
            . "class Foo {}\n"
            . "}\n"
            . "namespace Foo\\Bar\n"
            . "{\n"
            . "class Foo {}\n"
            . "}\n"
            . "namespace Foo\\Bar\\Bar\n"
            . "{\n"
            . "    class Foo {}\n"
            . "}\n"
            . "namespace\n"
            . "{\n"
            . "    class Foo {}\n"
            . "}\n"
        ;

        $this->assertEquals($expected, ClassCollectionLoader::fixNamespaceDeclarations($source));
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testUnableToLoadClassException()
    {
        ClassCollectionLoader::load(array('SomeNotExistingClass'), '', 'foo', false);
    }
}
