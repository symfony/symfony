<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests\Normalizer;

use PHPUnit\Framework\Attributes\RequiresFunction;
use PHPUnit\Framework\Attributes\RequiresPhp;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Exception\InvalidArgumentException;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Serializer\Exception\UnexpectedValueException;
use Symfony\Component\Serializer\Normalizer\ClosureNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\Tests\Fixtures\ConstExprClosureDummy;

#[RequiresFunction('deepclone_to_array')]
class ClosureNormalizerTest extends TestCase
{
    private ClosureNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = new ClosureNormalizer([ClosureNormalizer::ALLOW_NAMED_CALLABLES => true]);
    }

    protected function tearDown(): void
    {
        unset($this->normalizer);
    }

    public function testSupports()
    {
        self::assertTrue($this->normalizer->supportsNormalization(strlen(...)));
        self::assertFalse($this->normalizer->supportsNormalization('strlen'));
        self::assertTrue($this->normalizer->supportsDenormalization([], \Closure::class));
        self::assertFalse($this->normalizer->supportsDenormalization([], \stdClass::class));
        self::assertSame([\Closure::class => true], $this->normalizer->getSupportedTypes(null));
    }

    public function testNormalizeRejectsNonClosures()
    {
        $this->expectException(InvalidArgumentException::class);

        $this->normalizer->normalize('strlen');
    }

    public function testRoundTripOverAFunction()
    {
        $data = $this->normalizer->normalize(strlen(...));

        self::assertPureArray($data);

        $closure = $this->normalizer->denormalize($data, \Closure::class);

        self::assertSame(5, $closure('hello'));
    }

    public function testRoundTripOverAStaticMethod()
    {
        $data = $this->normalizer->normalize(ClosureNormalizerFixture::twice(...));
        $closure = $this->normalizer->denormalize($data, \Closure::class);

        self::assertSame(6, $closure(3));
    }

    public function testRoundTripOverABoundMethodKeepsTheObjectState()
    {
        $data = $this->normalizer->normalize((new ClosureNormalizerFixture(7))->add(...));
        $closure = $this->normalizer->denormalize($data, \Closure::class);

        self::assertSame(10, $closure(3));
    }

    #[RequiresPhp('>=8.5.0')]
    public function testRoundTripOverAnAnonymousClosureDeclaredInAConstantExpression()
    {
        $closure = (new \ReflectionClass(ConstExprClosureDummy::class))->getAttributes(ConstExprClosureDummy::class)[0]->getArguments()[0];

        // a declaration-site reference needs no ALLOW_NAMED_CALLABLES
        $normalizer = new ClosureNormalizer();

        $data = $normalizer->normalize($closure);
        self::assertPureArray($data);

        $restored = $normalizer->denormalize($data, \Closure::class);

        self::assertSame(9, $restored(3));
    }

    public function testTheNormalizedFormSurvivesJsonEncoding()
    {
        $data = $this->normalizer->normalize(strlen(...));
        $data = json_decode(json_encode($data, \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR);

        $closure = $this->normalizer->denormalize($data, \Closure::class);

        self::assertSame(5, $closure('hello'));
    }

    public function testStaticClosuresOutsideAConstantExpressionAreRefused()
    {
        $this->expectException(UnexpectedValueException::class);

        $this->normalizer->normalize(static fn (int $value): int => 3 * $value);
    }

    public function testCapturingClosuresAreRefused()
    {
        $greeting = 'hello';

        $this->expectException(UnexpectedValueException::class);

        $this->normalizer->normalize(static fn (string $name) => $greeting.' '.$name);
    }

    public function testRuntimeClosuresAreRefusedWithAnExplanation()
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('Cannot normalize the closure declared in "'.__FILE__.'" on line '.(__LINE__ + 2).': only first-class callables');

        $this->normalizer->normalize(static fn (int $value): int => 3 * $value);
    }

    public function testNamedCallablesAreRefusedByDefault()
    {
        $this->expectException(UnexpectedValueException::class);

        (new ClosureNormalizer())->normalize(strlen(...));
    }

    public function testDenormalizingANamedCallableIsRefusedByDefault()
    {
        $data = $this->normalizer->normalize(strlen(...));

        $this->expectException(NotNormalizableValueException::class);

        (new ClosureNormalizer())->denormalize($data, \Closure::class);
    }

    public function testDisallowedClassesAreRefused()
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessageMatches('/is not allowed/');

        $this->normalizer->normalize(strlen(...), null, [ClosureNormalizer::ALLOWED_CLASSES => []]);
    }

    public function testAllowingOnlyClosurePermitsFunctionAndStaticMethodTargets()
    {
        $context = [ClosureNormalizer::ALLOWED_CLASSES => [\Closure::class]];

        $data = $this->normalizer->normalize(strlen(...), null, $context);
        self::assertSame(5, $this->normalizer->denormalize($data, \Closure::class, null, $context)('hello'));

        $data = $this->normalizer->normalize(ClosureNormalizerFixture::twice(...), null, $context);
        self::assertSame(6, $this->normalizer->denormalize($data, \Closure::class, null, $context)(3));
    }

    public function testAllowingOnlyClosureRefusesABoundClosure()
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessageMatches('/"'.preg_quote(ClosureNormalizerFixture::class, '/').'" is not allowed/');

        $this->normalizer->normalize((new ClosureNormalizerFixture(7))->add(...), null, [ClosureNormalizer::ALLOWED_CLASSES => [\Closure::class]]);
    }

    public function testDenormalizeRejectsNonArrays()
    {
        $this->expectException(NotNormalizableValueException::class);
        $this->expectExceptionMessage('The data is not an array');

        $this->normalizer->denormalize('strlen', \Closure::class);
    }

    public function testDenormalizeRejectsAPayloadThatIsNotAClosure()
    {
        $data = $this->normalizer->normalize(strlen(...));
        $data['value'] = 'strlen';

        $this->expectException(NotNormalizableValueException::class);
        $this->expectExceptionMessage('The data does not describe a closure.');

        $this->normalizer->denormalize($data, \Closure::class);
    }

    public function testAnObjectHoldingAClosureRoundTripsThroughJson()
    {
        $serializer = $this->createSerializer();

        $holder = new ClosureHolderFixture();
        $holder->handler = ClosureNormalizerFixture::twice(...);
        $holder->name = 'doubler';

        $json = $serializer->serialize($holder, 'json');
        $restored = $serializer->deserialize($json, ClosureHolderFixture::class, 'json');

        self::assertSame('doubler', $restored->name);
        self::assertSame(6, ($restored->handler)(3));
    }

    public function testAClosureIsDenormalizedThroughAPromotedConstructorArgument()
    {
        $serializer = $this->createSerializer();

        $json = $serializer->serialize(new PromotedClosureHolderFixture(ClosureNormalizerFixture::twice(...), 'doubler'), 'json');
        $restored = $serializer->deserialize($json, PromotedClosureHolderFixture::class, 'json');

        self::assertSame('doubler', $restored->name);
        self::assertSame(6, ($restored->handler)(3));
    }

    public function testAnObjectHoldingARuntimeClosureIsRefused()
    {
        $holder = new ClosureHolderFixture();
        $holder->handler = static fn (int $value): int => 3 * $value;
        $holder->name = 'tripler';

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('only first-class callables');

        $this->createSerializer()->serialize($holder, 'json');
    }

    public function testTheOptionsArePropagatedFromTheSerializerContext()
    {
        $serializer = $this->createSerializer(new ClosureNormalizer());
        $context = [ClosureNormalizer::ALLOW_NAMED_CALLABLES => true];

        $holder = new ClosureHolderFixture();
        $holder->handler = ClosureNormalizerFixture::twice(...);

        $json = $serializer->serialize($holder, 'json', $context);
        $restored = $serializer->deserialize($json, ClosureHolderFixture::class, 'json', $context);

        self::assertSame(6, ($restored->handler)(3));
    }

    private function createSerializer(?ClosureNormalizer $closureNormalizer = null): Serializer
    {
        $extractor = new PropertyInfoExtractor([], [new ReflectionExtractor()]);

        return new Serializer(
            [$closureNormalizer ?? $this->normalizer, new ObjectNormalizer(null, null, null, $extractor)],
            ['json' => new JsonEncoder()],
        );
    }

    private static function assertPureArray(mixed $value): void
    {
        self::assertIsArray($value);

        array_walk_recursive($value, static function ($leaf) {
            self::assertIsNotObject($leaf);
        });
    }
}

class ClosureHolderFixture
{
    public \Closure $handler;
    public string $name = '';
}

class PromotedClosureHolderFixture
{
    public function __construct(
        public \Closure $handler,
        public string $name = '',
    ) {
    }
}

class ClosureNormalizerFixture
{
    public function __construct(private int $base = 0)
    {
    }

    public static function twice(int $value): int
    {
        return 2 * $value;
    }

    public function add(int $value): int
    {
        return $this->base + $value;
    }
}
