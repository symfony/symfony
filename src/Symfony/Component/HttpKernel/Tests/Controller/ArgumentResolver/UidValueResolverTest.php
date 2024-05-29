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
use Symfony\Component\HttpKernel\Controller\ArgumentResolver\UidValueResolver;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\AbstractUid;
use Symfony\Component\Uid\Factory\UlidFactory;
use Symfony\Component\Uid\Ulid;
use Symfony\Component\Uid\UuidV1;
use Symfony\Component\Uid\UuidV4;

class UidValueResolverTest extends TestCase
{
    /**
     * @dataProvider provideSupports
     */
    public function testSupports(bool $expected, Request $request, ArgumentMetadata $argument)
    {
        $this->assertCount((int) $expected, (new UidValueResolver())->resolve($request, $argument));
    }

    public static function provideSupports()
    {
        return [
            'Variadic argument' => [false, new Request([], [], ['foo' => (string) $uuidV4 = new UuidV4()]), new ArgumentMetadata('foo', UuidV4::class, true, false, null)],
            'No attribute for argument' => [false, new Request([], [], []), new ArgumentMetadata('foo', UuidV4::class, false, false, null)],
            'Attribute is not a string' => [false, new Request([], [], ['foo' => ['bar']]), new ArgumentMetadata('foo', UuidV4::class, false, false, null)],
            'Argument has no type' => [false, new Request([], [], ['foo' => (string) $uuidV4]), new ArgumentMetadata('foo', null, false, false, null)],
            'Argument type is not a class' => [false, new Request([], [], ['foo' => (string) $uuidV4]), new ArgumentMetadata('foo', 'string', false, false, null)],
            'Argument type is not a subclass of AbstractUid' => [false, new Request([], [], ['foo' => (string) $uuidV4]), new ArgumentMetadata('foo', UlidFactory::class, false, false, null)],
            'AbstractUid is not supported' => [false, new Request([], [], ['foo' => (string) $uuidV4]), new ArgumentMetadata('foo', AbstractUid::class, false, false, null)],
            'Known subclass' => [true, new Request([], [], ['foo' => (string) $uuidV4]), new ArgumentMetadata('foo', UuidV4::class, false, false, null)],
            'Format does not matter' => [true, new Request([], [], ['foo' => (string) $uuidV4]), new ArgumentMetadata('foo', Ulid::class, false, false, null)],
        ];
    }

    /**
     * @dataProvider provideResolveOK
     */
    public function testResolveOK(AbstractUid $expected, string $requestUid)
    {
        $this->assertEquals([$expected], (new UidValueResolver())->resolve(
            new Request([], [], ['id' => $requestUid]),
            new ArgumentMetadata('id', $expected::class, false, false, null)
        ));
    }

    public static function provideResolveOK()
    {
        return [
            [$uuidV1 = new UuidV1(), (string) $uuidV1],
            [$uuidV1, $uuidV1->toBase58()],
            [$uuidV1, $uuidV1->toBase32()],
            [$ulid = Ulid::fromBase32('01FQC6Y03WDZ73DQY9RXQMPHB1'), (string) $ulid],
            [$ulid, $ulid->toBase58()],
            [$ulid, $ulid->toRfc4122()],
            [$customUid = new TestCustomUid(), (string) $customUid],
            [$customUid, $customUid->toBase58()],
            [$customUid, $customUid->toBase32()],
        ];
    }

    /**
     * @dataProvider provideResolveKO
     */
    public function testResolveKO(string $requestUid, string $argumentType)
    {
        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('The uid for the "id" parameter is invalid.');

        (new UidValueResolver())->resolve(
            new Request([], [], ['id' => $requestUid]),
            new ArgumentMetadata('id', $argumentType, false, false, null)
        );
    }

    public static function provideResolveKO()
    {
        return [
            'Bad value for UUID' => ['ccc', UuidV1::class],
            'Bad value for ULID' => ['ccc', Ulid::class],
            'Bad value for custom UID' => ['ccc', TestCustomUid::class],
            'Bad UUID version' => [(string) new UuidV4(), UuidV1::class],
        ];
    }

    public function testResolveAbstractClass()
    {
        $this->expectException(\Error::class);
        $this->expectExceptionMessage('Cannot instantiate abstract class Symfony\Component\HttpKernel\Tests\Controller\ArgumentResolver\TestAbstractCustomUid');

        (new UidValueResolver())->resolve(
            new Request([], [], ['id' => (string) new UuidV1()]),
            new ArgumentMetadata('id', TestAbstractCustomUid::class, false, false, null)
        );
    }
}

class TestCustomUid extends UuidV1
{
}

abstract class TestAbstractCustomUid extends UuidV1
{
}
