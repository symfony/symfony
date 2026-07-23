<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests\Transport\Serialization;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\MessageDecodingFailedException;
use Symfony\Component\Messenger\Stamp\NonSendableStampInterface;
use Symfony\Component\Messenger\Tests\Fixtures\DummyLegacySerializable;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessageEnum;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessageWithLegacySerializable;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;

// Force-load the deprecated-Serializable fixture under an error handler that swallows
// the PHP deprecation it triggers at class-declaration time.
$errorHandler = set_error_handler(static function (int $errno, string $errstr) use (&$errorHandler) {
    if (\E_DEPRECATED === $errno && str_contains($errstr, 'Serializable interface')) {
        return true;
    }

    return $errorHandler ? $errorHandler(...\func_get_args()) : false;
});

try {
    class_exists(DummyLegacySerializable::class);
} finally {
    restore_error_handler();
}

class PhpSerializerTest extends TestCase
{
    public function testEncodedIsDecodable()
    {
        $serializer = $this->createPhpSerializer();

        $envelope = new Envelope(new DummyMessage('Hello'));

        $encoded = $serializer->encode($envelope);
        $this->assertStringNotContainsString("\0", $encoded['body'], 'Does not contain the binary characters');
        $this->assertEquals($envelope, $serializer->decode($encoded));
    }

    public function testDecodingFailsWithMissingBodyKey()
    {
        $serializer = $this->createPhpSerializer();

        $envelope = $serializer->decode([]);

        $this->assertInstanceOf(MessageDecodingFailedException::class, $envelope->getMessage());
    }

    public function testDecodingFailsWithBadFormat()
    {
        $serializer = $this->createPhpSerializer();

        $envelope = $serializer->decode([
            'body' => '{"message": "bar"}',
        ]);

        $this->assertInstanceOf(MessageDecodingFailedException::class, $envelope->getMessage());
    }

    public function testDecodingFailsWithBadBase64Body()
    {
        $serializer = $this->createPhpSerializer();

        $envelope = $serializer->decode([
            'body' => 'x',
        ]);

        $this->assertInstanceOf(MessageDecodingFailedException::class, $envelope->getMessage());
    }

    public function testDecodingFailsWithBadClass()
    {
        $serializer = $this->createPhpSerializer();

        $envelope = $serializer->decode([
            'body' => 'O:13:"ReceivedSt0mp":0:{}',
        ]);

        $this->assertInstanceOf(MessageDecodingFailedException::class, $envelope->getMessage());
    }

    public function testDecodingFailsForPropertyTypeMismatch()
    {
        $serializer = $this->createPhpSerializer();
        $encodedEnvelope = $serializer->encode(new Envelope(new DummyMessage('true')));
        // Simulate a change of property type in the code base
        $encodedEnvelope['body'] = str_replace('s:4:\"true\"', 'b:1', $encodedEnvelope['body']);

        $envelope = $serializer->decode($encodedEnvelope);

        $this->assertInstanceOf(MessageDecodingFailedException::class, $envelope->getMessage());
    }

    public function testEncodedSkipsNonEncodeableStamps()
    {
        $serializer = $this->createPhpSerializer();

        $envelope = new Envelope(new DummyMessage('Hello'), [
            new DummyPhpSerializerNonSendableStamp(),
        ]);

        $encoded = $serializer->encode($envelope);
        $this->assertStringNotContainsString('DummyPhpSerializerNonSendableStamp', $encoded['body']);
    }

    public function testNonUtf8IsBase64Encoded()
    {
        $serializer = $this->createPhpSerializer();

        $envelope = new Envelope(new DummyMessage("\xE9"));

        $encoded = $serializer->encode($envelope);
        $this->assertTrue((bool) preg_match('//u', $encoded['body']), 'Encodes non-UTF8 payloads');
        $this->assertEquals($envelope, $serializer->decode($encoded));
    }

