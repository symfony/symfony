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

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Validator\Constraints\L18n;
use Symfony\Component\Validator\Constraints\L18nValidator;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Range;

/**
 * @author Michael Hindley <mikael.chojnacki@gmail.com>
 */
class L18nValidatorTest extends AbstractConstraintValidatorTest
{
    /**
     * @param string $locale
     *
     * @return L18nValidator
     */
    protected function createValidator($locale = 'en')
    {
        $request = new Request();
        $request->setLocale($locale);
        $requestStack = new RequestStack();
        $requestStack->push($request);

        $validator = new L18nValidator($requestStack);

        return $validator;
    }

    public function testNullIsValid()
    {
        $this->validator->validate(null,new L18n(array('locale' => 'en','constraints' => array(new Range(array('min' => 4))))));

        $this->assertNoViolation();
    }

    /**
     * @expectedException \Symfony\Component\Validator\Exception\UnexpectedTypeException
     */
    public function testThrowsExceptionIfNotTraversable()
    {
        $this->validator->validate( 'foo.barbar', new L18n(array('locale' => 'en','constraints' => array(new Range(array('min' => 4))))));
    }

    /**
     * @dataProvider getValidArguments
     */
    public function testWalkSingleConstraint($array)
    {
        $constraint = new Range(array('min' => 4));

        $i = 0;

        foreach ($array as $key => $value) {
            $this->expectValidateValueAt($i++, '[' . $key . ']', $value, array($constraint));
        }

        $this->validator->validate($array, new L18n(array('locale' => 'en', 'constraints' => array($constraint))));

        $this->assertNoViolation();
    }

    /**
     * @dataProvider getValidArguments
     */
    public function testWalkMultipleConstraints($array)
    {
        $constraint1 = new Range(array('min' => 4));
        $constraint2 = new NotNull();

        $constraints = array($constraint1, $constraint2);

        $i = 0;

        foreach ($array as $key => $value) {
            $this->expectValidateValueAt($i++, '[' . $key . ']', $value, array($constraint1, $constraint2));
        }

        $this->validator->validate($array, new L18n(array('locale' => 'en', 'constraints' => $constraints)));

        $this->assertNoViolation();
    }

    public function getValidArguments()
    {
        return array(
            array(array(5, 6, 7)),
            array(new \ArrayObject(array(5, 6, 7))),
        );
    }

}
