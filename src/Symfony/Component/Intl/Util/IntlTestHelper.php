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
     * @param \PhpUnit_Framework_TestCase $testCase
     */
    public static function requireIntl(\PhpUnit_Framework_TestCase $testCase)
    {
        // We only run tests if the version is *one specific version*.
        // This condition is satisfied if
        //
        //   * the intl extension is loaded with version Intl::getIcuStubVersion()
        //   * the intl extension is not loaded

        if (IcuVersion::compare(Intl::getIcuVersion(), Intl::getIcuStubVersion(), '!=', $precision = 1)) {
            $testCase->markTestSkipped('Please change ICU version to ' . Intl::getIcuStubVersion());
        }

        if (IcuVersion::compare(Intl::getIcuDataVersion(), Intl::getIcuStubVersion(), '!=', $precision = 1)) {
            $testCase->markTestSkipped('Please change the Icu component to version 1.0.x or 1.' . IcuVersion::normalize(Intl::getIcuStubVersion(), 1) . '.x');
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
     * @param \PhpUnit_Framework_TestCase $testCase
     */
    public static function requireFullIntl(\PhpUnit_Framework_TestCase $testCase)
    {
        // We only run tests if the intl extension is loaded...
        if (!Intl::isExtensionLoaded()) {
            $testCase->markTestSkipped('The intl extension is not available.');
        }

        // ... and only if the version is *one specific version* ...
        if (IcuVersion::compare(Intl::getIcuVersion(), Intl::getIcuStubVersion(), '!=', $precision = 1)) {
            $testCase->markTestSkipped('Please change ICU version to ' . Intl::getIcuStubVersion());
        }

        // ... and only if the data in the Icu component matches that version.
        if (IcuVersion::compare(Intl::getIcuDataVersion(), Intl::getIcuStubVersion(), '!=', $precision = 1)) {
            $testCase->markTestSkipped('Please change the Icu component to version 1.0.x or 1.' . IcuVersion::normalize(Intl::getIcuStubVersion(), 1) . '.x');
        }

        // Normalize the default locale in case this is not done explicitly
        // in the test
        \Locale::setDefault('en');

        // Consequently, tests will
        //
        //   * run only for one ICU version (see Intl::getIcuStubVersion())
        //     there is no need to add control structures to your tests that
        //     change the test depending on the ICU version.
        //   * always use the C intl classes
        //   * always use the binary resource bundles (any locale is allowed)
    }

    /**
     * Skips the test unless the current system has a 32bit architecture.
     *
     * @param \PhpUnit_Framework_TestCase $testCase
     */
    public static function require32Bit(\PhpUnit_Framework_TestCase $testCase)
    {
        if (4 !== PHP_INT_SIZE) {
            $testCase->markTestSkipped('PHP must be compiled in 32 bit mode to run this test');
        }
    }

    /**
     * Skips the test unless the current system has a 64bit architecture.
     *
     * @param \PhpUnit_Framework_TestCase $testCase
     */
    public static function require64Bit(\PhpUnit_Framework_TestCase $testCase)
    {
        if (8 !== PHP_INT_SIZE) {
            $testCase->markTestSkipped('PHP must be compiled in 64 bit mode to run this test');
        }
    }

    /**
     * Must not be instantiated.
     */
    private function __construct() {}
}
