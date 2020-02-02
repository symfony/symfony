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
use Symfony\Component\AutoMapper\Transformer\StringToDateTimeTransformer;

class StringToDateTimeTransformerTest extends TestCase
{
    use EvalTransformerTrait;

    public function testDateTimeTransformer()
    {
        $transformer = new StringToDateTimeTransformer(\DateTime::class);

        $date = new \DateTime();
        $output = $this->evalTransformer($transformer, $date->format(\DateTime::RFC3339));

        self::assertInstanceOf(\DateTime::class, $output);
        self::assertSame($date->format(\DateTime::RFC3339), $output->format(\DateTime::RFC3339));
    }

    public function testDateTimeTransformerCustomFormat()
    {
        $transformer = new StringToDateTimeTransformer(\DateTime::class, \DateTime::COOKIE);

        $date = new \DateTime();
        $output = $this->evalTransformer($transformer, $date->format(\DateTime::COOKIE));

        self::assertInstanceOf(\DateTime::class, $output);
        self::assertSame($date->format(\DateTime::RFC3339), $output->format(\DateTime::RFC3339));
    }

    public function testDateTimeTransformerImmutable()
    {
        $transformer = new StringToDateTimeTransformer(\DateTimeImmutable::class, \DateTime::COOKIE);

        $date = new \DateTime();
        $output = $this->evalTransformer($transformer, $date->format(\DateTime::COOKIE));

        self::assertInstanceOf(\DateTimeImmutable::class, $output);
    }
}
