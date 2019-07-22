<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\BrowserKit\Tests\Test\Constraint;

use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\TestFailure;
use Symfony\Component\BrowserKit\AbstractBrowser;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\BrowserKit\CookieJar;
use Symfony\Component\BrowserKit\Test\Constraint\BrowserHasCookie;

class BrowserHasCookieTest extends TestCase
{
    public function testConstraint(): void
    {
        $browser = $this->getBrowser();
        $constraint = new BrowserHasCookie('foo', '/path');
        $this->assertTrue($constraint->evaluate($browser, '', true));
        $constraint = new BrowserHasCookie('foo', '/path', 'example.com');
        $this->assertTrue($constraint->evaluate($browser, '', true));
        $constraint = new BrowserHasCookie('bar');
        $this->assertFalse($constraint->evaluate($browser, '', true));

        try {
            $constraint->evaluate($browser);
        } catch (ExpectationFailedException $e) {
            $this->assertEquals("Failed asserting that the Browser has cookie \"bar\".\n", TestFailure::exceptionToString($e));

            return;
        }

        $this->fail();
    }

    public function testConstraintWithWrongPath(): void
    {
        $browser = $this->getBrowser();
        $constraint = new BrowserHasCookie('foo', '/other');
        try {
            $constraint->evaluate($browser);
        } catch (ExpectationFailedException $e) {
            $this->assertEquals("Failed asserting that the Browser has cookie \"foo\" with path \"/other\".\n", TestFailure::exceptionToString($e));

            return;
        }

        $this->fail();
    }

    public function testConstraintWithWrongDomain(): void
    {
        $browser = $this->getBrowser();
        $constraint = new BrowserHasCookie('foo', '/path', 'example.org');
        try {
            $constraint->evaluate($browser);
        } catch (ExpectationFailedException $e) {
            $this->assertEquals("Failed asserting that the Browser has cookie \"foo\" with path \"/path\" for domain \"example.org\".\n", TestFailure::exceptionToString($e));

            return;
        }

        $this->fail();
    }

    private function getBrowser(): AbstractBrowser
    {
        $browser = $this->createMock(AbstractBrowser::class);
        $jar = new CookieJar();
        $jar->set(new Cookie('foo', 'bar', null, '/path', 'example.com'));
        $browser->expects($this->any())->method('getCookieJar')->willReturn($jar);

        return $browser;
    }
}
