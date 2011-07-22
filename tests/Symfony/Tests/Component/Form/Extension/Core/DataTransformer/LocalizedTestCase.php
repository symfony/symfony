<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Form\Extension\Core\DataTransformer;

class LocalizedTestCase extends \PHPUnit_Framework_TestCase
{
    protected static $icuVersion = null;

    protected function setUp()
    {
        parent::setUp();

        if (!$this->isIntlExtensionLoaded()) {
            $this->markTestSkipped('The "intl" extension is not available');
        }
    }

    protected function isIntlExtensionLoaded()
    {
        return extension_loaded('intl');
    }

    protected function isLowerThanIcuVersion($version)
    {
        $version = $this->normalizeIcuVersion($version);
        $icuVersion = $this->normalizeIcuVersion($this->getIntlExtensionIcuVersion());

        return $icuVersion < $version;
    }

    protected function normalizeIcuVersion($version)
    {
        return ((float) $version) * 100;
    }

    protected function getIntlExtensionIcuVersion()
    {
        if (isset(self::$icuVersion)) {
            return self::$icuVersion;
        }

        if (!$this->isIntlExtensionLoaded()) {
            throw new \RuntimeException('The intl extension is not available');
        }

        if (defined('INTL_ICU_VERSION')) {
            return INTL_ICU_VERSION;
        }

        $reflector = new \ReflectionExtension('intl');

        ob_start();
        $reflector->info();
        $output = ob_get_clean();

        preg_match('/^ICU version => (.*)$/m', $output, $matches);
        self::$icuVersion = $matches[1];

        return self::$icuVersion;
    }
}
