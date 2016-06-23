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

use Symfony\Component\Validator\Constraints\Orcid;
use Symfony\Component\Validator\Constraints\OrcidValidator;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * @see http://orcid.org/
 */
class OrcidValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator()
    {
        return new OrcidValidator();
    }

    public function getValidLowerCasedOrcid()
    {
        return array(
            array('0000-0002-5060-860x'),
            array('0000-0003-1497-153x'),
            array('0000-0002-4752-937x'),
            array('0000-0003-0242-271x'),
            array('0000-0002-7934-453x'),
            array('6416-1779-1877-482x'),
            array('8806-8538-5539-947x'),
        );
    }

    public function getValidNonHyphenatedOrcid()
    {
        return array(
            array('000000028930008X'),
            array('0000000271028233'),
            array('0000000197839598'),
            array('0000000286629074'),
            array('0000000276958771'),
            array('4323660338597675'),
            array('4977785501386669'),
        );
    }

    public function getFullValidOrcid()
    {
        return array(
            array('0000-0002-1072-0023'),
            array('0000-0002-0767-5639'),
            array('0000-0002-1992-2596'),
            array('0000-0002-8968-428X'),
            array('0000-0003-2171-7580'),
            array('0000-0002-0704-6816'),
            array('2128-7213-5528-3868'),
            array('7385-7401-0622-3537'),
        );
    }

    public function getValidOrcid()
    {
        return array_merge(
            $this->getValidLowerCasedOrcid(),
            $this->getValidNonHyphenatedOrcid(),
            $this->getFullValidOrcid()
        );
    }

    public function getInvalidOrcid()
    {
        return array(
            array(0, Orcid::TOO_SHORT_ERROR),
            array('5478', Orcid::TOO_SHORT_ERROR),
            array('0000-0001-9672-186A', Orcid::INVALID_CHARACTERS_ERROR),
            array('0000-0002-5069-0325', Orcid::CHECKSUM_FAILED_ERROR),
            array('0000-0002-6182-2774', Orcid::CHECKSUM_FAILED_ERROR),
            array('0000-0002-3506-8890', Orcid::CHECKSUM_FAILED_ERROR),
            array('1595-6914-7121-3503', Orcid::CHECKSUM_FAILED_ERROR),
            array('7075-1299-0098-3944', Orcid::CHECKSUM_FAILED_ERROR),
        );
    }

    public function testNullIsValid()
    {
        $constraint = new Orcid();

        $this->validator->validate(null, $constraint);

        $this->assertNoViolation();
    }

    public function testEmptyStringIsValid()
    {
        $constraint = new Orcid();

        $this->validator->validate('', $constraint);

        $this->assertNoViolation();
    }

    public function testURIExpressionIsValid()
    {
        $constraint = new Orcid();

        $this->validator->validate('http://orcid.org/0000-0002-8968-428X', $constraint);

        $this->assertNoViolation();
    }

    /**
     * @expectedException \Symfony\Component\Validator\Exception\UnexpectedTypeException
     */
    public function testExpectsStringCompatibleType()
    {
        $constraint = new Orcid();
        $this->validator->validate(new \stdClass(), $constraint);
    }

    /**
     * @dataProvider getValidLowerCasedOrcid
     */
    public function testCaseSensitiveOrcids($orcid)
    {
        $constraint = new Orcid(array(
            'caseSensitive' => true,
            'message' => 'myMessage',
        ));

        $this->validator->validate($orcid, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"'.$orcid.'"')
            ->setCode(Orcid::INVALID_CASE_ERROR)
            ->assertRaised();
    }

    /**
     * @dataProvider getValidNonHyphenatedOrcid
     */
    public function testRequireHyphenOrcids($orcid)
    {
        $constraint = new Orcid(array(
            'requireHyphens' => true,
            'message' => 'myMessage',
        ));

        $this->validator->validate($orcid, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"'.$orcid.'"')
            ->setCode(Orcid::MISSING_HYPHENS_ERROR)
            ->assertRaised();
    }

    /**
     * @dataProvider getValidOrcid
     */
    public function testValidOrcid($orcid)
    {
        $constraint = new Orcid();

        $this->validator->validate($orcid, $constraint);

        $this->assertNoViolation();
    }

    /**
     * @dataProvider getInvalidOrcid
     */
    public function testInvalidOrcid($orcid, $code)
    {
        $constraint = new Orcid(array(
            'message' => 'myMessage',
        ));

        $this->validator->validate($orcid, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"'.$orcid.'"')
            ->setCode($code)
            ->assertRaised();
    }
}
