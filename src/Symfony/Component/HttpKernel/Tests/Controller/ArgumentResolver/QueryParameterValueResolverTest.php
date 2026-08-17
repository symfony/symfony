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
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver\QueryParameterValueResolver;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NearMissValueResolverException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Tests\Fixtures\Suit;
use Symfony\Component\Uid\Ulid;

class QueryParameterValueResolverTest extends TestCase
{
    private ValueResolverInterface $resolver;

    protected function setUp(): void
    {
        $this->resolver = new QueryParameterValueResolver();
    }

    public function testSkipWhenNoAttribute()
    {
        $metadata = new ArgumentMetadata('firstName', 'string', false, true, false);

        $this->assertSame([], $this->resolver->resolve(Request::create('/'), $metadata));
    }

    #[DataProvider('validDataProvider')]
    public function testResolvingSuccessfully(Request $request, ArgumentMetadata $metadata, array $expected)
    {
        $this->assertEquals($expected, $this->resolver->resolve($request, $metadata));
    }

    public function testStagingArgumentTypesItCannotBuild()
    {
        $request = Request::create('/', 'GET', ['standardClass' => 'test']);
        $metadata = new ArgumentMetadata('standardClass', \stdClass::class, false, false, false, false, [new MapQueryParameter()]);

        try {
            $this->resolver->resolve($request, $metadata);
            $this->fail(NearMissValueResolverException::class.' was not thrown.');
        } catch (NearMissValueResolverException $e) {
            $this->assertSame('#[MapQueryParameter] cannot build controller argument "$standardClass" of type "stdClass" by itself; no resolver converted the staged value.', $e->getMessage());
        }

        // the raw value is left in the attributes for a type-aware resolver further down the chain
        $this->assertSame('test', $request->attributes->get($metadata->getName()));
    }

    public function testStagingRejectsAnArrayValue()
    {
        $request = Request::create('/', 'GET', ['standardClass' => ['test']]);
        $metadata = new ArgumentMetadata('standardClass', \stdClass::class, false, false, false, false, [new MapQueryParameter()]);

        try {
            $this->resolver->resolve($request, $metadata);
            $this->fail(HttpException::class.' was not thrown.');
        } catch (HttpException $e) {
            $this->assertSame(404, $e->getStatusCode());
            $this->assertSame('Invalid query parameter "standardClass".', $e->getMessage());
        }

        $this->assertFalse($request->attributes->has($metadata->getName()));
    }

    public function testResolvingVariadicOfUnsupportedType()
    {
        $request = Request::create('/', 'GET', ['standardClass' => 'test']);
        $metadata = new ArgumentMetadata('standardClass', \stdClass::class, true, false, false, false, [new MapQueryParameter()]);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('#[MapQueryParameter] cannot be used on controller argument "...$standardClass" of type "stdClass"; one of array, string, int, float, bool, uid or \BackedEnum should be used.');

        $this->resolver->resolve($request, $metadata);
    }

    #[DataProvider('invalidOrMissingArgumentProvider')]
    public function testResolvingWithInvalidOrMissingArgument(Request $request, ArgumentMetadata $metadata, HttpException $expectedException)
    {
        try {
            $this->resolver->resolve($request, $metadata);

            $this->fail(\sprintf('Expected "%s" to be thrown.', HttpException::class));
        } catch (HttpException $exception) {
            $this->assertSame($expectedException->getMessage(), $exception->getMessage());
            $this->assertSame($expectedException->getStatusCode(), $exception->getStatusCode());
        }
    }

