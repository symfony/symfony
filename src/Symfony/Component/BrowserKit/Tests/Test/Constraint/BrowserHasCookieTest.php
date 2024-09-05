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
use Symfony\Component\BrowserKit\AbstractBrowser;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\BrowserKit\CookieJar;
use Symfony\Component\BrowserKit\Test\Constraint\BrowserHasCookie;

class BrowserHasCookieTest extends TestCase
{
    public function testConstraint()
    {
        $browser = $this->getBrowser();
        $constraint = new BrowserHasCookie('foo', '/path');
        $this->assertTrue($constraint->evaluate($browser, '', true));
        $constraint = new BrowserHasCookie('foo', '/path', 'example.com');
        $this->assertTrue($constraint->evaluate($browser, '', true));
        $constraint = new BrowserHasCookie('bar');
        $this->assertFalse($constraint->evaluate($browser, '', true));

        $this->expectException(ExpectationFailedException::class);
        $this->expectExceptionMessage('Failed asserting that the Browser has cookie "bar".');

        $constraint->evaluate($browser);
    }

    public function testConstraintWithWrongPath()
    {
        $browser = $this->getBrowser();
        $constraint = new BrowserHasCookie('foo', '/other');

        $this->expectException(ExpectationFailedException::class);
        $this->expectExceptionMessage('Failed asserting that the Browser has cookie "foo" with path "/other".');

        $constraint->evaluate($browser);
    }

    public function testConstraintWithWrongDomain()
    {
        $browser = $this->getBrowser();
        $constraint = new BrowserHasCookie('foo', '/path', 'example.org');

        $this->expectException(ExpectationFailedException::class);
        $this->expectExceptionMessage('Failed asserting that the Browser has cookie "foo" with path "/path" for domain "example.org".');

        $constraint->evaluate($browser);
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
