<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mime\Tests\Part;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mime\Part\SMimePart;

class SMimePartTestToStringGadget
{
    public static bool $fired = false;

    public function __toString(): string
    {
        self::$fired = true;

        return '';
    }
}

class SMimePartTest extends TestCase
{
    #[DataProvider('provideTrampolineKeys')]
    public function testUnserializeRejectsObjectInTypedStringProperty(string $key)
    {
        $template = (new SMimePart('body content', 'application', 'pkcs7-mime', []))->__serialize();
        $template[$key] = new SMimePartTestToStringGadget();
        $payload = \sprintf('O:%d:"%s":%d:{', \strlen(SMimePart::class), SMimePart::class, \count($template));
        foreach ($template as $k => $v) {
            $payload .= serialize($k).serialize($v);
        }
        $payload .= '}';
        SMimePartTestToStringGadget::$fired = false;

        try {
            unserialize($payload);
            $this->fail('Expected BadMethodCallException.');
        } catch (\BadMethodCallException $e) {
        }

        $this->assertFalse(SMimePartTestToStringGadget::$fired, '__toString gadget must not fire during unserialize');
    }

    public static function provideTrampolineKeys(): iterable
    {
        yield ['body'];
        yield ['type'];
        yield ['subtype'];
    }

    #[DataProvider('provideNonStringValues')]
    public function testUnserializeRejectsNonStringValue(string $key, mixed $value)
    {
        $template = (new SMimePart('body content', 'application', 'pkcs7-mime', []))->__serialize();
        $template[$key] = $value;
        $payload = \sprintf('O:%d:"%s":%d:{', \strlen(SMimePart::class), SMimePart::class, \count($template));
        foreach ($template as $k => $v) {
            $payload .= serialize($k).serialize($v);
        }

        $this->expectException(\BadMethodCallException::class);

        unserialize($payload.'}');
    }

    public static function provideNonStringValues(): iterable
    {
        foreach (['type', 'subtype'] as $key) {
            yield $key.' int' => [$key, 123];
            yield $key.' object' => [$key, new \stdClass()];
            yield $key.' null' => [$key, null];
        }
    }

    #[IgnoreDeprecations]
    #[Group('legacy')]
    #[DataProvider('provideSubclassesImplementingSleep')]
    public function testSerializeSubclassImplementingSleep(string $class)
    {
        $part = new $class('state');
        $part->getHeaders()->addTextHeader('X-Foo', 'bar');

        $this->expectUserDeprecationMessage(\sprintf('Since symfony/mime 7.4: Passing extra keys to "%s::__unserialize()" is deprecated, populate properties in "%s::__unserialize()" instead.', SMimePart::class, $class));

        $unserialized = unserialize(serialize($part));

        $this->assertSame('state', $unserialized->getState());
        $this->assertSame($part->toString(), $unserialized->toString());
    }

    public static function provideSubclassesImplementingSleep(): iterable
    {
        yield [SMimePartTestSleepPart::class];
        yield [SMimePartTestWakeupPart::class];
    }
}

class SMimePartTestSleepPart extends SMimePart
{
    protected ?string $state = null;

    public function __construct(string $state)
    {
        parent::__construct('content', 'application', 'pkcs7-mime', ['smime-type' => 'enveloped-data']);

        $this->state = $state;
    }

    public function getState(): ?string
    {
        return $this->state;
    }

    public function __sleep(): array
    {
        return [...parent::__sleep(), 'state'];
    }
}

class SMimePartTestWakeupPart extends SMimePartTestSleepPart
{
    public function __wakeup(): void
    {
        parent::__wakeup();
    }
}
