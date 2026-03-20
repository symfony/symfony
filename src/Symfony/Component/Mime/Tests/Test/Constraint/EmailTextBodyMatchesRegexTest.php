<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mime\Tests\Test\Constraint;

use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Test\Constraint\EmailTextBodyMatchesRegex;

class EmailTextBodyMatchesRegexTest extends TestCase
{
    public function testToString()
    {
        $constraint = new EmailTextBodyMatchesRegex('expectedRegex');

        $this->assertSame('matches text body against regex "expectedRegex"', $constraint->toString());
    }

    public function testFailureDescription()
    {
        $expectedRegex = 'expectedRegex';
        $email = new Email();
        $email->text('actualValue');

        $this->expectException(ExpectationFailedException::class);
        $this->expectExceptionMessage('Failed asserting that The Email text body matches text body against regex "expectedRegex". The text body was: "actualValue".');

        (new EmailTextBodyMatchesRegex($expectedRegex))->evaluate($email);
    }
}
