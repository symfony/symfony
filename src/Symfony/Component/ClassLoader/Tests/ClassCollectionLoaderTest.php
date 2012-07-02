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
use Symfony\Component\ClassLoader\UniversalClassLoader;

class ClassCollectionLoaderTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider getDifferentOrders
     */
    public function testClassReordering(array $classes)
    {
        $loader = new UniversalClassLoader();
        $loader->registerNamespace('ClassesWithParents', __DIR__.'/Fixtures');
        $loader->register();

        $expected = <<<EOF
<?php  

namespace ClassesWithParents
{

interface CInterface {}
}
 

namespace ClassesWithParents
{

class B implements CInterface {}
}
 

namespace ClassesWithParents
{

class A extends B {}
}

EOF;

        $dir = sys_get_temp_dir();
        $fileName = uniqid('symfony_');

        ClassCollectionLoader::load($classes, $dir, $fileName, true);
        $cachedContent = @file_get_contents($dir.'/'.$fileName.'.php');

        $this->assertEquals($expected, $cachedContent);
    }
    
    public function getDifferentOrders()
    {
        return array(
            array(array(
                'ClassesWithParents\\A',
                'ClassesWithParents\\CInterface',
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
        );
    }

    public function testFixNamespaceDeclarations()
    {
        $source = <<<EOF
<?php

namespace Foo;
class Foo {}
namespace   Bar ;
class Foo {}
namespace Foo\Bar;
class Foo {}
namespace Foo\Bar\Bar
{
    class Foo {}
}
namespace
{
    class Foo {}
}
EOF;

        $expected = <<<EOF
<?php

namespace Foo
{
class Foo {}
}
namespace   Bar
{
class Foo {}
}
namespace Foo\Bar
{
class Foo {}
}
namespace Foo\Bar\Bar
{
    class Foo {}
}
namespace
{
    class Foo {}
}
EOF;

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
