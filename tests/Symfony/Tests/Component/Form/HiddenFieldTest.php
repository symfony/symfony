<?php

namespace Symfony\Tests\Component\Form;

use Symfony\Component\Form\HiddenField;

class HiddenFieldTest extends \PHPUnit_Framework_TestCase
{
    protected $field;

    public function setUp()
    {
        $this->field = new HiddenField('name');
    }

    public function testIsHidden()
    {
        $this->assertTrue($this->field->isHidden());
    }
}