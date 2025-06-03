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
use Symfony\Component\Mime\Test\Constraint\EmailHasHeader;

class EmailHasHeaderTest extends TestCase
{
    public function testToString()
    {
        $constraint = new EmailHasHeader('headerName');

        $this->assertSame('has header "headerName"', $constraint->toString());
    }

    public function testFailureDescription()
    {
        $headers = new Headers();
        $headers->addMailboxHeader('headerName', 'test@example.com');

        $this->expectException(ExpectationFailedException::class);
        $this->expectExceptionMessage('Failed asserting that the Email has header "not existing header".');

        (new EmailHasHeader('not existing header'))->evaluate(new Email($headers));
    }
}
