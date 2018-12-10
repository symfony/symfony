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

use Symfony\Component\Validator\Constraints\Bic;
use Symfony\Component\Validator\Constraints\BicValidator;
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

class BicValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator()
    {
        return new BicValidator();
    }

    public function testNullIsValid()
    {
        $this->validator->validate(null, new Bic());

        $this->assertNoViolation();
    }

    public function testEmptyStringIsValid()
    {
        $this->validator->validate('', new Bic());

        $this->assertNoViolation();
    }

    public function testValidComparisonToPropertyPath()
    {
        $constraint = new Bic(array('ibanPropertyPath' => 'value'));

        $object = new BicComparisonTestClass('FR14 2004 1010 0505 0001 3M02 606');

        $this->setObject($object);

        $this->validator->validate('SOGEFRPP', $constraint);

        $this->assertNoViolation();
    }

    public function testValidComparisonToPropertyPathOnArray()
    {
        $constraint = new Bic(array('ibanPropertyPath' => '[root][value]'));

        $this->setObject(array('root' => array('value' => 'FR14 2004 1010 0505 0001 3M02 606')));

        $this->validator->validate('SOGEFRPP', $constraint);

        $this->assertNoViolation();
    }

    public function testInvalidComparisonToPropertyPath()
    {
        $constraint = new Bic(array('ibanPropertyPath' => 'value'));
        $constraint->ibanMessage = 'Constraint Message';

        $object = new BicComparisonTestClass('FR14 2004 1010 0505 0001 3M02 606');

        $this->setObject($object);

        $this->validator->validate('UNCRIT2B912', $constraint);

        $this->buildViolation('Constraint Message')
            ->setParameter('{{ value }}', '"UNCRIT2B912"')
            ->setParameter('{{ iban }}', 'FR14 2004 1010 0505 0001 3M02 606')
            ->setCode(Bic::INVALID_IBAN_COUNTRY_CODE_ERROR)
            ->assertRaised();
    }

    public function testValidComparisonToValue()
    {
        $constraint = new Bic(array('iban' => 'FR14 2004 1010 0505 0001 3M02 606'));
        $constraint->ibanMessage = 'Constraint Message';

        $this->validator->validate('SOGEFRPP', $constraint);

        $this->assertNoViolation();
    }

    public function testInvalidComparisonToValue()
    {
        $constraint = new Bic(array('iban' => 'FR14 2004 1010 0505 0001 3M02 606'));
        $constraint->ibanMessage = 'Constraint Message';

        $this->validator->validate('UNCRIT2B912', $constraint);

        $this->buildViolation('Constraint Message')
            ->setParameter('{{ value }}', '"UNCRIT2B912"')
            ->setParameter('{{ iban }}', 'FR14 2004 1010 0505 0001 3M02 606')
            ->setCode(Bic::INVALID_IBAN_COUNTRY_CODE_ERROR)
            ->assertRaised();
    }

    public function testNoViolationOnNullObjectWithPropertyPath()
    {
        $constraint = new Bic(array('ibanPropertyPath' => 'propertyPath'));

        $this->setObject(null);

        $this->validator->validate('UNCRIT2B912', $constraint);

        $this->assertNoViolation();
    }

    /**
     * @expectedException \Symfony\Component\Validator\Exception\ConstraintDefinitionException
     * @expectedExceptionMessage The "iban" and "ibanPropertyPath" options of the Iban constraint cannot be used at the same time
     */
    public function testThrowsConstraintExceptionIfBothValueAndPropertyPath()
    {
        new Bic(array(
            'iban' => 'value',
            'ibanPropertyPath' => 'propertyPath',
        ));
    }

    public function testInvalidValuePath()
    {
        $constraint = new Bic(array('ibanPropertyPath' => 'foo'));

        if (method_exists($this, 'expectException')) {
            $this->expectException(ConstraintDefinitionException::class);
            $this->expectExceptionMessage(sprintf('Invalid property path "foo" provided to "%s" constraint', \get_class($constraint)));
        } else {
            $this->setExpectedException(ConstraintDefinitionException::class, sprintf('Invalid property path "foo" provided to "%s" constraint', \get_class($constraint)));
        }

        $object = new BicComparisonTestClass(5);

        $this->setObject($object);

        $this->validator->validate('UNCRIT2B912', $constraint);
    }

    /**
     * @expectedException \Symfony\Component\Validator\Exception\UnexpectedValueException
     */
    public function testExpectsStringCompatibleType()
    {
        $this->validator->validate(new \stdClass(), new Bic());
    }

    /**
     * @dataProvider getValidBics
     */
    public function testValidBics($bic)
    {
        $this->validator->validate($bic, new Bic());

        $this->assertNoViolation();
    }

    public function getValidBics()
    {
        // http://formvalidation.io/validators/bic/
        return array(
            array('ASPKAT2LXXX'),
            array('ASPKAT2L'),
            array('DSBACNBXSHA'),
            array('UNCRIT2B912'),
            array('DABADKKK'),
            array('RZOOAT2L303'),
        );
    }

    /**
     * @dataProvider getInvalidBics
     */
    public function testInvalidBics($bic, $code)
    {
        $constraint = new Bic(array(
            'message' => 'myMessage',
        ));

        $this->validator->validate($bic, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"'.$bic.'"')
            ->setCode($code)
            ->assertRaised();
    }

    public function getInvalidBics()
    {
        return array(
            array('DEUTD', Bic::INVALID_LENGTH_ERROR),
            array('ASPKAT2LXX', Bic::INVALID_LENGTH_ERROR),
            array('ASPKAT2LX', Bic::INVALID_LENGTH_ERROR),
            array('ASPKAT2LXXX1', Bic::INVALID_LENGTH_ERROR),
            array('DABADKK', Bic::INVALID_LENGTH_ERROR),
            array('1SBACNBXSHA', Bic::INVALID_BANK_CODE_ERROR),
            array('RZ00AT2L303', Bic::INVALID_BANK_CODE_ERROR),
            array('D2BACNBXSHA', Bic::INVALID_BANK_CODE_ERROR),
            array('DS3ACNBXSHA', Bic::INVALID_BANK_CODE_ERROR),
            array('DSB4CNBXSHA', Bic::INVALID_BANK_CODE_ERROR),
            array('DEUT12HH', Bic::INVALID_COUNTRY_CODE_ERROR),
            array('DSBAC6BXSHA', Bic::INVALID_COUNTRY_CODE_ERROR),
            array('DSBA5NBXSHA', Bic::INVALID_COUNTRY_CODE_ERROR),
            array('DSBAAABXSHA', Bic::INVALID_COUNTRY_CODE_ERROR),

            // branch code error
            array('THISSVAL1D]', Bic::INVALID_CHARACTERS_ERROR),

            // location code error
            array('DEUTDEF]', Bic::INVALID_CHARACTERS_ERROR),

            // lower case values are invalid
            array('DeutAT2LXXX', Bic::INVALID_CASE_ERROR),
            array('DEUTAT2lxxx', Bic::INVALID_CASE_ERROR),
        );
    }
}

class BicComparisonTestClass
{
    protected $value;

    public function __construct($value)
    {
        $this->value = $value;
    }

    public function __toString()
    {
        return (string) $this->value;
    }

    public function getValue()
    {
        return $this->value;
    }
}
