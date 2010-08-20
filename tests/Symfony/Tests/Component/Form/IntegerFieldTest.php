<?php

namespace Symfony\Tests\Component\Form;

require_once __DIR__ . '/LocalizedTestCase.php';

use Symfony\Component\Form\IntegerField;

class IntegerFieldTest extends LocalizedTestCase
{
    public function testBindCastsToInteger()
    {
        $field = new IntegerField('name');

        $field->bind('1.678');

        $this->assertSame(1, $field->getData());
        $this->assertSame('1', $field->getDisplayedData());
    }
}