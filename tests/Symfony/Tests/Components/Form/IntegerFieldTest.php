<?php

namespace Symfony\Tests\Components\Form;

require_once __DIR__ . '/../../../../bootstrap.php';

use Symfony\Components\Form\IntegerField;

class IntegerFieldTest extends \PHPUnit_Framework_TestCase
{
  public function testBindCastsToInteger()
  {
    $field = new IntegerField('name');

    $field->bind('1.678');

    $this->assertSame(1, $field->getData());
    $this->assertSame('1', $field->getDisplayedData());
  }
}