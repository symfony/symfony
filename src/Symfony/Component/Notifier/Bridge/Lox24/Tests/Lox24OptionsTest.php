<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Lox24\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Notifier\Bridge\Lox24\Lox24Options;
use Symfony\Component\Notifier\Bridge\Lox24\Type;
use Symfony\Component\Notifier\Bridge\Lox24\VoiceLanguage;

/**
 * @author Andrei Lebedev <andrew.lebedev@gmail.com>
 */
class Lox24OptionsTest extends TestCase
{
    public function testDeliveryAtWithNotNull()
    {
        $options = (new Lox24Options())->deliveryAt((new \DateTimeImmutable())->setTimestamp(123));
        $this->assertSame(123, $options->toArray()['delivery_at']);
    }

    public function testDeliveryWithNull()
    {
        $options = (new Lox24Options())->deliveryAt(null);
        $this->assertSame(0, $options->toArray()['delivery_at']);
    }

    public function testVoiceLangAuto()
    {
        $options = (new Lox24Options())->voiceLanguage(VoiceLanguage::Auto);
        $this->assertArrayNotHasKey('voice_lang', $options->toArray());
    }

    public function testVoiceLangValid()
    {
        $options = (new Lox24Options())->voiceLanguage(VoiceLanguage::English);
        $this->assertSame('EN', $options->toArray()['voice_lang']);
    }

    public function testTextDelete()
    {
        $options = (new Lox24Options())->deleteTextAfterSending(true);
        $this->assertTrue($options->toArray()['delete_text']);
        $options->deleteTextAfterSending(false);
        $this->assertFalse($options->toArray()['delete_text']);
    }

    public function testRecipientId()
    {
        $options = (new Lox24Options());
        $this->assertNull($options->getRecipientId());
    }

    public function testCallbackData()
    {
        $options = (new Lox24Options())->callbackData('test');
        $this->assertSame('test', $options->toArray()['callback_data']);
    }

    public function testTypeSms()
    {
        $options = (new Lox24Options())->type(Type::Sms);
        $this->assertSame('sms', $options->toArray()['type']);
    }

    public function testTypeVoice()
    {
        $options = (new Lox24Options())->type(Type::Voice);
        $this->assertSame('voice', $options->toArray()['type']);
    }
}
