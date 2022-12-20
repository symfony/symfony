<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Extension\Core\DataTransformer;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Exception\InvalidArgumentException;
use Symfony\Component\Form\Extension\Core\DataTransformer\BaseDateTimeTransformer;

abstract class BaseDateTimeTransformerTest extends TestCase
{
    public function testConstructFailsIfInputTimezoneIsInvalid()
    {
        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('this_timezone_does_not_exist');
        $this->createDateTimeTransformer('this_timezone_does_not_exist');
    }

    public function testConstructFailsIfOutputTimezoneIsInvalid()
    {
        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('that_timezone_does_not_exist');
        $this->createDateTimeTransformer(null, 'that_timezone_does_not_exist');
    }

    abstract protected function createDateTimeTransformer(string $inputTimezone = null, string $outputTimezone = null): BaseDateTimeTransformer;
}
