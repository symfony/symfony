<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Symfony\Component\Notifier\Bridge\Lox24\Lox24Options;
use Symfony\Component\Notifier\Bridge\Lox24\Type;
use Symfony\Component\Notifier\Bridge\Lox24\VoiceLanguage;

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

    public function testVoiceLangAuto(): void
    {
        $options = (new Lox24Options())->voiceLanguage(VoiceLanguage::Auto);
        $this->assertNull($options->toArray()['voice_lang'] ?? null);
    }

    public function testVoiceLangValid(): void
    {
        $options = (new Lox24Options())->voiceLanguage(VoiceLanguage::English);
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
        $options = (new Lox24Options())->type(Type::Sms);
        $this->assertSame('sms', $options->toArray()['type']);
    }

    public function testTypeVoice(): void
    {
        $options = (new Lox24Options())->type(Type::Voice);
        $this->assertSame('voice', $options->toArray()['type']);
    }

}