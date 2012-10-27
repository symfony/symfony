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
    /**
     * @dataProvider getFixNamespaceDeclarationsData
     */
    public function testFixNamespaceDeclarations($source, $expected)
    {
        $this->assertEquals('<?php '.$expected, ClassCollectionLoader::fixNamespaceDeclarations('<?php '.$source));
    }

    /**
     * @dataProvider getFixNamespaceDeclarationsData
     */
    public function testFixNamespaceDeclarationsWithoutTokenizer($source, $expected)
    {
        ClassCollectionLoader::enableTokenizer(false);
        $this->assertEquals('<?php '.$expected, ClassCollectionLoader::fixNamespaceDeclarations('<?php '.$source));
        ClassCollectionLoader::enableTokenizer(true);
    }

    public function getFixNamespaceDeclarationsData()
    {
        return array(
            array("namespace;\nclass Foo {}\n", "namespace\n{\nclass Foo {}\n}\n"),
            array("namespace Foo;\nclass Foo {}\n", "namespace Foo\n{\nclass Foo {}\n}\n"),
            array("namespace   Bar ;\nclass Foo {}\n", "namespace   Bar \n{\nclass Foo {}\n}\n"),
            array("namespace Foo\Bar;\nclass Foo {}\n", "namespace Foo\Bar\n{\nclass Foo {}\n}\n"),
            array("namespace Foo\Bar\Bar\n{\nclass Foo {}\n}\n", "namespace Foo\Bar\Bar\n{\nclass Foo {}\n}\n"),
            array("namespace\n{\nclass Foo {}\n}\n", "namespace\n{\nclass Foo {}\n}\n"),
        );
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testUnableToLoadClassException()
    {
        ClassCollectionLoader::load(array('SomeNotExistingClass'), '', 'foo', false);
    }
}
