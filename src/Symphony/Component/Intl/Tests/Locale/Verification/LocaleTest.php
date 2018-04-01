<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Intl\Tests\Locale\Verification;

use Symphony\Component\Intl\Tests\Locale\AbstractLocaleTest;
use Symphony\Component\Intl\Util\IntlTestHelper;

/**
 * Verifies that {@link AbstractLocaleTest} matches the behavior of the
 * {@link Locale} class with a specific version of ICU.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class LocaleTest extends AbstractLocaleTest
{
    protected function setUp()
    {
        IntlTestHelper::requireFullIntl($this, false);

        parent::setUp();
    }

    protected function call($methodName)
    {
        $args = array_slice(func_get_args(), 1);

        return call_user_func_array(array('Locale', $methodName), $args);
    }
}
