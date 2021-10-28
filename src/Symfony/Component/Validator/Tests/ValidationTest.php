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
use Symfony\Bridge\PhpUnit\ExpectDeprecationTrait;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validation;

/**
 * @author Jan Vernieuwe <jan.vernieuwe@phpro.be>
 */
class ValidationTest extends TestCase
{
    use ExpectDeprecationTrait;

    /**
     * @group legacy
     */
    public function testCreateCallableValid()
    {
        $validator = Validation::createCallable(new Email());
        $this->expectDeprecation('Since symfony/validator 5.4: The "loose" email validation mode is deprecated, use "html5" instead');
        $this->assertEquals('test@example.com', $validator('test@example.com'));
    }

    /**
     * @group legacy
     */
    public function testCreateCallableInvalid()
    {
        $validator = Validation::createCallable(new Email());
        $this->expectDeprecation('Since symfony/validator 5.4: The "loose" email validation mode is deprecated, use "html5" instead');
        try {
            $validator('test');
            $this->fail('No ValidationFailedException thrown');
        } catch (ValidationFailedException $e) {
            $this->assertEquals('test', $e->getValue());

            $violations = $e->getViolations();
            $this->assertCount(1, $violations);
            $this->assertEquals('This value is not a valid email address.', $violations->get(0)->getMessage());
        }
    }

    /**
     * @group legacy
     */
    public function testCreateIsValidCallableValid()
    {
        $validator = Validation::createIsValidCallable(new Email());
        $this->expectDeprecation('Since symfony/validator 5.4: The "loose" email validation mode is deprecated, use "html5" instead');
        $this->assertTrue($validator('test@example.com'));
    }

    /**
     * @group legacy
     */
    public function testCreateIsValidCallableInvalid()
    {
        $validator = Validation::createIsValidCallable(new Email());
        $this->expectDeprecation('Since symfony/validator 5.4: The "loose" email validation mode is deprecated, use "html5" instead');
        $this->assertFalse($validator('test', $violations));
        $this->assertCount(1, $violations);
        $this->assertEquals('This value is not a valid email address.', $violations->get(0)->getMessage());
    }
}
