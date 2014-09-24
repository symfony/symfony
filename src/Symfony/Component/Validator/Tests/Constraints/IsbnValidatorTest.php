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

use Symfony\Component\Validator\Constraints\Isbn;
use Symfony\Component\Validator\Constraints\IsbnValidator;

/**
 * @see https://en.wikipedia.org/wiki/Isbn
 */
class IsbnValidatorTest extends AbstractConstraintValidatorTest
{
    protected function createValidator()
    {
        return new IsbnValidator();
    }

    public function getValidIsbn10()
    {
        return array(
            array('2723442284'),
            array('2723442276'),
            array('2723455041'),
            array('2070546810'),
            array('2711858839'),
            array('2756406767'),
            array('2870971648'),
            array('226623854X'),
            array('2851806424'),
            array('0321812700'),
            array('0-45122-5244'),
            array('0-4712-92311'),
            array('0-9752298-0-X'),
        );
    }

    public function getInvalidIsbn10()
    {
        return array(
            array('1234567890'),
            array('987'),
            array('0987656789'),
            array(0),
            array('7-35622-5444'),
            array('0-4X19-92611'),
            array('0_45122_5244'),
            array('2870#971#648'),
            //array('0-9752298-0-x'),
            array('1A34567890'),
            // chr(1) evaluates to 0
            // 2070546810 is valid
            array('2'.chr(1).'70546810'),
        );
    }

    public function getValidIsbn13()
    {
        return array(
            array('978-2723442282'),
            array('978-2723442275'),
            array('978-2723455046'),
            array('978-2070546817'),
            array('978-2711858835'),
            array('978-2756406763'),
            array('978-2870971642'),
            array('978-2266238540'),
            array('978-2851806420'),
            array('978-0321812704'),
            array('978-0451225245'),
            array('978-0471292319'),
        );
    }

    public function getInvalidIsbn13()
    {
        return array(
            array('1234567890'),
            array('987'),
            array('0987656789'),
            array(0),
            array('0-4X19-9261981'),
            array('978-0321513774'),
            array('979-0431225385'),
            array('980-0474292319'),
            array('978_0451225245'),
            array('978#0471292319'),
            array('978-272C442282'),
            // chr(1) evaluates to 0
            // 978-2070546817 is valid
            array('978-2'.chr(1).'70546817'),
        );
    }

    public function getValidIsbn()
    {
        return array_merge(
            $this->getValidIsbn10(),
            $this->getValidIsbn13()
        );
    }

    public function getInvalidIsbn()
    {
        return array_merge(
            $this->getInvalidIsbn10(),
            $this->getInvalidIsbn13()
        );
    }

    public function testNullIsValid()
    {
        $constraint = new Isbn(true);

        $this->validator->validate(null, $constraint);

        $this->assertNoViolation();
    }

    public function testEmptyStringIsValid()
    {
        $constraint = new Isbn(true);

        $this->validator->validate('', $constraint);

        $this->assertNoViolation();
    }

    /**
     * @expectedException \Symfony\Component\Validator\Exception\UnexpectedTypeException
     */
    public function testExpectsStringCompatibleType()
    {
        $constraint = new Isbn(true);

        $this->validator->validate(new \stdClass(), $constraint);
    }

    /**
     * @dataProvider getValidIsbn10
     */
    public function testValidIsbn10($isbn)
    {
        $constraint = new Isbn(array(
            'isbn10' => true,
        ));

        $this->validator->validate($isbn, $constraint);

        $this->assertNoViolation();
    }

    /**
     * @dataProvider getInvalidIsbn10
     */
    public function testInvalidIsbn10($isbn)
    {
        $constraint = new Isbn(array(
            'isbn10' => true,
            'isbn10Message' => 'myMessage',
        ));

        $this->validator->validate($isbn, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"'.$isbn.'"')
            ->assertRaised();
    }

    /**
     * @dataProvider getValidIsbn13
     */
    public function testValidIsbn13($isbn)
    {
        $constraint = new Isbn(array('isbn13' => true));

        $this->validator->validate($isbn, $constraint);

        $this->assertNoViolation();
    }

    /**
     * @dataProvider getInvalidIsbn13
     */
    public function testInvalidIsbn13($isbn)
    {
        $constraint = new Isbn(array(
            'isbn13' => true,
            'isbn13Message' => 'myMessage',
        ));

        $this->validator->validate($isbn, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"'.$isbn.'"')
            ->assertRaised();
    }

    /**
     * @dataProvider getValidIsbn
     */
    public function testValidIsbn($isbn)
    {
        $constraint = new Isbn(array(
            'isbn10' => true,
            'isbn13' => true,
        ));

        $this->validator->validate($isbn, $constraint);

        $this->assertNoViolation();
    }

    /**
     * @dataProvider getInvalidIsbn
     */
    public function testInvalidIsbn($isbn)
    {
        $constraint = new Isbn(array(
            'isbn10' => true,
            'isbn13' => true,
            'bothIsbnMessage' => 'myMessage',
        ));

        $this->validator->validate($isbn, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"'.$isbn.'"')
            ->assertRaised();
    }
}
