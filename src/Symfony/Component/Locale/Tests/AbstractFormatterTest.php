<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Locale\Tests;

use Symfony\Component\Locale\Formatter;

abstract class AbstractFormatterTest extends TestCase
{
    static private $defaultTimeZone;

    private $defaultLocale;
    protected $dateTime;

    public function __construct()
    {
        // intl uses the TZ environment instead of the date.timezone value.
        // For some reason, it is not possible to do this in setUp() or setUpBeforeClass().
        self::$defaultTimeZone = getenv('TZ');
        putenv('TZ=America/Sao_Paulo');
    }

    static public function tearDownAfterClass()
    {
        putenv('TZ='.self::$defaultTimeZone);
    }

    protected function setUp()
    {
        $this->defaultLocale = \Locale::getDefault();

        if ($this->isIntlExtensionLoaded()) {
            \Locale::setDefault('en');
        }

        $this->dateTime = new \DateTime('2012-07-10 23:00:00', new \DateTimeZone('America/Sao_Paulo'));
    }

    protected function tearDown()
    {
        if ($this->isIntlExtensionLoaded()) {
            \Locale::setDefault($this->defaultLocale);
        }

        unset($this->dateTime);
    }
}