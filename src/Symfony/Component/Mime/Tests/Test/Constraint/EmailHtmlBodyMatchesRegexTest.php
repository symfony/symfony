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
use Symfony\Component\Mime\Test\Constraint\EmailHtmlBodyMatchesRegex;

class EmailHtmlBodyMatchesRegexTest extends TestCase
{
    public function testToString()
    {
        $constraint = new EmailHtmlBodyMatchesRegex('expectedRegex');

        $this->assertSame('matches HTML body against regex "expectedRegex"', $constraint->toString());
    }

    public function testFailureDescription()
    {
        $expectedRegex = 'expectedRegex';
        $email = new Email();
        $email->html('actualValue');

        $this->expectException(ExpectationFailedException::class);
        $this->expectExceptionMessage('Failed asserting that The Email HTML body matches HTML body against regex "expectedRegex". The HTML body was: "actualValue".');

        (new EmailHtmlBodyMatchesRegex($expectedRegex))->evaluate($email);
    }
}
