<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Intl\Util;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Intl\Intl;

/**
 * Helper class for preparing test cases that rely on the Intl component.
 *
 * Any test that tests functionality relying on either the intl classes or
 * the resource bundle data should call either of the methods
 * {@link requireIntl()} or {@link requireFullIntl()}. Calling
 * {@link requireFullIntl()} is only necessary if you use functionality in the
 * test that is not provided by the stub intl implementation.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class IntlTestHelper
{
    /**
     * Should be called before tests that work fine with the stub implementation.
     *
     * @return void
     */
    public static function requireIntl(TestCase $testCase, string $minimumIcuVersion = null)
    {
        $minimumIcuVersion ??= Intl::getIcuStubVersion();

        // We only run tests if the version is *one specific version*.
        // This condition is satisfied if
        //
        //   * the intl extension is loaded with version Intl::getIcuStubVersion()
        //   * the intl extension is not loaded

        if ($minimumIcuVersion && IcuVersion::compare(Intl::getIcuVersion(), $minimumIcuVersion, '<', 1)) {
            $testCase->markTestSkipped('ICU version '.$minimumIcuVersion.' is required.');
        }

        // Normalize the default locale in case this is not done explicitly
        // in the test
        \Locale::setDefault('en');

        // Consequently, tests will
        //
        //   * run only for one ICU version (see Intl::getIcuStubVersion())
        //     there is no need to add control structures to your tests that
        //     change the test depending on the ICU version.
        //
        // Tests should only rely on functionality that is implemented in the
        // stub classes.
    }

    /**
     * Should be called before tests that require a feature-complete intl
     * implementation.
     *
     * @return void
     */
    public static function requireFullIntl(TestCase $testCase, string $minimumIcuVersion = null)
    {
        // We only run tests if the intl extension is loaded...
        if (!Intl::isExtensionLoaded()) {
            $testCase->markTestSkipped('Extension intl is required.');
        }

        self::requireIntl($testCase, $minimumIcuVersion);

        // Consequently, tests will
        //
        //   * run only for one ICU version (see Intl::getIcuStubVersion())
        //     there is no need to add control structures to your tests that
        //     change the test depending on the ICU version.
        //   * always use the C intl classes
    }

    /**
     * Skips the test unless the current system has a 32bit architecture.
     *
     * @return void
     */
    public static function require32Bit(TestCase $testCase)
    {
        if (4 !== \PHP_INT_SIZE) {
            $testCase->markTestSkipped('PHP 32 bit is required.');
        }
    }

    /**
     * Skips the test unless the current system has a 64bit architecture.
     *
     * @return void
     */
    public static function require64Bit(TestCase $testCase)
    {
        if (8 !== \PHP_INT_SIZE) {
            $testCase->markTestSkipped('PHP 64 bit is required.');
        }
    }

    /**
     * Must not be instantiated.
     */
    private function __construct()
    {
    }
}
