<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Config;

use Symfony\Component\Config\FileLocator;

class FileLocatorTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider getIsAbsolutePathTests
     */
    public function testIsAbsolutePath($path)
    {
        $loader = new FileLocator(array());
        $r = new \ReflectionObject($loader);
        $m = $r->getMethod('isAbsolutePath');
        $m->setAccessible(true);

        $this->assertTrue($m->invoke($loader, $path), '->isAbsolutePath() returns true for an absolute path');
    }

    public function getIsAbsolutePathTests()
    {
        return array(
            array('/foo.xml'),
            array('c:\\\\foo.xml'),
            array('c:/foo.xml'),
            array('\\server\\foo.xml'),
        );
    }

    public function testLocate()
    {
        $loader = new FileLocator(array(__DIR__.'/Fixtures'));

        $this->assertEquals(__DIR__.DIRECTORY_SEPARATOR.'FileLocatorTest.php', $loader->locate('FileLocatorTest.php', __DIR__), '->getAbsolutePath() returns an absolute filename if the file exists in the current path');

        $this->assertEquals(__DIR__.'/Fixtures'.DIRECTORY_SEPARATOR.'foo.xml', $loader->locate('foo.xml', __DIR__), '->getAbsolutePath() returns an absolute filename if the file exists in one of the paths given in the constructor');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testLocateThrowsAnExceptionIfTheFileDoesNotExists()
    {
        $loader = new FileLocator(array(__DIR__.'/Fixtures'));

        $loader->locate('foobar.xml', __DIR__);
    }
}