    /**
     * @return iterable<string, array{
     *   Request,
     *   ArgumentMetadata,
     *   array<mixed>,
     * }>
     */
    public static function validDataProvider(): iterable
    {
        yield 'parameter found and array' => [
            Request::create('/', 'GET', ['ids' => ['1', '2']]),
            new ArgumentMetadata('ids', 'array', false, false, false, false, [new MapQueryParameter()]),
            [['1', '2']],
        ];

        yield 'parameter found and array variadic' => [
            Request::create('/', 'GET', ['ids' => [['1', '2'], ['2']]]),
            new ArgumentMetadata('ids', 'array', true, false, false, false, [new MapQueryParameter()]),
            [['1', '2'], ['2']],
        ];

        yield 'parameter found and string' => [
            Request::create('/', 'GET', ['firstName' => 'John']),
            new ArgumentMetadata('firstName', 'string', false, false, false, false, [new MapQueryParameter()]),
            ['John'],
        ];

        yield 'parameter found and string variadic' => [
            Request::create('/', 'GET', ['ids' => ['1', '2']]),
            new ArgumentMetadata('ids', 'string', true, false, false, false, [new MapQueryParameter()]),
            ['1', '2'],
        ];

        yield 'parameter found and string with regexp filter that matches' => [
            Request::create('/', 'GET', ['firstName' => 'John']),
            new ArgumentMetadata('firstName', 'string', false, false, false, false, [new MapQueryParameter(options: ['regexp' => '/John/'])]),
            ['John'],
        ];

        yield 'parameter found and string with regexp filter that falls back to null on failure' => [
            Request::create('/', 'GET', ['firstName' => 'Fabien']),
            new ArgumentMetadata('firstName', 'string', false, false, false, false, [new MapQueryParameter(flags: \FILTER_NULL_ON_FAILURE, options: ['regexp' => '/John/'])]),
            [null],
        ];

        yield 'parameter found and string variadic with regexp filter that matches' => [
            Request::create('/', 'GET', ['firstName' => ['John', 'John']]),
            new ArgumentMetadata('firstName', 'string', true, false, false, false, [new MapQueryParameter(options: ['regexp' => '/John/'])]),
            ['John', 'John'],
        ];

        yield 'parameter found and string variadic with regexp filter that falls back to null on failure' => [
            Request::create('/', 'GET', ['firstName' => ['John', 'Fabien']]),
            new ArgumentMetadata('firstName', 'string', true, false, false, false, [new MapQueryParameter(flags: \FILTER_NULL_ON_FAILURE, options: ['regexp' => '/John/'])]),
            ['John'],
        ];

        yield 'parameter found and integer' => [
            Request::create('/', 'GET', ['age' => '123']),
            new ArgumentMetadata('age', 'int', false, false, false, false, [new MapQueryParameter()]),
            [123],
        ];

        yield 'parameter found and integer variadic' => [
            Request::create('/', 'GET', ['age' => ['123', '222']]),
            new ArgumentMetadata('age', 'int', true, false, false, false, [new MapQueryParameter()]),
            [123, 222],
        ];

        yield 'parameter found and float' => [
            Request::create('/', 'GET', ['price' => '10.99']),
            new ArgumentMetadata('price', 'float', false, false, false, false, [new MapQueryParameter()]),
            [10.99],
        ];

        yield 'parameter found and float variadic' => [
            Request::create('/', 'GET', ['price' => ['10.99e2', '5.99']]),
            new ArgumentMetadata('price', 'float', true, false, false, false, [new MapQueryParameter()]),
            [1099.0, 5.99],
        ];

        yield 'parameter found and boolean yes' => [
            Request::create('/', 'GET', ['isVerified' => 'yes']),
            new ArgumentMetadata('isVerified', 'bool', false, false, false, false, [new MapQueryParameter()]),
            [true],
        ];

        yield 'parameter found and boolean yes variadic' => [
            Request::create('/', 'GET', ['isVerified' => ['yes', 'yes']]),
            new ArgumentMetadata('isVerified', 'bool', true, false, false, false, [new MapQueryParameter()]),
            [true, true],
        ];

        yield 'parameter found and boolean true' => [
            Request::create('/', 'GET', ['isVerified' => 'true']),
            new ArgumentMetadata('isVerified', 'bool', false, false, false, false, [new MapQueryParameter()]),
            [true],
        ];

        yield 'parameter found and boolean 1' => [
            Request::create('/', 'GET', ['isVerified' => '1']),
            new ArgumentMetadata('isVerified', 'bool', false, false, false, false, [new MapQueryParameter()]),
            [true],
        ];

        yield 'parameter found and boolean no' => [
            Request::create('/', 'GET', ['isVerified' => 'no']),
            new ArgumentMetadata('isVerified', 'bool', false, false, false, false, [new MapQueryParameter()]),
            [false],
        ];

        yield 'parameter found and backing value' => [
            Request::create('/', 'GET', ['suit' => 'H']),
            new ArgumentMetadata('suit', Suit::class, false, false, false, false, [new MapQueryParameter()]),
            [Suit::Hearts],
        ];

        yield 'parameter found and backing value variadic' => [
            Request::create('/', 'GET', ['suits' => ['H', 'D']]),
            new ArgumentMetadata('suits', Suit::class, true, false, false, false, [new MapQueryParameter()]),
            [Suit::Hearts, Suit::Diamonds],
        ];

        yield 'parameter found and backing value not int nor string that fallbacks to null on failure' => [
            Request::create('/', 'GET', ['suit' => 1]),
            new ArgumentMetadata('suit', Suit::class, false, false, false, false, [new MapQueryParameter(filter: \FILTER_VALIDATE_BOOL, flags: \FILTER_NULL_ON_FAILURE)]),
            [null],
        ];

        yield 'parameter found and value not valid backing value that falls back to null on failure' => [
            Request::create('/', 'GET', ['suit' => 'B']),
            new ArgumentMetadata('suit', Suit::class, false, false, false, false, [new MapQueryParameter(flags: \FILTER_NULL_ON_FAILURE)]),
            [null],
        ];

        yield 'parameter found and backing type variadic and at least one backing value not int nor string that fallbacks to null on failure' => [
            Request::create('/', 'GET', ['suits' => ['1', 'D']]),
            new ArgumentMetadata('suits', Suit::class, false, false, false, false, [new MapQueryParameter(flags: \FILTER_NULL_ON_FAILURE)]),
            [null],
        ];

        yield 'parameter found and backing type variadic and at least one value not valid backing value that falls back to null on failure' => [
            Request::create('/', 'GET', ['suits' => ['B', 'D']]),
            new ArgumentMetadata('suits', Suit::class, false, false, false, false, [new MapQueryParameter(flags: \FILTER_NULL_ON_FAILURE)]),
            [null],
        ];

        yield 'parameter not found but nullable' => [
            Request::create('/', 'GET'),
            new ArgumentMetadata('firstName', 'string', false, false, false, true, [new MapQueryParameter()]),
            [],
        ];

        yield 'parameter not found but optional' => [
            Request::create('/', 'GET'),
            new ArgumentMetadata('firstName', 'string', false, true, false, false, [new MapQueryParameter()]),
            [],
        ];

        yield 'parameter found and ULID' => [
            Request::create('/', 'GET', ['groupId' => '01E439TP9XJZ9RPFH3T1PYBCR8']),
            new ArgumentMetadata('groupId', Ulid::class, false, true, false, false, [new MapQueryParameter()]),
            [Ulid::fromString('01E439TP9XJZ9RPFH3T1PYBCR8')],
        ];
    }

