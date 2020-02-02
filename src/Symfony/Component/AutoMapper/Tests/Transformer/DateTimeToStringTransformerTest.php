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
use Symfony\Component\AutoMapper\Transformer\DateTimeToStringTansformer;

class DateTimeToStringTransformerTest extends TestCase
{
    use EvalTransformerTrait;

    public function testDateTimeTransformer()
    {
        $transformer = new DateTimeToStringTansformer();

        $date = new \DateTime();
        $output = $this->evalTransformer($transformer, new \DateTime());

        self::assertSame($date->format(\DateTime::RFC3339), $output);
    }

    public function testDateTimeTransformerCustomFormat()
    {
        $transformer = new DateTimeToStringTansformer(\DateTime::COOKIE);

        $date = new \DateTime();
        $output = $this->evalTransformer($transformer, new \DateTime());

        self::assertSame($date->format(\DateTime::COOKIE), $output);
    }
}
