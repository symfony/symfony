<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\Controller\ArgumentResolver;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver\RequestAttributeValueResolver;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class RequestAttributeValueResolverTest extends TestCase
{
    public function testValidIntWithinRangeWorks()
    {
        $resolver = new RequestAttributeValueResolver();
        $request = new Request();
        $request->attributes->set('id', '123');
        $metadata = new ArgumentMetadata('id', 'int', false, false, null);

        $result = iterator_to_array($resolver->resolve($request, $metadata));

        $this->assertSame([123], $result);
    }

    public function testInvalidStringBecomes404()
    {
        $resolver = new RequestAttributeValueResolver();
        $request = new Request();
        $request->attributes->set('id', 'abc');
        $metadata = new ArgumentMetadata('id', 'int', false, false, null);

        $this->expectException(NotFoundHttpException::class);
        iterator_to_array($resolver->resolve($request, $metadata));
    }

    public function testOutOfRangeIntBecomes404()
    {
        $resolver = new RequestAttributeValueResolver();
        $request = new Request();
        // one more than PHP_INT_MAX on 64-bit (string input)
        $request->attributes->set('id', '9223372036854775808');
        $metadata = new ArgumentMetadata('id', 'int', false, false, null);

        $this->expectException(NotFoundHttpException::class);
        iterator_to_array($resolver->resolve($request, $metadata));
    }

    public function testNullableIntAllowsNull()
    {
        $resolver = new RequestAttributeValueResolver();
        $request = new Request();
        $request->attributes->set('id', null);
        $metadata = new ArgumentMetadata('id', 'int', false, true, null);

        $result = iterator_to_array($resolver->resolve($request, $metadata));

        $this->assertSame([null], $result);
    }

    /**
     * A zero-padded decimal (e.g. "06" from a {month} placeholder with a "\d+"
     * requirement) must resolve to its int value instead of becoming a 404.
     */
    #[DataProvider('provideLeadingZeroInts')]
    public function testLeadingZeroIntIsAccepted(string $value, int $expected)
    {
        $resolver = new RequestAttributeValueResolver();
        $request = new Request();
        $request->attributes->set('id', $value);
        $metadata = new ArgumentMetadata('id', 'int', false, false, null);

        $result = iterator_to_array($resolver->resolve($request, $metadata));

        $this->assertSame([$expected], $result);
    }

    public static function provideLeadingZeroInts(): iterable
    {
        yield 'single leading zero' => ['06', 6];
        yield 'multiple leading zeros' => ['007', 7];
        yield 'would-be-octal stays decimal' => ['08', 8];
        yield 'all zeros' => ['00', 0];
        yield 'negative with leading zero' => ['-06', -6];
    }

    public function testLeadingZeroOutOfRangeIntBecomes404()
    {
        $resolver = new RequestAttributeValueResolver();
        $request = new Request();
        // leading zero in front of (PHP_INT_MAX + 1) must still overflow, not silently truncate
        $request->attributes->set('id', '09223372036854775808');
        $metadata = new ArgumentMetadata('id', 'int', false, false, null);

        $this->expectException(NotFoundHttpException::class);
        iterator_to_array($resolver->resolve($request, $metadata));
    }

    #[DataProvider('provideFloats')]
    public function testFloatIsCoerced(string $value, float $expected)
    {
        $resolver = new RequestAttributeValueResolver();
        $request = new Request();
        $request->attributes->set('price', $value);
        $metadata = new ArgumentMetadata('price', 'float', false, false, null);

        $result = iterator_to_array($resolver->resolve($request, $metadata));

        $this->assertSame([$expected], $result);
    }

    public static function provideFloats(): iterable
    {
        yield 'decimal' => ['1.5', 1.5];
        yield 'leading zero' => ['06', 6.0];
    }

    public function testInvalidFloatBecomes404()
    {
        $resolver = new RequestAttributeValueResolver();
        $request = new Request();
        $request->attributes->set('price', 'abc');
        $metadata = new ArgumentMetadata('price', 'float', false, false, null);

        $this->expectException(NotFoundHttpException::class);
        iterator_to_array($resolver->resolve($request, $metadata));
    }

    #[DataProvider('provideStrictBools')]
    public function testBoolUsesStrictValidation(string $value, bool $expected)
    {
        $resolver = new RequestAttributeValueResolver();
        $request = new Request();
        $request->attributes->set('active', $value);
        $metadata = new ArgumentMetadata('active', 'bool', false, false, null);

        $result = iterator_to_array($resolver->resolve($request, $metadata));

        $this->assertSame([$expected], $result);
    }

    public static function provideStrictBools(): iterable
    {
        yield 'one' => ['1', true];
        yield 'zero' => ['0', false];
        yield 'true' => ['true', true];
        yield 'false' => ['false', false];
    }

    public function testNonTokenBoolBecomes404()
    {
        $resolver = new RequestAttributeValueResolver();
        $request = new Request();
        // unlike PHP's coercion (any non-empty string is true), bool keeps strict token validation
        $request->attributes->set('active', 'banana');
        $metadata = new ArgumentMetadata('active', 'bool', false, false, null);

        $this->expectException(NotFoundHttpException::class);
        iterator_to_array($resolver->resolve($request, $metadata));
    }
}
