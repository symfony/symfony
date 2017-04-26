<?php

namespace Symfony\Component\Validator\Tests\Translation;

use Symfony\Component\Validator\Translation\TranslatorValidator;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use stdClass;

class TranslatorValidatorTest extends \PHPUnit_Framework_TestCase
{
    private $parentValidator;
    private $translatorValidator;
    private $translator;

    public function setUp()
    {
        $this->parentValidator     = $this->getMock('Symfony\Component\Validator\ValidatorInterface');
        $this->translator          = $this->getMock('Symfony\Component\Translation\TranslatorInterface');
        $this->translatorValidator = new TranslatorValidator($this->parentValidator, $this->translator);
    }

    public function createCvlFixture()
    {
        $cvl = new ConstraintViolationList();
        $cvl->add(new ConstraintViolation('message', array(), null, 'path', 1234));

        return $cvl;
    }

    public function testValidate()
    {
        $object = new stdClass;
        $cvl = $this->createCvlFixture();

        $this->mockTranslatorTrans();
        $this->parentValidator->expects($this->once())
                              ->method('validate')
                              ->with($this->equalTo($object), $this->equalTo(array('GROUP')))
                              ->will($this->returnValue($cvl));

        $translatedCvl = $this->translatorValidator->validate($object, array('GROUP'));

        $this->assertConstraintsTranslated($translatedCvl, $cvl);
    }

    public function testValidateTransChoice()
    {
        $cvl = new ConstraintViolationList();
        $cvl->add(new ConstraintViolation('message', array(), null, 'path', 1234, 2));
        $object = new stdClass;

        $this->translator->expects($this->once())
                         ->method('transChoice')
                         ->with($this->equalTo('message'), $this->equalTo(2), $this->equalTo(array()), $this->equalTo('validators'))
                         ->will($this->returnValue('translated message'));

        $this->parentValidator->expects($this->once())
                              ->method('validate')
                              ->with($this->equalTo($object), $this->equalTo(array('GROUP')))
                              ->will($this->returnValue($cvl));

        $translatedCvl = $this->translatorValidator->validate($object, array('GROUP'));

        $this->assertConstraintsTranslated($translatedCvl, $cvl);
    }

    public function testValidateProperty()
    {
        $object = new stdClass;
        $cvl = $this->createCvlFixture();

        $this->mockTranslatorTrans();
        $this->parentValidator->expects($this->once())
                              ->method('validateProperty')
                              ->with($this->equalTo($object), $this->equalTo('prop'), $this->equalTo(array('GROUP')))
                              ->will($this->returnValue($cvl));

        $translatedCvl = $this->translatorValidator->validateProperty($object, 'prop', array('GROUP'));

        $this->assertConstraintsTranslated($translatedCvl, $cvl);
    }
 
    public function testValidatePropertyValue()
    {
        $object = new stdClass;
        $cvl = $this->createCvlFixture();

        $this->mockTranslatorTrans();
        $this->parentValidator->expects($this->once())
                              ->method('validatePropertyValue')
                              ->with($this->equalTo($object), $this->equalTo('prop'), $this->equalTo('val'), $this->equalTo(array('GROUP')))
                              ->will($this->returnValue($cvl));

        $translatedCvl = $this->translatorValidator->validatePropertyValue($object, 'prop', 'val', array('GROUP'));

        $this->assertConstraintsTranslated($translatedCvl, $cvl);
    }

    public function testValidateValue()
    {
        $object = new stdClass;
        $cvl = $this->createCvlFixture();

        $constraint = $this->getMock('Symfony\Component\Validator\Constraint');

        $this->mockTranslatorTrans();
        $this->parentValidator->expects($this->once())
            ->method('validateValue')
            ->with($this->equalTo('val'), $this->equalTo($constraint), $this->equalTo(array('GROUP')))
            ->will($this->returnValue($cvl));

        $translatedCvl = $this->translatorValidator->validateValue('val', $constraint, array('GROUP'));

        $this->assertConstraintsTranslated($translatedCvl, $cvl);
    }

    public function testGetMetadataFactory()
    {
        $this->parentValidator->expects($this->once())->method('getMetadataFactory');

        $this->translatorValidator->getMetadataFactory();
    }

    public function assertConstraintsTranslated($translatedCvl, $originalCvl)
    {
        $this->assertNotSame($translatedCvl, $originalCvl);
        $this->assertEquals(1, count($translatedCvl));

        $violation = $translatedCvl->get(0);

        $this->assertEquals('translated message', $violation->getMessageTemplate());
        $this->assertEquals('translated message', $violation->getMessage());
        $this->assertnull($violation->getRoot());
        $this->assertEquals('path', $violation->getPropertyPath());
        $this->assertEquals(1234, $violation->getInvalidValue());
    }

    public function mockTranslatorTrans()
    {
        $this->translator->expects($this->once())
                         ->method('trans')
                         ->with($this->equalTo('message'), $this->equalTo(array()), $this->equalTo('validators'))
                         ->will($this->returnValue('translated message'));
    }
}