    public function testGetMessageType()
    {
        $serializer = $this->createPhpSerializer();

        $this->assertSame(DummyMessage::class, $serializer->getMessageType($serializer->encode(new Envelope(new DummyMessage('Hello')))));
        // base64-encoded body (non-UTF8 payload)
        $this->assertSame(DummyMessage::class, $serializer->getMessageType($serializer->encode(new Envelope(new DummyMessage("\xE9")))));
    }

    public function testGetMessageTypeWithLegacySerializableProperty()
    {
        $serializer = $this->createPhpSerializer();

        $envelope = new Envelope(new DummyMessageWithLegacySerializable(new DummyLegacySerializable('15.98')));
        $encoded = $serializer->encode($envelope);

        $errors = [];
        set_error_handler(static function ($_, $msg) use (&$errors) {
            $errors[] = $msg;

            return true;
        });

        try {
            $type = $serializer->getMessageType($encoded);
        } finally {
            restore_error_handler();
        }

        $this->assertSame(DummyMessageWithLegacySerializable::class, $type);
        $this->assertSame([], $errors, 'getMessageType() should not emit PHP warnings.');
    }

    public function testGetMessageTypeReturnsNullForUndeterminableBody()
    {
        $serializer = $this->createPhpSerializer();

        $this->assertNull($serializer->getMessageType([]));
        $this->assertNull($serializer->getMessageType(['body' => '']));
        $this->assertNull($serializer->getMessageType(['body' => 'definitely not serialized data']));
        $this->assertNull($serializer->getMessageType(['body' => addslashes(serialize(123))]));
        $this->assertNull($serializer->getMessageType(['body' => addslashes(serialize(new DummyMessage('Hello')))]));
    }

    #[DataProvider('provideAdversarialBodies')]
    public function testGetMessageTypeRejectsOrMatchesPhpForAdversarialBody(string $body, ?string $expected)
    {
        $this->assertSame($expected, $this->createPhpSerializer()->getMessageType(['body' => addslashes($body)]));
    }

