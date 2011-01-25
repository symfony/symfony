<?php

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

    protected function skipIfPhpIsNot32Bit()
    {
        if (!$this->is32Bit()) {
            $this->markTestSkipped('The PHP must be compiled in 32 bit mode to run this test.');
        }
    }

    protected function skipIfPhpIsNot64Bit()
    {
        if (!$this->is64Bit()) {
            $this->markTestSkipped('The PHP must be compiled in 64 bit mode to run this test.');
        }
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

    protected function isGreaterOrEqualThanIcuVersion($version)
    {
        $version = $this->normalizeIcuVersion($version);
        $icuVersion = $this->normalizeIcuVersion($this->getIntlExtensionIcuVersion());

        return $icuVersion >= $version;
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

        ob_start();
        phpinfo(INFO_MODULES);
        $output = ob_get_clean();

        preg_match('/^ICU version => (.*)$/m', $output, $matches);
        self::$icuVersion = $matches[1];

        return self::$icuVersion;
    }
}
