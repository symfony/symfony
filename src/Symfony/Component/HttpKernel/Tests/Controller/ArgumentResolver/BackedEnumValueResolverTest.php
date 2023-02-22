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
use Symfony\Component\HttpKernel\Controller\ArgumentResolver\BackedEnumValueResolver;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Tests\Fixtures\Suit;

class BackedEnumValueResolverTest extends TestCase
{
    /**
     * In Symfony 7, keep this test case but remove the call to supports().
     *
     * @group legacy
     *
     * @dataProvider provideTestSupportsData
     */
    public function testSupports(Request $request, ArgumentMetadata $metadata, bool $expectedSupport)
    {
        $resolver = new BackedEnumValueResolver();

        if (!$expectedSupport) {
            $this->assertSame([], $resolver->resolve($request, $metadata));
        }
        self::assertSame($expectedSupport, $resolver->supports($request, $metadata));
    }

    public static function provideTestSupportsData(): iterable
    {
        yield 'unsupported type' => [
            self::createRequest(['suit' => 'H']),
            self::createArgumentMetadata('suit', \stdClass::class),
            false,
        ];

        yield 'supports from attributes' => [
            self::createRequest(['suit' => 'H']),
            self::createArgumentMetadata('suit', Suit::class),
            true,
        ];

        yield 'with null attribute value' => [
            self::createRequest(['suit' => null]),
            self::createArgumentMetadata('suit', Suit::class),
            true,
        ];

        yield 'without matching attribute' => [
            self::createRequest(),
            self::createArgumentMetadata('suit', Suit::class),
            false,
        ];

        yield 'unsupported variadic' => [
            self::createRequest(['suit' => ['H', 'S']]),
            self::createArgumentMetadata(
                'suit',
                Suit::class,
                variadic: true,
            ),
            false,
        ];
    }

    /**
     * @dataProvider provideTestResolveData
     */
    public function testResolve(Request $request, ArgumentMetadata $metadata, $expected)
    {
        $resolver = new BackedEnumValueResolver();
        /** @var \Generator $results */
        $results = $resolver->resolve($request, $metadata);

        self::assertSame($expected, $results);
    }

    public static function provideTestResolveData(): iterable
    {
        yield 'resolves from attributes' => [
            self::createRequest(['suit' => 'H']),
            self::createArgumentMetadata('suit', Suit::class),
            [Suit::Hearts],
        ];

        yield 'with null attribute value' => [
            self::createRequest(['suit' => null]),
            self::createArgumentMetadata(
                'suit',
                Suit::class,
            ),
            [null],
        ];

        yield 'already resolved attribute value' => [
            self::createRequest(['suit' => Suit::Hearts]),
            self::createArgumentMetadata('suit', Suit::class),
            [Suit::Hearts],
        ];
    }

    public function testResolveThrowsNotFoundOnInvalidValue()
    {
        $resolver = new BackedEnumValueResolver();
        $request = self::createRequest(['suit' => 'foo']);
        $metadata = self::createArgumentMetadata('suit', Suit::class);

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Could not resolve the "Symfony\Component\HttpKernel\Tests\Fixtures\Suit $suit" controller argument: "foo" is not a valid backing value for enum');

        $resolver->resolve($request, $metadata);
    }

    public function testResolveThrowsOnUnexpectedType()
    {
        $resolver = new BackedEnumValueResolver();
        $request = self::createRequest(['suit' => false]);
        $metadata = self::createArgumentMetadata('suit', Suit::class);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Could not resolve the "Symfony\Component\HttpKernel\Tests\Fixtures\Suit $suit" controller argument: expecting an int or string, got "bool".');

        $resolver->resolve($request, $metadata);
    }

    private static function createRequest(array $attributes = []): Request
    {
        return new Request([], [], $attributes);
    }

    private static function createArgumentMetadata(string $name, string $type, bool $variadic = false): ArgumentMetadata
    {
        return new ArgumentMetadata($name, $type, $variadic, false, null);
    }
}
