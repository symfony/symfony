<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Intl\Tests\ResourceBundle;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class AbstractBundleTest extends \PHPUnit_Framework_TestCase
{
    const RES_DIR = '/base/dirName';

    /**
     * @var \Symfony\Component\Intl\ResourceBundle\AbstractBundle
     */
    private $bundle;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    private $reader;

    protected function setUp()
    {
        $this->reader = $this->getMock('Symfony\Component\Intl\ResourceBundle\Reader\StructuredBundleReaderInterface');
        $this->bundle = $this->getMockForAbstractClass(
            'Symfony\Component\Intl\ResourceBundle\AbstractBundle',
            array(self::RES_DIR, $this->reader)
        );

        $this->bundle->expects($this->any())
            ->method('getDirectoryName')
            ->will($this->returnValue('dirName'));
    }

    public function testGetLocales()
    {
        $locales = array('de', 'en', 'fr');

        $this->reader->expects($this->once())
            ->method('getLocales')
            ->with(self::RES_DIR)
            ->will($this->returnValue($locales));

        $this->assertSame($locales, $this->bundle->getLocales());
    }
}
