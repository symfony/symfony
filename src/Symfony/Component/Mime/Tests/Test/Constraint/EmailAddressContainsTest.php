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
use Symfony\Component\Mime\Header\Headers;
use Symfony\Component\Mime\Test\Constraint\EmailAddressContains;

class EmailAddressContainsTest extends TestCase
{
    public function testToString()
    {
        $constraint = new EmailAddressContains('headerName', 'expectedValue');

        $this->assertSame('contains address "headerName" with value "expectedValue"', $constraint->toString());
    }

    public function testFailureDescription()
    {
        $mailboxHeader = 'text@example.com';
        $headers = new Headers();
        $headers->addMailboxHeader($mailboxHeader, 'actualValue@example.com');

        $this->expectException(ExpectationFailedException::class);
        $this->expectExceptionMessage('Failed asserting that the Email contains address "text@example.com" with value "expectedValue@example.com" (value is actualValue@example.com).');

        (new EmailAddressContains($mailboxHeader, 'expectedValue@example.com'))->evaluate(new Email($headers));
    }
}
