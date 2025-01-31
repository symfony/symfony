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

use Symfony\Component\Validator\Constraints\Base64Encoded;
use Symfony\Component\Validator\Constraints\Base64EncodedValidator;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * @author Refat Alsakka <refatalsakka@gmail.com>
 */
class Base64EncodedValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): Base64EncodedValidator
    {
        return new Base64EncodedValidator();
    }

    public function testValidBase64()
    {
        $this->validator->validate('U3ltZm9ueQ==', new Base64Encoded());
        $this->assertNoViolation();
    }

    public function testInvalidBase64()
    {
        $this->validator->validate('invalid@base64', new Base64Encoded());

        $this->buildViolation('The value "{{ value }}" is not a valid Base64-encoded string.')
            ->setParameter('{{ value }}', 'invalid@base64')
            ->assertRaised();
    }
}
