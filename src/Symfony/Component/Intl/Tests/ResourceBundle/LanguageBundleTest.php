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

use Symfony\Component\Intl\Exception\MissingResourceException;
use Symfony\Component\Intl\ResourceBundle\LanguageBundle;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class LanguageBundleTest extends \PHPUnit_Framework_TestCase
{
    const RES_DIR = '/base/lang';

    /**
     * @var LanguageBundle
     */
    private $bundle;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    private $reader;

    protected function setUp()
    {
        $this->reader = $this->getMock('Symfony\Component\Intl\ResourceBundle\Reader\StructuredBundleReaderInterface');
        $this->bundle = new LanguageBundle(self::RES_DIR, $this->reader);
    }

    public function testGetLanguageName()
    {
        $this->reader->expects($this->once())
            ->method('readEntry')
            ->with(self::RES_DIR, 'en', array('Languages', 'de'))
            ->will($this->returnValue('German'));

        $this->assertSame('German', $this->bundle->getLanguageName('de', null, 'en'));
    }

    public function testGetLanguageNameWithRegion()
    {
        $this->reader->expects($this->once())
            ->method('readEntry')
            ->with(self::RES_DIR, 'en', array('Languages', 'en_GB'))
            ->will($this->returnValue('British English'));

        $this->assertSame('British English', $this->bundle->getLanguageName('en', 'GB', 'en'));
    }

    public function testGetLanguageNameWithUntranslatedRegion()
    {
        $this->reader->expects($this->at(0))
            ->method('readEntry')
            ->with(self::RES_DIR, 'en', array('Languages', 'en_US'))
            ->will($this->throwException(new MissingResourceException()));

        $this->reader->expects($this->at(1))
            ->method('readEntry')
            ->with(self::RES_DIR, 'en', array('Languages', 'en'))
            ->will($this->returnValue('English'));

        $this->assertSame('English', $this->bundle->getLanguageName('en', 'US', 'en'));
    }

    public function testGetLanguageNames()
    {
        $sortedLanguages = array(
            'en' => 'English',
            'de' => 'German',
        );

        $this->reader->expects($this->once())
            ->method('readEntry')
            ->with(self::RES_DIR, 'en', array('Languages'))
            ->will($this->returnValue($sortedLanguages));

        $this->assertSame($sortedLanguages, $this->bundle->getLanguageNames('en'));
    }

    public function testGetScriptName()
    {
        $this->reader->expects($this->once())
            ->method('readEntry')
            ->with(self::RES_DIR, 'en', array('Scripts', 'Latn'))
            ->will($this->returnValue('Latin'));

        $this->assertSame('Latin', $this->bundle->getScriptName('Latn', null, 'en'));
    }

    public function testGetScriptNameIncludedInLanguageBC()
    {
        $this->reader->expects($this->once())
            ->method('readEntry')
            ->with(self::RES_DIR, 'en', array('Scripts', 'Latn'))
            ->will($this->returnValue('Latin'));

        // the second argument once was used, but is now ignored since it
        // doesn't make a difference anyway
        $this->assertSame('Latin', $this->bundle->getScriptName('Latn', 'zh', 'en'));
    }

    public function testGetScriptNames()
    {
        $sortedScripts = array(
            'Cyrl' => 'cyrillique',
            'Latn' => 'latin',
        );

        $this->reader->expects($this->once())
            ->method('readEntry')
            ->with(self::RES_DIR, 'en', array('Scripts'))
            ->will($this->returnValue($sortedScripts));

        $this->assertSame($sortedScripts, $this->bundle->getScriptNames('en'));
    }
}
