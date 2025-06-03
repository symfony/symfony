<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Tests\Messenger\Serializer\Normalizer;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Scheduler\Messenger\Serializer\Normalizer\SchedulerTriggerNormalizer;
use Symfony\Component\Scheduler\Trigger\CallbackTrigger;
use Symfony\Component\Scheduler\Trigger\PeriodicalTrigger;
use Symfony\Component\Scheduler\Trigger\TriggerInterface;

class SchedulerTriggerNormalizerTest extends TestCase
{
    private SchedulerTriggerNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = new SchedulerTriggerNormalizer();
    }

    /**
     * @dataProvider normalizeProvider
     */
    public function testNormalize(mixed $data, mixed $expected)
    {
        self::assertSame($expected, $this->normalizer->normalize($data));
    }

    public static function normalizeProvider(): iterable
    {
        yield 'CallbackTrigger' => [new CallbackTrigger(fn () => null, 'test1'), 'test1'];
        yield 'PeriodicalTrigger' => [new PeriodicalTrigger(5), 'every 5 seconds'];
    }

    /**
     * @dataProvider supportsNormalizationProvider
     */
    public function testSupportsNormalization(mixed $data, array $context, bool $expected)
    {
        self::assertSame($expected, $this->normalizer->supportsNormalization($data, 'json', $context));
    }

    public static function supportsNormalizationProvider(): iterable
    {
        yield 'CallbackTrigger, messenger context' => [new CallbackTrigger(fn () => null, 'test1'), ['messenger_serialization' => true], true];
        yield 'CallbackTrigger, normal context' => [new CallbackTrigger(fn () => null, 'test1'), [], false];
        yield 'PeriodicalTrigger, messenger context' => [new PeriodicalTrigger(5), ['messenger_serialization' => true], true];
        yield 'PeriodicalTrigger, normal context' => [new PeriodicalTrigger(5), [], false];
        yield 'stdClass, messenger context' => [new \stdClass(), ['messenger_serialization' => true], false];
        yield 'stdClass, normal context' => [new \stdClass(), [], false];
    }

    /**
     * @dataProvider supportsDenormalizationProvider
     */
    public function testSupportsDenormalization(mixed $data, string $type, array $context, bool $expected)
    {
        self::assertSame($expected, $this->normalizer->supportsDenormalization($data, $type, 'json', $context));
    }

    public static function supportsDenormalizationProvider(): iterable
    {
        yield 'unknown type' => ['test', \stdClass::class, ['messenger_serialization' => true], false];
        yield 'string, messenger context' => ['test', TriggerInterface::class, ['messenger_serialization' => true], true];
        yield 'string, normal context' => ['test', TriggerInterface::class, [], false];
        yield 'array, messenger context' => [['a' => 'b'], TriggerInterface::class, ['messenger_serialization' => true], false];
        yield 'array, normal context' => [['a' => 'b'], TriggerInterface::class, [], false];
    }

    public function testDenormalize()
    {
        $trigger = $this->normalizer->denormalize('every 5 seconds', TriggerInterface::class);
        self::assertSame('every 5 seconds', (string) $trigger);
    }
}
