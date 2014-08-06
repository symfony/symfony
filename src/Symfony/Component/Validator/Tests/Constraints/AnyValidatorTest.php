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

use Symfony\Component\Validator\Constraints\Any;
use Symfony\Component\Validator\Constraints\AnyValidator;
use Symfony\Component\Validator\Constraints\Isbn;
use Symfony\Component\Validator\Constraints\Range;
use Symfony\Component\Validator\Validation;

/**
 * @author Cas Leentfaar <info@casleentfaar.com>
 */
class AnyValidatorTest extends AbstractConstraintValidatorTest
{
    public function testNullIsValid()
    {
        $this->validator->validate(null, new Any(new Range(array('min' => 4))));
        $this->assertNoViolation();
    }

    /**
     * @dataProvider getValidArguments
     */
    public function testWalkSingleConstraint($value)
    {
        $this->validator->validate($value, new Any(new Range(array('min' => 4))));
        $this->assertNoViolation();
    }

    /**
     * @dataProvider getValidArguments
     */
    public function testWalkMultipleConstraints($value)
    {
        $this->validator->validate($value, new Any(array(
            new Range(array('min' => 4)),
            new Isbn(),
        )));
        $this->assertNoViolation();
    }

    public function testNoConstraintValidated()
    {
        $any = new Any(array(
            new Range(array('min' => 4)),
            new Isbn(),
        ));
        $this->validator->validate(1, $any);
        $this->assertViolation($any->message);
    }

    /**
     * @return array
     */
    public function getValidArguments()
    {
        return array(
            array(5),
            array(6),
            array(7),
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function getApiVersion()
    {
        return Validation::API_VERSION_2_5;
    }

    /**
     * {@inheritdoc}
     */
    protected function createValidator()
    {
        return new AnyValidator();
    }
}
