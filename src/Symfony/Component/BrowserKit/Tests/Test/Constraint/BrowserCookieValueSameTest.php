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
use Symfony\Component\BrowserKit\Test\Constraint\BrowserCookieValueSame;

class BrowserCookieValueSameTest extends TestCase
{
    public function testConstraint()
    {
        $browser = $this->getBrowser();
        $constraint = new BrowserCookieValueSame('foo', 'bar', false, '/path');
        self::assertTrue($constraint->evaluate($browser, '', true));
        $constraint = new BrowserCookieValueSame('foo', 'bar', true, '/path');
        self::assertTrue($constraint->evaluate($browser, '', true));
        $constraint = new BrowserCookieValueSame('foo', 'babar', false, '/path');
        self::assertFalse($constraint->evaluate($browser, '', true));

        try {
            $constraint->evaluate($browser);
        } catch (ExpectationFailedException $e) {
            self::assertEquals("Failed asserting that the Browser has cookie \"foo\" with path \"/path\" with value \"babar\".\n", TestFailure::exceptionToString($e));

            return;
        }

        self::fail();
    }

    private function getBrowser(): AbstractBrowser
    {
        $browser = self::createMock(AbstractBrowser::class);
        $jar = new CookieJar();
        $jar->set(new Cookie('foo', 'bar', null, '/path', 'example.com'));
        $browser->expects(self::any())->method('getCookieJar')->willReturn($jar);

        return $browser;
    }
}