    public static function provideAdversarialBodies(): iterable
    {
        $stampsKey = 's:44:"'."\0".'Symfony\\Component\\Messenger\\Envelope'."\0".'stamps";';
        $messageKey = 's:45:"'."\0".'Symfony\\Component\\Messenger\\Envelope'."\0".'message";';
        $envelopePrefix = 'O:36:"Symfony\\Component\\Messenger\\Envelope"';

        yield 'reversed property order: still finds message by key' => [
            $envelopePrefix.':2:{'.$messageKey.'O:8:"stdClass":0:{}'.$stampsKey.'a:0:{}}',
            'stdClass',
        ];

        yield 'duplicate message keys: returns last value (PHP semantics)' => [
            $envelopePrefix.':3:{'.$stampsKey.'a:0:{}'.$messageKey.'O:5:"First":0:{}'.$messageKey.'O:6:"Second":0:{}}',
            'Second',
        ];

        $enumClass = DummyMessageEnum::class;
        $enumPayload = $enumClass.':A';
        yield 'enum message: returns class name without case suffix' => [
            $envelopePrefix.':2:{'.$stampsKey.'a:0:{}'.$messageKey.'E:'.\strlen($enumPayload).':"'.$enumPayload.'";}',
            $enumClass,
        ];

        yield 'stamp string value containing message-key bytes: ignored' => (static function () use ($stampsKey, $messageKey, $envelopePrefix) {
            $decoy = $messageKey.'O:5:"PWNED":0:{}';
            $stamps = 'a:1:{i:0;s:'.\strlen($decoy).':"'.$decoy.'";}';

            return [
                $envelopePrefix.':2:{'.$stampsKey.$stamps.$messageKey.'O:6:"Honest":0:{}}',
                'Honest',
            ];
        })();

        yield 'C: opaque payload containing message-key bytes: ignored' => (static function () use ($stampsKey, $messageKey, $envelopePrefix) {
            $opaque = $messageKey.'O:8:"PWNED999":0:{}';
            $stamps = 'a:1:{i:0;C:6:"FakeOp":'.\strlen($opaque).':{'.$opaque.'}}';

            return [
                $envelopePrefix.':2:{'.$stampsKey.$stamps.$messageKey.'O:7:"Honest1":0:{}}',
                'Honest1',
            ];
        })();

        yield 'trailing garbage after envelope: rejected' => [
            $envelopePrefix.':2:{'.$stampsKey.'a:0:{}'.$messageKey.'O:8:"stdClass":0:{}}garbage',
            null,
        ];

        yield 'wrong declared property count vs actual pairs: rejected' => [
            $envelopePrefix.':3:{'.$stampsKey.'a:0:{}'.$messageKey.'O:8:"stdClass":0:{}}',
            null,
        ];

        yield 'message value is a reference to the envelope itself: safely resolves to Envelope' => [
            // PHP unserialize() resolves r:1; to the envelope itself; the resulting class
            // name (Envelope) is harmless when fed back to SigningSerializer because
            // Envelope is virtually never registered as a signed message type.
            $envelopePrefix.':2:{'.$stampsKey.'a:0:{}'.$messageKey.'r:1;}',
            Envelope::class,
        ];

        yield 'capital-S binary-string format (not produced by serialize()): rejected' => [
            $envelopePrefix.':2:{'.$stampsKey.'a:0:{}S:45:"\\00Symfony\\\\Component\\\\Messenger\\\\Envelope\\00message";O:6:"Signed":0:{}}',
            null,
        ];

        yield 'no message key in envelope: returns null' => [
            $envelopePrefix.':1:{'.$stampsKey.'a:0:{}}',
            null,
        ];

        $protectedMessageKey = 's:10:"'."\0*\0".'message";';
        $publicMessageKey = 's:7:"message";';

        yield 'protected-form message key: scanner recognizes it (PHP routes it to $message)' => [
            $envelopePrefix.':2:{'.$stampsKey.'a:0:{}'.$protectedMessageKey.'O:8:"stdClass":0:{}}',
            'stdClass',
        ];

        yield 'public-form message key: scanner recognizes it (PHP routes it to $message)' => [
            $envelopePrefix.':2:{'.$stampsKey.'a:0:{}'.$publicMessageKey.'O:8:"stdClass":0:{}}',
            'stdClass',
        ];

        yield 'private-form message key with unrelated class name: not recognized' => [
            // PHP only routes "\0<ClassName>\0prop" to Envelope::$prop when <ClassName>
            // matches the declaring class. So "\0Unrelated\0message" is just a stray
            // dynamic-like property; Envelope::$message stays uninitialized.
            $envelopePrefix.':2:{'.$stampsKey.'a:0:{}s:17:"'."\0".'Unrelated'."\0".'message";O:8:"stdClass":0:{}}',
            null,
        ];

        yield 'signature bypass attempt: scanner must catch the public-form last-wins override' => [
            // PHP last-wins → $message = Signed; scanner that ignored public-form key
            // would return "Unsigned" and let SigningSerializer fall through.
            $envelopePrefix.':3:{'.$stampsKey.'a:0:{}'.$messageKey.'O:8:"Unsigned":0:{}'.$publicMessageKey.'O:6:"Signed":0:{}}',
            'Signed',
        ];

        // Deeply nested stamps: PHP unserialize() refuses past unserialize_max_depth (4096
        // by default). The scanner must refuse rather than burning CPU descending recursively.
        $deepNesting = str_repeat('a:1:{i:0;', 10_000).'s:0:"";'.str_repeat('}', 10_000);
        yield 'deeply nested stamps (DoS guard)' => [
            $envelopePrefix.':2:{'.$stampsKey.$deepNesting.$messageKey.'O:8:"stdClass":0:{}}',
            null,
        ];
    }

    protected function createPhpSerializer(): PhpSerializer
    {
        return new PhpSerializer();
    }
}

class DummyPhpSerializerNonSendableStamp implements NonSendableStampInterface
{
}
