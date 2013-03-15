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
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class IntlTestHelper
{
    /**
     * Prepares the given test case to use the Intl component.
     *
     * @param \PhpUnit_Framework_TestCase $testCase
     */
    public static function setUp(\PhpUnit_Framework_TestCase $testCase)
    {
        if (!Intl::isExtensionLoaded()) {
            $testCase->markTestSkipped('The intl extension is not available.');
        }

        if (IcuVersion::compare(Intl::getIcuVersion(), Intl::getIcuStubVersion(), '!=', $precision = 1)) {
            $testCase->markTestSkipped('Please change ICU version to ' . Intl::getIcuStubVersion());
        }

        Intl::setDataSource(Intl::STUB);

        \Locale::setDefault('en');
    }
    /**
     * Must not be instantiated.
     */
    private function __construct() {}
}
