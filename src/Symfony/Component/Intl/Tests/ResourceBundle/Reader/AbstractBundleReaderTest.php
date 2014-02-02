<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Intl\Tests\ResourceBundle\Reader;

use Symfony\Component\Filesystem\Filesystem;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class AbstractBundleReaderTest extends \PHPUnit_Framework_TestCase
{
    private $directory;

    /**
     * @var Filesystem
     */
    private $filesystem;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    private $reader;

    protected function setUp()
    {
        $this->directory = sys_get_temp_dir() . '/AbstractBundleReaderTest/' . rand(1000, 9999);
        $this->filesystem = new Filesystem();
        $this->reader = $this->getMockForAbstractClass('Symfony\Component\Intl\ResourceBundle\Reader\AbstractBundleReader');

        $this->filesystem->mkdir($this->directory);
    }

    protected function tearDown()
    {
        $this->filesystem->remove($this->directory);
    }

    public function testGetLocales()
    {
        $this->filesystem->touch($this->directory . '/en.foo');
        $this->filesystem->touch($this->directory . '/de.foo');
        $this->filesystem->touch($this->directory . '/fr.foo');
        $this->filesystem->touch($this->directory . '/bo.txt');
        $this->filesystem->touch($this->directory . '/gu.bin');
        $this->filesystem->touch($this->directory . '/s.lol');

        $this->reader->expects($this->any())
            ->method('getFileExtension')
            ->will($this->returnValue('foo'));

        $sortedLocales = array('de', 'en', 'fr');

        $this->assertSame($sortedLocales, $this->reader->getLocales($this->directory));
    }
}