    /**
     * @return iterable<string, array{
     *   Request,
     *   ArgumentMetadata,
     *   HttpException,
     * }>
     */
    public static function invalidOrMissingArgumentProvider(): iterable
    {
        yield 'parameter found and array variadic with parameter not array failure' => [
            Request::create('/', 'GET', ['ids' => [['1', '2'], '1']]),
            new ArgumentMetadata('ids', 'array', true, false, false, false, [new MapQueryParameter()]),
            new NotFoundHttpException('Invalid query parameter "ids".'),
        ];

        yield 'parameter found and string with regexp filter that does not match' => [
            Request::create('/', 'GET', ['firstName' => 'Fabien']),
            new ArgumentMetadata('firstName', 'string', false, false, false, false, [new MapQueryParameter(filter: \FILTER_VALIDATE_REGEXP, options: ['regexp' => '/John/'])]),
            new NotFoundHttpException('Invalid query parameter "firstName".'),
        ];

        yield 'parameter found and string variadic with regexp filter that does not match' => [
            Request::create('/', 'GET', ['firstName' => ['Fabien']]),
            new ArgumentMetadata('firstName', 'string', true, false, false, false, [new MapQueryParameter(filter: \FILTER_VALIDATE_REGEXP, options: ['regexp' => '/John/'])]),
            new NotFoundHttpException('Invalid query parameter "firstName".'),
        ];

        yield 'parameter found and boolean invalid' => [
            Request::create('/', 'GET', ['isVerified' => 'whatever']),
            new ArgumentMetadata('isVerified', 'bool', false, false, false, false, [new MapQueryParameter()]),
            new NotFoundHttpException('Invalid query parameter "isVerified".'),
        ];

        yield 'parameter found and backing value not int nor string' => [
            Request::create('/', 'GET', ['suit' => 1]),
            new ArgumentMetadata('suit', Suit::class, false, false, false, false, [new MapQueryParameter(filter: \FILTER_VALIDATE_BOOL)]),
            new NotFoundHttpException('Invalid query parameter "suit".'),
        ];

        yield 'parameter found and value not valid backing value' => [
            Request::create('/', 'GET', ['suit' => 'B']),
            new ArgumentMetadata('suit', Suit::class, false, false, false, false, [new MapQueryParameter()]),
            new NotFoundHttpException('Invalid query parameter "suit".'),
        ];

        yield 'parameter found and backing type variadic and at least one backing value not int nor string' => [
            Request::create('/', 'GET', ['suits' => [1, 'D']]),
            new ArgumentMetadata('suits', Suit::class, false, false, false, false, [new MapQueryParameter(filter: \FILTER_VALIDATE_BOOL)]),
            new NotFoundHttpException('Invalid query parameter "suits".'),
        ];

        yield 'parameter found and backing type variadic and at least one value not valid backing value' => [
            Request::create('/', 'GET', ['suits' => ['B', 'D']]),
            new ArgumentMetadata('suits', Suit::class, false, false, false, false, [new MapQueryParameter()]),
            new NotFoundHttpException('Invalid query parameter "suits".'),
        ];

        yield 'parameter not found' => [
            Request::create('/', 'GET'),
            new ArgumentMetadata('firstName', 'string', false, false, false, false, [new MapQueryParameter()]),
            new NotFoundHttpException('Missing query parameter "firstName".'),
        ];

        yield 'parameter not found with custom validation failed status code' => [
            Request::create('/', 'GET'),
            new ArgumentMetadata('firstName', 'string', false, false, false, false, [new MapQueryParameter(validationFailedStatusCode: Response::HTTP_BAD_REQUEST)]),
            new BadRequestHttpException('Missing query parameter "firstName".'),
        ];
    }
}
