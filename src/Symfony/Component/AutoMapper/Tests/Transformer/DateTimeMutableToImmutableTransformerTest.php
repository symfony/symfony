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
use Symfony\Component\AutoMapper\Transformer\DateTimeMutableToImmutableTransformer;

class DateTimeMutableToImmutableTransformerTest extends TestCase
{
    use EvalTransformerTrait;

    public function testDateTimeImmutableTransformer()
    {
        $transformer = new DateTimeMutableToImmutableTransformer();

        $date = new \DateTime();
        $output = $this->evalTransformer($transformer, $date);

        self::assertInstanceOf(\DateTimeImmutable::class, $output);
        self::assertSame($date->format(\DateTime::RFC3339), $output->format(\DateTime::RFC3339));
    }

    public function testAssignByRef()
    {
        $transformer = new DateTimeMutableToImmutableTransformer();

        self::assertFalse($transformer->assignByRef());
    }

    public function testEmptyDependencies()
    {
        $transformer = new DateTimeMutableToImmutableTransformer();

        self::assertEmpty($transformer->getDependencies());
    }
}
