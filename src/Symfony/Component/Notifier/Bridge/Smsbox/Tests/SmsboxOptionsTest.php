<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Smsbox\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Notifier\Bridge\Smsbox\SmsboxOptions;
use Symfony\Component\Notifier\Exception\InvalidArgumentException;

class SmsboxOptionsTest extends TestCase
{
    public function testSmsboxOptions()
    {
        $smsboxOptions = (new SmsboxOptions())
            ->mode(SmsboxOptions::MESSAGE_MODE_EXPERT)
            ->sender('SENDER')
            ->strategy(SmsboxOptions::MESSAGE_STRATEGY_MARKETING)
            ->charset(SmsboxOptions::MESSAGE_CHARSET_UTF8)
            ->udh(SmsboxOptions::MESSAGE_UDH_DISABLED_CONCAT)
            ->maxParts(2)
            ->validity(100)
            ->destIso('FR');

        self::assertSame([
            'mode' => 'Expert',
            'sender' => 'SENDER',
            'strategy' => 4,
            'charset' => 'utf-8',
            'udh' => 0,
            'max_parts' => 2,
            'validity' => 100,
            'dest_iso' => 'FR',
        ], $smsboxOptions->toArray());
    }

    public function testSmsboxOptionsInvalidMode()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The message mode "XXXXXX" is not supported; supported message modes are: "Standard", "Expert", "Reponse"');

        $smsboxOptions = (new SmsboxOptions())
            ->mode('XXXXXX')
            ->sender('SENDER')
            ->strategy(SmsboxOptions::MESSAGE_STRATEGY_MARKETING);
    }

    public function testSmsboxOptionsInvalidStrategy()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The message strategy "10" is not supported; supported strategies types are: "1", "2", "3", "4"');

        $smsboxOptions = (new SmsboxOptions())
            ->mode(SmsboxOptions::MESSAGE_MODE_STANDARD)
            ->sender('SENDER')
            ->strategy(10);
    }

    public function testSmsboxOptionsInvalidDestIso()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('destIso must be the ISO 3166-1 alpha 2 on two uppercase characters.');

        $smsboxOptions = (new SmsboxOptions())
            ->mode(SmsboxOptions::MESSAGE_MODE_EXPERT)
            ->sender('SENDER')
            ->strategy(SmsboxOptions::MESSAGE_STRATEGY_MARKETING)
            ->destIso('X1');
    }
}
