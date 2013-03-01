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

use Symfony\Component\ClassLoader\ClassCollectionLoader;

require_once __DIR__.'/Fixtures/ClassesWithParents/GInterface.php';
require_once __DIR__.'/Fixtures/ClassesWithParents/CInterface.php';
require_once __DIR__.'/Fixtures/ClassesWithParents/B.php';
require_once __DIR__.'/Fixtures/ClassesWithParents/A.php';

class ClassCollectionLoaderTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider getDifferentOrders
     */
    public function testClassReordering(array $classes)
    {
        $expected = array(
            'ClassesWithParents\\GInterface',
            'ClassesWithParents\\CInterface',
            'ClassesWithParents\\B',
            'ClassesWithParents\\A',
        );

        $r = new \ReflectionClass('Symfony\Component\ClassLoader\ClassCollectionLoader');
        $m = $r->getMethod('getOrderedClasses');
        $m->setAccessible(true);

        $ordered = $m->invoke('Symfony\Component\ClassLoader\ClassCollectionLoader', $classes);

        $this->assertEquals($expected, array_map(function ($class) { return $class->getName(); }, $ordered));
    }

    public function getDifferentOrders()
    {
        return array(
            array(array(
                'ClassesWithParents\\A',
                'ClassesWithParents\\CInterface',
                'ClassesWithParents\\GInterface',
                'ClassesWithParents\\B',
            )),
            array(array(
                'ClassesWithParents\\B',
                'ClassesWithParents\\A',
                'ClassesWithParents\\CInterface',
            )),
            array(array(
                'ClassesWithParents\\CInterface',
                'ClassesWithParents\\B',
                'ClassesWithParents\\A',
            )),
            array(array(
                'ClassesWithParents\\A',
            )),
        );
    }

    /**
     * @dataProvider getDifferentOrdersForTraits
     */
    public function testClassWithTraitsReordering(array $classes)
    {
        if (version_compare(phpversion(), '5.4.0', '<')) {
            $this->markTestSkipped('Requires PHP > 5.4.0.');

            return;
        }

        require_once __DIR__.'/Fixtures/ClassesWithParents/ATrait.php';
        require_once __DIR__.'/Fixtures/ClassesWithParents/BTrait.php';
        require_once __DIR__.'/Fixtures/ClassesWithParents/CTrait.php';
        require_once __DIR__.'/Fixtures/ClassesWithParents/D.php';
        require_once __DIR__.'/Fixtures/ClassesWithParents/E.php';

        $expected = array(
            'ClassesWithParents\\GInterface',
            'ClassesWithParents\\CInterface',
            'ClassesWithParents\\CTrait',
            'ClassesWithParents\\ATrait',
            'ClassesWithParents\\BTrait',
            'ClassesWithParents\\B',
            'ClassesWithParents\\A',
            'ClassesWithParents\\D',
            'ClassesWithParents\\E',
        );

        $r = new \ReflectionClass('Symfony\Component\ClassLoader\ClassCollectionLoader');
        $m = $r->getMethod('getOrderedClasses');
        $m->setAccessible(true);

        $ordered = $m->invoke('Symfony\Component\ClassLoader\ClassCollectionLoader', $classes);

        $this->assertEquals($expected, array_map(function ($class) { return $class->getName(); }, $ordered));
    }

    public function getDifferentOrdersForTraits()
    {
        return array(
            array(array(
                'ClassesWithParents\\E',
                'ClassesWithParents\\ATrait',
            )),
            array(array(
                'ClassesWithParents\\E',
            )),
        );
    }

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
            array("namespace   Bar ;\nclass Foo {}\n", "namespace   Bar\n{\nclass Foo {}\n}\n"),
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

    public function testLoadTwiceClass()
    {
        ClassCollectionLoader::load(array('Foo'), '', 'foo', false);
        ClassCollectionLoader::load(array('Foo'), '', 'foo', false);
    }
}
