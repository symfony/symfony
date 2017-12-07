<?php

namespace Symfony\Component\Form\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormError;
use Symfony\Component\Validator\ConstraintViolation;

class FormErrorSerializableTest extends TestCase
{
    public function testSerializeFormError()
    {
        if (!class_exists(ConstraintViolation::class)) {
            $this->markTestSkipped('Validator component required.');
        }

        $simpleXMLElement = new \SimpleXMLElement('<foo></foo>');
        $cause = new ConstraintViolation('Error 1!', null, array(), $simpleXMLElement, '', null, null, '');
        $formError = new FormError('Error 1!', null, array(), null, $cause);
        $expectedSerializedFormError = 'C:32:"Symfony\Component\Form\FormError":211:{a:5:{i:0;s:8:"Error 1!";i:1;s:8:"Error 1!";i:2;a:0:{}i:3;N;i:4;C:47:"Symfony\Component\Validator\ConstraintViolation":87:{a:9:{i:0;s:8:"Error 1!";i:1;N;i:2;a:0:{}i:3;N;i:4;s:0:"";i:5;N;i:6;N;i:7;s:0:"";i:8;N;}}}}';

        $this->assertEquals($expectedSerializedFormError, serialize($formError));
        $this->assertInstanceOf(FormError::class, unserialize(serialize($formError)));
    }
}
