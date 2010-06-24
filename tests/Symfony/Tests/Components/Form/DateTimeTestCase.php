<?php

namespace Symfony\Tests\Components\Form;

class DateTimeTestCase extends \PHPUnit_Framework_TestCase
{
  public static function assertDateTimeEquals(\DateTime $expected, \DateTime $actual)
  {
    self::assertEquals($expected->format('c'), $actual->format('c'));
  }
}