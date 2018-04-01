<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Intl\Tests\Collator\Verification;

use Symphony\Component\Intl\Tests\Collator\AbstractCollatorTest;
use Symphony\Component\Intl\Util\IntlTestHelper;

/**
 * Verifies that {@link AbstractCollatorTest} matches the behavior of the
 * {@link \Collator} class in a specific version of ICU.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class CollatorTest extends AbstractCollatorTest
{
    protected function setUp()
    {
        IntlTestHelper::requireFullIntl($this, false);

        parent::setUp();
    }

    protected function getCollator($locale)
    {
        return new \Collator($locale);
    }
}
