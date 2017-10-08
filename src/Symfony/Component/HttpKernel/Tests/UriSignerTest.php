<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\UriSigner;

class UriSignerTest extends TestCase
{
    public function testSign()
    {
        $signer = new UriSigner('foobar');

        $this->assertContains('?_hash=', $signer->sign('http://example.com/foo'));
        $this->assertContains('&_hash=', $signer->sign('http://example.com/foo?foo=bar'));
    }

    public function testCheck()
    {
        $signer = new UriSigner('foobar');

        $this->assertFalse($signer->check('http://example.com/foo?_hash=foo'));
        $this->assertFalse($signer->check('http://example.com/foo?foo=bar&_hash=foo'));
        $this->assertFalse($signer->check('http://example.com/foo?foo=bar&_hash=foo&bar=foo'));

        $this->assertTrue($signer->check($signer->sign('http://example.com/foo')));
        $this->assertTrue($signer->check($signer->sign('http://example.com/foo?foo=bar')));
        $this->assertTrue($signer->check($signer->sign('http://example.com/foo?foo=bar&0=integer')));

        $this->assertTrue($signer->sign('http://example.com/foo?foo=bar&bar=foo') === $signer->sign('http://example.com/foo?bar=foo&foo=bar'));
    }

    public function testCheckWithDifferentArgSeparator()
    {
        $this->iniSet('arg_separator.output', '&amp;');
        $signer = new UriSigner('foobar');

        $this->assertSame(
            'http://example.com/foo?baz=bay&foo=bar&_hash=rIOcC%2FF3DoEGo%2FvnESjSp7uU9zA9S%2F%2BOLhxgMexoPUM%3D',
            $signer->sign('http://example.com/foo?foo=bar&baz=bay')
        );
        $this->assertTrue($signer->check($signer->sign('http://example.com/foo?foo=bar&baz=bay')));
    }

    public function testCheckWithDifferentParameter()
    {
        $signer = new UriSigner('foobar', 'qux');

        $this->assertSame(
            'http://example.com/foo?baz=bay&foo=bar&qux=rIOcC%2FF3DoEGo%2FvnESjSp7uU9zA9S%2F%2BOLhxgMexoPUM%3D',
            $signer->sign('http://example.com/foo?foo=bar&baz=bay')
        );
        $this->assertTrue($signer->check($signer->sign('http://example.com/foo?foo=bar&baz=bay')));
    }
}
