<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Smsbox\Tests\Enum;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Notifier\Bridge\Smsbox\Enum\Mode;

class ModeTest extends TestCase
{
    #[DataProvider('provideModeValues')]
    public function testModeValues(Mode $mode, string $value)
    {
        self::assertSame($value, $mode->value);
    }

    /**
     * @return iterable<array{Mode, string}>
     */
    public static function provideModeValues(): iterable
    {
        yield [Mode::Standard, 'Standard'];
        yield [Mode::Expert, 'Expert'];
        yield [Mode::Response, 'Reponse']; // not a typo, see https://en.smsbox.net/docs/doc-API-SMSBOX-1.1-EN.pdf
    }
}
