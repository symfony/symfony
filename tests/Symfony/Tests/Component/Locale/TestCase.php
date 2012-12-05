<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Locale;

abstract class TestCase extends \PHPUnit_Framework_TestCase
{
    protected static $icuVersion = null;

    protected function is32Bit()
    {
        return PHP_INT_SIZE == 4;
    }

    protected function is64Bit()
    {
        return PHP_INT_SIZE == 8;
    }

    protected function isIntlExtensionLoaded()
    {
        return extension_loaded('intl');
    }

    protected function skipIfIntlExtensionIsNotLoaded()
    {
        if (!$this->isIntlExtensionLoaded()) {
            $this->markTestSkipped('The intl extension is not available.');
        }
    }

    protected function skipIfICUVersionIsTooOld()
    {
        if ($this->isLowerThanIcuVersion('4.0')) {
            $this->markTestSkipped('Please upgrade ICU version to 4+');
        }
    }

    protected function skipIfNot32Bit()
    {
        if (!$this->is32Bit()) {
            $this->markTestSkipped('PHP must be compiled in 32 bit mode to run this test');
        }
    }

    protected function skipIfNot64Bit()
    {
        if (!$this->is64Bit()) {
            $this->markTestSkipped('PHP must be compiled in 64 bit mode to run this test');
        }
    }

    protected function isGreaterOrEqualThanPhpVersion($version)
    {
        return version_compare(\PHP_VERSION, $version, '>=');
    }

    protected function isGreaterOrEqualThanIcuVersion($version)
    {
        $version = $this->normalizeIcuVersion($version);
        $icuVersion = $this->normalizeIcuVersion($this->getIntlExtensionIcuVersion());

        return $icuVersion >= $version;
    }

    protected function isSameAsIcuVersion($version)
    {
        $version = $this->normalizeIcuVersion($version);
        $icuVersion = $this->normalizeIcuVersion($this->getIntlExtensionIcuVersion());

        return $icuVersion === $version;
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
