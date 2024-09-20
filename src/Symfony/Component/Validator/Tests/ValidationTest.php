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
use Symfony\Component\Validator\Constraints\Blank;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validation;

/**
 * @author Jan Vernieuwe <jan.vernieuwe@phpro.be>
 */
class ValidationTest extends TestCase
{
    public function testCreateCallableValid()
    {
        $validator = Validation::createCallable(new NotBlank());
        $this->assertEquals('test@example.com', $validator('test@example.com'));
    }

    public function testCreateCallableInvalid()
    {
        $validator = Validation::createCallable(new Blank());
        try {
            $validator('test');
            $this->fail('No ValidationFailedException thrown');
        } catch (ValidationFailedException $e) {
            $this->assertEquals('test', $e->getValue());

            $violations = $e->getViolations();
            $this->assertCount(1, $violations);
            $this->assertEquals('This value should be blank.', $violations->get(0)->getMessage());
        }
    }

    public function testCreateIsValidCallableValid()
    {
        $validator = Validation::createIsValidCallable(new NotBlank());
        $this->assertTrue($validator('test@example.com'));
    }

    public function testCreateIsValidCallableInvalid()
    {
        $validator = Validation::createIsValidCallable(new Blank());
        $this->assertFalse($validator('test', $violations));
        $this->assertCount(1, $violations);
        $this->assertEquals('This value should be blank.', $violations->get(0)->getMessage());
    }
}
