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

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver\QueryParameterValueResolver;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class QueryParameterValueResolverTest extends TestCase
{
    private ValueResolverInterface $resolver;

    protected function setUp(): void
    {
        $this->resolver = new QueryParameterValueResolver();
    }

    /**
     * @dataProvider provideTestResolve
     */
    public function testResolve(Request $request, ArgumentMetadata $metadata, array $expected, string $exceptionClass = null, string $exceptionMessage = null)
    {
        if ($exceptionMessage) {
            self::expectException($exceptionClass);
            self::expectExceptionMessage($exceptionMessage);
        }

        self::assertSame($expected, $this->resolver->resolve($request, $metadata));
    }

    /**
     * @return iterable<string, array{
     *   Request,
     *   ArgumentMetadata,
     *   array<mixed>,
     *   null|class-string<\Exception>,
     *   null|string
     * }>
     */
    public static function provideTestResolve(): iterable
    {
        yield 'parameter found and array' => [
            Request::create('/', 'GET', ['ids' => ['1', '2']]),
            new ArgumentMetadata('ids', 'array', false, false, false, attributes: [new MapQueryParameter()]),
            [['1', '2']],
            null,
        ];
        yield 'parameter found and array variadic' => [
            Request::create('/', 'GET', ['ids' => [['1', '2'], ['2']]]),
            new ArgumentMetadata('ids', 'array', true, false, false, attributes: [new MapQueryParameter()]),
            [['1', '2'], ['2']],
            null,
        ];
        yield 'parameter found and string' => [
            Request::create('/', 'GET', ['firstName' => 'John']),
            new ArgumentMetadata('firstName', 'string', false, false, false, attributes: [new MapQueryParameter()]),
            ['John'],
            null,
        ];
        yield 'parameter found and string variadic' => [
            Request::create('/', 'GET', ['ids' => ['1', '2']]),
            new ArgumentMetadata('ids', 'string', true, false, false, attributes: [new MapQueryParameter()]),
            ['1', '2'],
            null,
        ];
        yield 'parameter found and string with regexp filter that matches' => [
            Request::create('/', 'GET', ['firstName' => 'John']),
            new ArgumentMetadata('firstName', 'string', false, false, false, attributes: [new MapQueryParameter(filter: \FILTER_VALIDATE_REGEXP, flags: \FILTER_NULL_ON_FAILURE, options: ['regexp' => '/John/'])]),
            ['John'],
            null,
        ];
        yield 'parameter found and string with regexp filter that falls back to null on failure' => [
            Request::create('/', 'GET', ['firstName' => 'Fabien']),
            new ArgumentMetadata('firstName', 'string', false, false, false, attributes: [new MapQueryParameter(filter: \FILTER_VALIDATE_REGEXP, flags: \FILTER_NULL_ON_FAILURE, options: ['regexp' => '/John/'])]),
            [null],
            null,
        ];
        yield 'parameter found and string with regexp filter that does not match' => [
            Request::create('/', 'GET', ['firstName' => 'Fabien']),
            new ArgumentMetadata('firstName', 'string', false, false, false, attributes: [new MapQueryParameter(filter: \FILTER_VALIDATE_REGEXP, options: ['regexp' => '/John/'])]),
            [],
            NotFoundHttpException::class,
            'Invalid query parameter "firstName".',
        ];
        yield 'parameter found and string variadic with regexp filter that matches' => [
            Request::create('/', 'GET', ['firstName' => ['John', 'John']]),
            new ArgumentMetadata('firstName', 'string', true, false, false, attributes: [new MapQueryParameter(filter: \FILTER_VALIDATE_REGEXP, flags: \FILTER_NULL_ON_FAILURE, options: ['regexp' => '/John/'])]),
            ['John', 'John'],
            null,
        ];
        yield 'parameter found and string variadic with regexp filter that falls back to null on failure' => [
            Request::create('/', 'GET', ['firstName' => ['John', 'Fabien']]),
            new ArgumentMetadata('firstName', 'string', true, false, false, attributes: [new MapQueryParameter(filter: \FILTER_VALIDATE_REGEXP, flags: \FILTER_NULL_ON_FAILURE, options: ['regexp' => '/John/'])]),
            ['John'],
            null,
        ];
        yield 'parameter found and string variadic with regexp filter that does not match' => [
            Request::create('/', 'GET', ['firstName' => ['Fabien']]),
            new ArgumentMetadata('firstName', 'string', true, false, false, attributes: [new MapQueryParameter(filter: \FILTER_VALIDATE_REGEXP, options: ['regexp' => '/John/'])]),
            [],
            NotFoundHttpException::class,
            'Invalid query parameter "firstName".',
        ];
        yield 'parameter found and integer' => [
            Request::create('/', 'GET', ['age' => 123]),
            new ArgumentMetadata('age', 'int', false, false, false, attributes: [new MapQueryParameter()]),
            [123],
            null,
        ];
        yield 'parameter found and integer variadic' => [
            Request::create('/', 'GET', ['age' => [123, 222]]),
            new ArgumentMetadata('age', 'int', true, false, false, attributes: [new MapQueryParameter()]),
            [123, 222],
            null,
        ];
        yield 'parameter found and float' => [
            Request::create('/', 'GET', ['price' => 10.99]),
            new ArgumentMetadata('price', 'float', false, false, false, attributes: [new MapQueryParameter()]),
            [10.99],
            null,
        ];
        yield 'parameter found and float variadic' => [
            Request::create('/', 'GET', ['price' => [10.99, 5.99]]),
            new ArgumentMetadata('price', 'float', true, false, false, attributes: [new MapQueryParameter()]),
            [10.99, 5.99],
            null,
        ];
        yield 'parameter found and boolean yes' => [
            Request::create('/', 'GET', ['isVerified' => 'yes']),
            new ArgumentMetadata('isVerified', 'bool', false, false, false, attributes: [new MapQueryParameter()]),
            [true],
            null,
        ];
        yield 'parameter found and boolean yes variadic' => [
            Request::create('/', 'GET', ['isVerified' => ['yes', 'yes']]),
            new ArgumentMetadata('isVerified', 'bool', true, false, false, attributes: [new MapQueryParameter()]),
            [true, true],
            null,
        ];
        yield 'parameter found and boolean true' => [
            Request::create('/', 'GET', ['isVerified' => 'true']),
            new ArgumentMetadata('isVerified', 'bool', false, false, false, attributes: [new MapQueryParameter()]),
            [true],
            null,
        ];
        yield 'parameter found and boolean 1' => [
            Request::create('/', 'GET', ['isVerified' => '1']),
            new ArgumentMetadata('isVerified', 'bool', false, false, false, attributes: [new MapQueryParameter()]),
            [true],
            null,
        ];
        yield 'parameter found and boolean no' => [
            Request::create('/', 'GET', ['isVerified' => 'no']),
            new ArgumentMetadata('isVerified', 'bool', false, false, false, attributes: [new MapQueryParameter()]),
            [false],
            null,
        ];
        yield 'parameter found and boolean invalid' => [
            Request::create('/', 'GET', ['isVerified' => 'whatever']),
            new ArgumentMetadata('isVerified', 'bool', false, false, false, attributes: [new MapQueryParameter()]),
            [],
            NotFoundHttpException::class,
            'Invalid query parameter "isVerified".',
        ];

        yield 'parameter not found but nullable' => [
            Request::create('/', 'GET'),
            new ArgumentMetadata('firstName', 'string', false, false, false, true, [new MapQueryParameter()]),
            [],
            null,
        ];

        yield 'parameter not found but optional' => [
            Request::create('/', 'GET'),
            new ArgumentMetadata('firstName', 'string', false, true, false, attributes: [new MapQueryParameter()]),
            [],
            null,
        ];

        yield 'parameter not found' => [
            Request::create('/', 'GET'),
            new ArgumentMetadata('firstName', 'string', false, false, false, attributes: [new MapQueryParameter()]),
            [],
            NotFoundHttpException::class,
            'Missing query parameter "firstName".',
        ];

        yield 'unsupported type' => [
            Request::create('/', 'GET', ['standardClass' => 'test']),
            new ArgumentMetadata('standardClass', \stdClass::class, false, false, false, attributes: [new MapQueryParameter()]),
            [],
            \LogicException::class,
            '#[MapQueryParameter] cannot be used on controller argument "$standardClass" of type "stdClass"; one of array, string, int, float or bool should be used.',
        ];
        yield 'unsupported type variadic' => [
            Request::create('/', 'GET', ['standardClass' => 'test']),
            new ArgumentMetadata('standardClass', \stdClass::class, true, false, false, attributes: [new MapQueryParameter()]),
            [],
            \LogicException::class,
            '#[MapQueryParameter] cannot be used on controller argument "...$standardClass" of type "stdClass"; one of array, string, int, float or bool should be used.',
        ];
    }

    public function testSkipWhenNoAttribute()
    {
        $metadata = new ArgumentMetadata('firstName', 'string', false, true, false);

        self::assertSame([], $this->resolver->resolve(Request::create('/'), $metadata));
    }
}
