<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Tests\Constraints;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Constraints\Timezone;

/**
 * @author Javier Spagnoletti <phansys@gmail.com>
 */
class TimezoneTest extends TestCase
{
    public function testValidTimezoneConstraints()
    {
        $constraint = new Timezone();

        $constraint = new Timezone(array(
            'message' => 'myMessage',
            'timezone' => \DateTimeZone::PER_COUNTRY,
            'countryCode' => 'AR',
        ));

        $constraint = new Timezone(array(
            'message' => 'myMessage',
            'timezone' => \DateTimeZone::ALL,
        ));
    }

    /**
     * @expectedException \Symfony\Component\Validator\Exception\ConstraintDefinitionException
     */
    public function testExceptionForGroupedTimezonesByCountryWithWrongTimezone()
    {
        $constraint = new Timezone(array(
            'message' => 'myMessage',
            'timezone' => \DateTimeZone::ALL,
            'countryCode' => 'AR',
        ));
    }

    /**
     * @expectedException \Symfony\Component\Validator\Exception\ConstraintDefinitionException
     */
    public function testExceptionForGroupedTimezonesByCountryWithoutTimezone()
    {
        $constraint = new Timezone(array(
            'message' => 'myMessage',
            'countryCode' => 'AR',
        ));
    }
}
