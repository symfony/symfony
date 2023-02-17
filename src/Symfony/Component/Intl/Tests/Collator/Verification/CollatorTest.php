<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Intl\Tests\Collator\Verification;

use Symfony\Component\Intl\Tests\Collator\AbstractCollatorTestCase;
use Symfony\Component\Intl\Util\IntlTestHelper;

/**
 * Verifies that {@link AbstractCollatorTestCase} matches the behavior of the
 * {@link \Collator} class in a specific version of ICU.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class CollatorTest extends AbstractCollatorTestCase
{
    protected function setUp(): void
    {
        IntlTestHelper::requireFullIntl($this, false);

        parent::setUp();
    }

    protected function getCollator(?string $locale): \Collator
    {
        return new \Collator($locale);
    }
}
