<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\Translator;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilder;

class ConstraintViolationBuilderTest extends TestCase
{
    private $violations;
    private $builder;

    protected function setUp(): void
    {
        $this->violations = new ConstraintViolationList();

        $this->builder = new ConstraintViolationBuilder(
            $this->violations,
            new NotBlank(),
            "myMessage",
            [],
            null,
            'root',
            null,
            new Translator('en_EN')
        );
    }

    public function testMultipleAtPathCall() {
        $this->builder
            ->atPath('firstName')
            ->atPath('lastName')
            ->atPath('email')
            ->addViolation();

        $violationFirst = $this->violations->get(0);
        $violationLast = $this->violations->get(2);

        $this->assertCount(3, $this->violations);
        $this->assertEquals('root.firstName', $violationFirst->getPropertyPath());
        $this->assertEquals('myMessage', $violationFirst->getMessage());
        $this->assertEquals('root.email', $violationLast->getPropertyPath());
        $this->assertEquals('myMessage', $violationLast->getMessage());
    }
}
