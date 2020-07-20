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
        $constraint = new Bic(['ibanPropertyPath' => 'value']);

        $object = new BicComparisonTestClass('FR14 2004 1010 0505 0001 3M02 606');

        $this->setObject($object);

        $this->validator->validate('SOGEFRPP', $constraint);

        $this->assertNoViolation();
    }

    public function testInvalidComparisonToPropertyPath()
    {
        $constraint = new Bic(['ibanPropertyPath' => 'value']);
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
        $constraint = new Bic(['iban' => 'FR14 2004 1010 0505 0001 3M02 606']);
        $constraint->ibanMessage = 'Constraint Message';

        $this->validator->validate('SOGEFRPP', $constraint);

        $this->assertNoViolation();
    }

    public function testInvalidComparisonToValue()
    {
        $constraint = new Bic(['iban' => 'FR14 2004 1010 0505 0001 3M02 606']);
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
        $constraint = new Bic(['ibanPropertyPath' => 'propertyPath']);

        $this->setObject(null);

        $this->validator->validate('UNCRIT2B912', $constraint);

        $this->assertNoViolation();
    }

    public function testThrowsConstraintExceptionIfBothValueAndPropertyPath()
    {
        $this->expectException('Symfony\Component\Validator\Exception\ConstraintDefinitionException');
        $this->expectExceptionMessage('The "iban" and "ibanPropertyPath" options of the Iban constraint cannot be used at the same time');
        new Bic([
            'iban' => 'value',
            'ibanPropertyPath' => 'propertyPath',
        ]);
    }

    public function testInvalidValuePath()
    {
        $constraint = new Bic(['ibanPropertyPath' => 'foo']);

        $this->expectException(ConstraintDefinitionException::class);
        $this->expectExceptionMessage(sprintf('Invalid property path "foo" provided to "%s" constraint', \get_class($constraint)));

        $object = new BicComparisonTestClass(5);

        $this->setObject($object);

        $this->validator->validate('UNCRIT2B912', $constraint);
    }

    public function testExpectsStringCompatibleType()
    {
        $this->expectException('Symfony\Component\Validator\Exception\UnexpectedValueException');
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
        return [
            ['ASPKAT2LXXX'],
            ['ASPKAT2L'],
            ['DSBACNBXSHA'],
            ['UNCRIT2B912'],
            ['DABADKKK'],
            ['RZOOAT2L303'],
        ];
    }

    /**
     * @dataProvider getInvalidBics
     */
    public function testInvalidBics($bic, $code)
    {
        $constraint = new Bic([
            'message' => 'myMessage',
        ]);

        $this->validator->validate($bic, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"'.$bic.'"')
            ->setCode($code)
            ->assertRaised();
    }

    public function getInvalidBics()
    {
        return [
            ['DEUTD', Bic::INVALID_LENGTH_ERROR],
            ['ASPKAT2LXX', Bic::INVALID_LENGTH_ERROR],
            ['ASPKAT2LX', Bic::INVALID_LENGTH_ERROR],
            ['ASPKAT2LXXX1', Bic::INVALID_LENGTH_ERROR],
            ['DABADKK', Bic::INVALID_LENGTH_ERROR],
            ['1SBACNBXSHA', Bic::INVALID_BANK_CODE_ERROR],
            ['RZ00AT2L303', Bic::INVALID_BANK_CODE_ERROR],
            ['D2BACNBXSHA', Bic::INVALID_BANK_CODE_ERROR],
            ['DS3ACNBXSHA', Bic::INVALID_BANK_CODE_ERROR],
            ['DSB4CNBXSHA', Bic::INVALID_BANK_CODE_ERROR],
            ['DEUT12HH', Bic::INVALID_COUNTRY_CODE_ERROR],
            ['DSBAC6BXSHA', Bic::INVALID_COUNTRY_CODE_ERROR],
            ['DSBA5NBXSHA', Bic::INVALID_COUNTRY_CODE_ERROR],
            ['DSBAAABXSHA', Bic::INVALID_COUNTRY_CODE_ERROR],

            // branch code error
            ['THISSVAL1D]', Bic::INVALID_CHARACTERS_ERROR],

            // location code error
            ['DEUTDEF]', Bic::INVALID_CHARACTERS_ERROR],

            // lower case values are invalid
            ['DeutAT2LXXX', Bic::INVALID_CASE_ERROR],
            ['DEUTAT2lxxx', Bic::INVALID_CASE_ERROR],
        ];
    }

    /**
     * @dataProvider getValidBicSpecialCases
     *
     * Some territories have their own ISO country code but can use another country code
     * for IBAN accounts. Example: "French Guiana" (country code "GF") can use FR too.
     */
    public function testValidBicSpecialCases(string $bic, string $iban)
    {
        $constraint = new Bic(['iban' => $iban]);
        $this->validator->validate($bic, $constraint);

        $this->assertNoViolation();
    }

    public function getValidBicSpecialCases()
    {
        // FR related special cases
        yield ['BNPAGFGX', 'FR14 2004 1010 0505 0001 3M02 606'];
        yield ['BNPAPFGX', 'FR14 2004 1010 0505 0001 3M02 606'];
        yield ['BNPATFGX', 'FR14 2004 1010 0505 0001 3M02 606'];
        yield ['BNPAGPGX', 'FR14 2004 1010 0505 0001 3M02 606'];
        yield ['BNPAMQGX', 'FR14 2004 1010 0505 0001 3M02 606'];
        yield ['BNPAYTGX', 'FR14 2004 1010 0505 0001 3M02 606'];
        yield ['BNPANCGX', 'FR14 2004 1010 0505 0001 3M02 606'];
        yield ['BNPAREGX', 'FR14 2004 1010 0505 0001 3M02 606'];
        yield ['BNPAPMGX', 'FR14 2004 1010 0505 0001 3M02 606'];
        yield ['BNPAWFGX', 'FR14 2004 1010 0505 0001 3M02 606'];

        // GB related special cases
        yield ['BARCJESA', 'GB12 CPBK 0892 9965 0449 911'];
        yield ['BARCIMSA', 'GB12 CPBK 0892 9965 0449 911'];
        yield ['BARCGGSA', 'GB12 CPBK 0892 9965 0449 911'];
        yield ['BARCVGSA', 'GB12 CPBK 0892 9965 0449 911'];
    }
}

class BicComparisonTestClass
{
    protected $value;

    public function __construct($value)
    {
        $this->value = $value;
    }

    public function __toString(): string
    {
        return (string) $this->value;
    }

    public function getValue()
    {
        return $this->value;
    }
}
