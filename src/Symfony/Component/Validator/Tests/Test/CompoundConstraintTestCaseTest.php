<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Tests\Test;

use PHPUnit\Framework\ExpectationFailedException;
use Symfony\Component\Validator\Constraints\Compound;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Test\CompoundConstraintTestCase;
use Symfony\Component\Validator\Tests\Fixtures\DummyCompoundConstraint;

/**
 * @extends CompoundConstraintTestCase<DummyCompoundConstraint>
 */
class CompoundConstraintTestCaseTest extends CompoundConstraintTestCase
{
    protected function createCompound(): Compound
    {
        return new DummyCompoundConstraint();
    }

    public function testAssertNoViolation()
    {
        $this->validateValue('ab1');

        $this->assertNoViolation();
        $this->assertViolationsCount(0);
    }

    public function testAssertIsRaisedByCompound()
    {
        $this->validateValue('');

        $this->assertViolationsRaisedByCompound(new NotBlank());
        $this->assertViolationsCount(1);
    }

    public function testMultipleAssertAreRaisedByCompound()
    {
        $this->validateValue('1245');

        $this->assertViolationsRaisedByCompound([
            new Length(max: 3),
            new Regex('/[a-z]+/'),
        ]);
        $this->assertViolationsCount(2);
    }

    public function testNoAssertRaisedButExpected()
    {
        $this->validateValue('azert');

        $this->expectException(ExpectationFailedException::class);
        $this->expectExceptionMessage("Expected violation(s) for constraint(s) Symfony\Component\Validator\Constraints\Length, Symfony\Component\Validator\Constraints\Regex to be raised by compound.");
        $this->assertViolationsRaisedByCompound([
            new Length(max: 5),
            new Regex('/^[A-Z]+$/'),
        ]);
    }

    public function testAssertRaisedByCompoundIsNotExactlyTheSame()
    {
        $this->validateValue('123');

        $this->expectException(ExpectationFailedException::class);
        $this->expectExceptionMessage('Expected violation(s) for constraint(s) Symfony\Component\Validator\Constraints\Regex to be raised by compound.');
        $this->assertViolationsRaisedByCompound(new Regex('/^[a-z]+$/'));
    }

    public function testAssertRaisedByCompoundButGotNone()
    {
        $this->validateValue('123');

        $this->expectException(ExpectationFailedException::class);
        $this->expectExceptionMessage('Expected at least one violation for constraint(s) "Symfony\Component\Validator\Constraints\Length", got none raised.');
        $this->assertViolationsRaisedByCompound(new Length(max: 5));
    }
}
