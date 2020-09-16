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
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validation;

/**
 * @author Jan Vernieuwe <jan.vernieuwe@phpro.be>
 */
class ValidationTest extends TestCase
{
    public function testCreateCallableValid()
    {
        $validator = Validation::createCallable(new Email());
        $this->assertEquals('test@example.com', $validator('test@example.com'));
    }

    public function testCreateCallableInvalid()
    {
        $validator = Validation::createCallable(new Email());
        $this->expectException(ValidationFailedException::class);
        $validator('test');
    }
}
