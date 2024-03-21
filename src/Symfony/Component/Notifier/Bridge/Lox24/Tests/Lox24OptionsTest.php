<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use PHPUnit\Framework\TestCase;
use Symfony\Component\Notifier\Bridge\Lox24\Lox24Options;

/**
 * @author Andrei Lebedev <andrew.lebedev@gmail.com>
 */
class Lox24OptionsTest extends TestCase
{

    public function testDeliveryAtWithNotNull(): void
    {
        $options = (new Lox24Options())->deliveryAt((new DateTimeImmutable())->setTimestamp(123));
        $this->assertSame(123, $options->toArray()['delivery_at']);
    }

    public function testDeliveryWithNull(): void
    {
        $options = (new Lox24Options())->deliveryAt(null);
        $this->assertSame(0, $options->toArray()['delivery_at']);
    }

    public function testVoiceLangNull(): void
    {
        $options = (new Lox24Options())->voiceLanguage(null);
        $this->assertNull($options->toArray()['voice_lang'] ?? null);
    }

    public function testVoiceLangInvalid(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'The language \'invalid\' is not supported; supported languages are: de, en, es, fr, it, auto.'
        );
        (new Lox24Options())->voiceLanguage('invalid');
    }

    public function testVoiceLangValidLower(): void
    {
        $options = (new Lox24Options())->voiceLanguage('en');
        $this->assertSame('en', $options->toArray()['voice_lang']);
    }

    public function testVoiceLangValidUpper(): void
    {
        $options = (new Lox24Options())->voiceLanguage('EN');
        $this->assertSame('en', $options->toArray()['voice_lang']);
    }

    public function testTextDelete(): void
    {
        $options = (new Lox24Options())->textDelete(true);
        $this->assertTrue($options->toArray()['is_text_delete']);
        $options->textDelete(false);
        $this->assertFalse($options->toArray()['is_text_delete']);
    }

    public function testRecipientId(): void
    {
        $options = (new Lox24Options());
        $this->assertNull($options->getRecipientId());
    }

    public function testCallbackData(): void
    {
        $options = (new Lox24Options())->callbackData('test');
        $this->assertSame('test', $options->toArray()['callback_data']);
    }

    public function testTypeSms(): void
    {
        $options = (new Lox24Options())->type('sms');
        $this->assertSame('sms', $options->toArray()['type']);
    }

    public function testTypeVoice(): void
    {
        $options = (new Lox24Options())->type('voice');
        $this->assertSame('voice', $options->toArray()['type']);
    }

    public function testTypeInvalid(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid type: fax');
        (new Lox24Options())->type('fax');
    }
}