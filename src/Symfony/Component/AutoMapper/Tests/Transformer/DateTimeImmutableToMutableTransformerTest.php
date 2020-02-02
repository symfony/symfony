<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\AutoMapper\Tests\Transformer;

use PHPUnit\Framework\TestCase;
use Symfony\Component\AutoMapper\Transformer\DateTimeImmutableToMutableTransformer;

class DateTimeImmutableToMutableTransformerTest extends TestCase
{
    use EvalTransformerTrait;

    public function testDateTimeImmutableTransformer()
    {
        $transformer = new DateTimeImmutableToMutableTransformer();

        $date = new \DateTimeImmutable();
        $output = $this->evalTransformer($transformer, $date);

        self::assertInstanceOf(\DateTime::class, $output);
        self::assertSame($date->format(\DateTime::RFC3339), $output->format(\DateTime::RFC3339));
    }

    public function testAssignByRef()
    {
        $transformer = new DateTimeImmutableToMutableTransformer();

        self::assertFalse($transformer->assignByRef());
    }

    public function testEmptyDependencies()
    {
        $transformer = new DateTimeImmutableToMutableTransformer();

        self::assertEmpty($transformer->getDependencies());
    }
}
