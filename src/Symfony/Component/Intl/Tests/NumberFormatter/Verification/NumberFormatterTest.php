<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Intl\Tests\NumberFormatter\Verification;

use Symfony\Component\Intl\Tests\NumberFormatter\AbstractNumberFormatterTest;
use Symfony\Component\Intl\Util\IntlTestHelper;

/**
 * Note that there are some values written like -2147483647 - 1. This is the lower 32bit int max and is a known
 * behavior of PHP.
 */
class NumberFormatterTest extends AbstractNumberFormatterTest
{
    protected function setUp(): void
    {
        IntlTestHelper::requireFullIntl($this, '55.1');

        parent::setUp();
    }

    public function testCreate()
    {
        $this->assertInstanceOf('\NumberFormatter', \NumberFormatter::create('en', \NumberFormatter::DECIMAL));
    }

    public function testGetTextAttribute()
    {
        IntlTestHelper::requireFullIntl($this, '57.1');

        parent::testGetTextAttribute();
    }

    protected function getNumberFormatter(?string $locale = 'en', string $style = null, string $pattern = null): \NumberFormatter
    {
        return new \NumberFormatter($locale, $style, $pattern);
    }

    protected function getIntlErrorMessage(): string
    {
        return intl_get_error_message();
    }

    protected function getIntlErrorCode(): int
    {
        return intl_get_error_code();
    }

    protected function isIntlFailure($errorCode): bool
    {
        return intl_is_failure($errorCode);
    }
}
