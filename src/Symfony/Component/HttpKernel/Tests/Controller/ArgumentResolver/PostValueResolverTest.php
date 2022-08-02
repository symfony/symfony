<?php

declare(strict_types=1);

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
use Symfony\Component\HttpKernel\Controller\ArgumentResolver\PostValueResolver;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver\ResolvePostValue;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class PostValueResolverTest extends TestCase
{
    public function testSupports()
    {
        $sut = new PostValueResolver();
        $request = new Request();
        $argumentMetadata = new ArgumentMetadata(
            '',
            null,
            false,
            false,
            null,
            attributes: [new ResolvePostValue()],
        );
        $this->assertTrue($sut->supports($request, $argumentMetadata));
        $argumentMetadata = new ArgumentMetadata(
            '',
            null,
            false,
            false,
            null,
        );
        $this->assertFalse($sut->supports($request, $argumentMetadata));
    }

    /**
     * @dataProvider provideForTestResolve
     */
    public function testResolve(
        array $post,
        ArgumentMetadata $argumentMetadata,
        mixed $expectedValue,
    ) {
        $sut = new PostValueResolver();
        $request = new Request(request: $post);
        $this->assertSame([$expectedValue], $sut->resolve($request, $argumentMetadata));
    }

    public function provideForTestResolve(): iterable
    {
        yield 'arg name' => [
            ['arg_name' => 'value'],
            new ArgumentMetadata(
                'arg_name',
                'string',
                false,
                false,
                null,
                false,
                [new ResolvePostValue()],
            ),
            'value',
        ];
        yield 'attribute name' => [
            ['post_name' => 'value'],
            new ArgumentMetadata(
                'arg_name',
                'string',
                false,
                false,
                null,
                false,
                [new ResolvePostValue('post_name')],
            ),
            'value',
        ];
        yield 'attribute default' => [
            [],
            new ArgumentMetadata(
                'arg_name',
                'string',
                false,
                false,
                null,
                false,
                [new ResolvePostValue(default: 'value')],
            ),
            'value',
        ];
        yield 'argument default' => [
            [],
            new ArgumentMetadata(
                'arg_name',
                'string',
                false,
                true,
                'value',
                false,
                [new ResolvePostValue()],
            ),
            'value',
        ];
        yield 'nullable argument' => [
            [],
            new ArgumentMetadata(
                'arg_name',
                'string',
                false,
                false,
                null,
                true,
                [new ResolvePostValue()],
            ),
            null,
        ];
        yield 'bool coercion - false' => [
            ['arg_name' => '0'],
            new ArgumentMetadata(
                'arg_name',
                'bool',
                false,
                false,
                null,
                false,
                [new ResolvePostValue()],
            ),
            false,
        ];
        yield 'bool coercion - true' => [
            ['arg_name' => '1'],
            new ArgumentMetadata(
                'arg_name',
                'bool',
                false,
                false,
                null,
                false,
                [new ResolvePostValue()],
            ),
            true,
        ];
        yield 'int coercion' => [
            ['arg_name' => '13'],
            new ArgumentMetadata(
                'arg_name',
                'int',
                false,
                false,
                null,
                false,
                [new ResolvePostValue()],
            ),
            13,
        ];
        yield 'float coercion' => [
            ['arg_name' => '13.0'],
            new ArgumentMetadata(
                'arg_name',
                'float',
                false,
                false,
                null,
                false,
                [new ResolvePostValue()],
            ),
            13.0,
        ];
    }

    public function testLogicException()
    {
        $sut = new PostValueResolver();
        $request = new Request();
        $argumentMetadata = new ArgumentMetadata('', null, false, false, null);
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Argument does not have a "Symfony\Component\HttpKernel\Controller\ArgumentResolver\ResolvePostValue" attribute.');
        $sut->resolve($request, $argumentMetadata);
    }

    public function testPostParamDoesNotExistException()
    {
        $sut = new PostValueResolver();
        $request = new Request();
        $argumentMetadata = new ArgumentMetadata('arg_name', 'string', false, false, null, attributes: [new ResolvePostValue()]);
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Request param "arg_name" does not exist.');
        $sut->resolve($request, $argumentMetadata);
    }

    public function testPostParamCanNotBeCoercedException()
    {
        $sut = new PostValueResolver();
        $request = new Request(request: ['arg_name' => 'bogus']);
        $argumentMetadata = new ArgumentMetadata('arg_name', 'bool', false, false, null, attributes: [new ResolvePostValue()]);
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Request param "arg_name" could not be coerced to a "bool".');
        $sut->resolve($request, $argumentMetadata);
    }
}
