<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\DependencyInjection\Loader;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\FileLocator;

class FileLocatorTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @covers Symfony\Component\DependencyInjection\Loader\FileLocator::GetAbsolutePath
     */
    public function testGetAbsolutePath()
    {
        $loader = new FileLocator(array(__DIR__.'/../Fixtures/containers'));
        $this->assertEquals('/foo.xml', $loader->getAbsolutePath('/foo.xml'), '->getAbsolutePath() return the path unmodified if it is already an absolute path');
        $this->assertEquals('c:\\\\foo.xml', $loader->getAbsolutePath('c:\\\\foo.xml'), '->getAbsolutePath() return the path unmodified if it is already an absolute path');
        $this->assertEquals('c:/foo.xml', $loader->getAbsolutePath('c:/foo.xml'), '->getAbsolutePath() return the path unmodified if it is already an absolute path');
        $this->assertEquals('\\server\\foo.xml', $loader->getAbsolutePath('\\server\\foo.xml'), '->getAbsolutePath() return the path unmodified if it is already an absolute path');

        $this->assertEquals(__DIR__.DIRECTORY_SEPARATOR.'FileLocatorTest.php', $loader->getAbsolutePath('FileLocatorTest.php', __DIR__), '->getAbsolutePath() returns an absolute filename if the file exists in the current path');

        $this->assertEquals(__DIR__.'/../Fixtures/containers'.DIRECTORY_SEPARATOR.'container10.php', $loader->getAbsolutePath('container10.php', __DIR__), '->getAbsolutePath() returns an absolute filename if the file exists in one of the paths given in the constructor');

        $this->assertEquals('foo.xml', $loader->getAbsolutePath('foo.xml', __DIR__), '->getAbsolutePath() returns the path unmodified if it is unable to find it in the given paths');
    }
}
