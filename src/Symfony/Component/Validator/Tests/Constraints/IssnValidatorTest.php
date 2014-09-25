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

use Symfony\Component\Validator\Constraints\Issn;
use Symfony\Component\Validator\Constraints\IssnValidator;

/**
 * @see https://en.wikipedia.org/wiki/Issn
 */
class IssnValidatorTest extends AbstractConstraintValidatorTest
{
    protected function createValidator()
    {
        return new IssnValidator();
    }

    public function getValidLowerCasedIssn()
    {
        return array(
            array('2162-321x'),
            array('2160-200x'),
            array('1537-453x'),
            array('1937-710x'),
            array('0002-922x'),
            array('1553-345x'),
            array('1553-619x'),
        );
    }

    public function getValidNonHyphenatedIssn()
    {
        return array(
            array('2162321X'),
            array('01896016'),
            array('15744647'),
            array('14350645'),
            array('07174055'),
            array('20905076'),
            array('14401592'),
        );
    }

    public function getFullValidIssn()
    {
        return array(
            array('1550-7416'),
            array('1539-8560'),
            array('2156-5376'),
            array('1119-023X'),
            array('1684-5315'),
            array('1996-0786'),
            array('1684-5374'),
            array('1996-0794'),
        );
    }

    public function getValidIssn()
    {
        return array_merge(
            $this->getValidLowerCasedIssn(),
            $this->getValidNonHyphenatedIssn(),
            $this->getFullValidIssn()
        );
    }

    public function getInvalidFormatedIssn()
    {
        return array(
            array(0),
            array('1539'),
            array('2156-537A'),
        );
    }

    public function getInvalidValueIssn()
    {
        return array(
            array('1119-0231'),
            array('1684-5312'),
            array('1996-0783'),
            array('1684-537X'),
            array('1996-0795'),
        );
    }

    public function getInvalidIssn()
    {
        return array_merge(
            $this->getInvalidFormatedIssn(),
            $this->getInvalidValueIssn()
        );
    }

    public function testNullIsValid()
    {
        $constraint = new Issn();

        $this->validator->validate(null, $constraint);

        $this->assertNoViolation();
    }

    public function testEmptyStringIsValid()
    {
        $constraint = new Issn();

        $this->validator->validate('', $constraint);

        $this->assertNoViolation();
    }

    /**
     * @expectedException \Symfony\Component\Validator\Exception\UnexpectedTypeException
     */
    public function testExpectsStringCompatibleType()
    {
        $constraint = new Issn();
        $this->validator->validate(new \stdClass(), $constraint);
    }

    /**
     * @dataProvider getValidLowerCasedIssn
     */
    public function testCaseSensitiveIssns($issn)
    {
        $constraint = new Issn(array(
            'caseSensitive' => true,
            'message' => 'myMessage',
        ));

        $this->validator->validate($issn, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"'.$issn.'"')
            ->assertRaised();
    }

    /**
     * @dataProvider getValidNonHyphenatedIssn
     */
    public function testRequireHyphenIssns($issn)
    {
        $constraint = new Issn(array(
            'requireHyphen' => true,
            'message' => 'myMessage',
        ));

        $this->validator->validate($issn, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"'.$issn.'"')
            ->assertRaised();
    }

    /**
     * @dataProvider getValidIssn
     */
    public function testValidIssn($issn)
    {
        $constraint = new Issn();

        $this->validator->validate($issn, $constraint);

        $this->assertNoViolation();
    }

    /**
     * @dataProvider getInvalidFormatedIssn
     */
    public function testInvalidFormatIssn($issn)
    {
        $constraint = new Issn(array(
            'message' => 'myMessage',
        ));

        $this->validator->validate($issn, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"'.$issn.'"')
            ->assertRaised();
    }

    /**
     * @dataProvider getInvalidValueIssn
     */
    public function testInvalidValueIssn($issn)
    {
        $constraint = new Issn(array(
            'message' => 'myMessage',
        ));

        $this->validator->validate($issn, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"'.$issn.'"')
            ->assertRaised();
    }

    /**
     * @dataProvider getInvalidIssn
     */
    public function testInvalidIssn($issn)
    {
        $constraint = new Issn(array(
            'message' => 'myMessage',
        ));

        $this->validator->validate($issn, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"'.$issn.'"')
            ->assertRaised();
    }
}
