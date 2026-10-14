<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonSchema\Tests\ClassSchemaResolver;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\JsonSchema\ClassSchemaResolver\NativeClassSchemaResolver;
use Symfony\Component\JsonSchema\Configuration;
use Symfony\Component\JsonSchema\SchemaDocument;
use Symfony\Component\Uid\Ulid;
use Symfony\Component\Uid\Uuid;

class NativeClassSchemaResolverTest extends TestCase
{
    #[DataProvider('provideDefaultContexts')]
    public function testDefaultContextSelectsTheFormat(array $defaultContext, string $class, array $expected)
    {
        $this->assertSame($expected, (new NativeClassSchemaResolver($defaultContext))->resolve($class, new Configuration(), new SchemaDocument()));
    }

    public static function provideDefaultContexts(): iterable
    {
        yield 'RFC 3339 by default' => [[], \DateTimeImmutable::class, ['type' => 'string', 'format' => 'date-time']];
        yield 'RFC 3339 with milliseconds' => [['datetime_format' => \DateTimeInterface::RFC3339_EXTENDED], \DateTimeImmutable::class, ['type' => 'string', 'format' => 'date-time']];
        yield 'date' => [['datetime_format' => 'Y-m-d'], \DateTime::class, ['type' => 'string', 'format' => 'date']];
        yield 'custom date format' => [['datetime_format' => 'd/m/Y'], \DateTimeImmutable::class, ['type' => 'string']];
        yield 'canonical UUID' => [[], Uuid::class, ['type' => 'string', 'format' => 'uuid']];
        yield 'base58 UUID' => [['uid_normalization_format' => 'base58'], Uuid::class, ['type' => 'string']];
        yield 'canonical ULID' => [[], Ulid::class, ['type' => 'string', 'format' => 'ulid']];
        yield 'RFC 4122 ULID' => [['uid_normalization_format' => 'rfc4122'], Ulid::class, ['type' => 'string', 'format' => 'uuid']];
    }
}
