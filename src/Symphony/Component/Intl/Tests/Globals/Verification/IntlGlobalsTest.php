<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Intl\Tests\Globals\Verification;

use Symphony\Component\Intl\Tests\Globals\AbstractIntlGlobalsTest;
use Symphony\Component\Intl\Util\IntlTestHelper;

/**
 * Verifies that {@link AbstractIntlGlobalsTest} matches the behavior of the
 * intl functions with a specific version of ICU.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class IntlGlobalsTest extends AbstractIntlGlobalsTest
{
    protected function setUp()
    {
        IntlTestHelper::requireFullIntl($this, false);

        parent::setUp();
    }

    protected function getIntlErrorName($errorCode)
    {
        return intl_error_name($errorCode);
    }
}
