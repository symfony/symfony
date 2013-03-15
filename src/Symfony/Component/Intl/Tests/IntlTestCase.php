<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Intl\Tests;

use Symfony\Component\Intl\Intl;
use Symfony\Component\Intl\Util\IcuVersion;
use Symfony\Component\Intl\Util\Version;

/**
 * Base test case for the Intl component.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
abstract class IntlTestCase extends \PHPUnit_Framework_TestCase
{
    protected function setUp()
    {
        // Always use stub data for testing to have consistent results
        Intl::setDataSource(Intl::STUB);
    }

    protected function is32Bit()
    {
        return PHP_INT_SIZE == 4;
    }

    protected function is64Bit()
    {
        return PHP_INT_SIZE == 8;
    }

    protected function skipIfIntlExtensionNotLoaded()
    {
        if (!Intl::isExtensionLoaded()) {
            $this->markTestSkipped('The intl extension is not available.');
        }
    }

    protected function skipIfInsufficientIcuVersion()
    {
        if (IcuVersion::compare(Intl::getIcuVersion(), Intl::getIcuStubVersion(), '!=', $precision = 1)) {
            $this->markTestSkipped('Please change ICU version to ' . Intl::getIcuStubVersion());
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
}
