<?php

namespace Symfony\Tests\Component\Form;

require_once __DIR__ . '/LocalizedTestCase.php';

class DateTimeTestCase extends LocalizedTestCase
{
    public static function assertDateTimeEquals(\DateTime $expected, \DateTime $actual)
    {
        self::assertEquals($expected->format('c'), $actual->format('c'));
    }
}