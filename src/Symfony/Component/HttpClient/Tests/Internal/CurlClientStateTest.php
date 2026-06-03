<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpClient\Tests\Internal;

use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Internal\CurlClientState;

#[RequiresPhpExtension('curl')]
class CurlClientStateTest extends TestCase
{
    public function testOriginKeyFormat()
    {
        self::assertSame('http://127.0.0.1:8057', CurlClientState::originKey('http', '127.0.0.1', 8057));
        self::assertSame('http://127.0.0.1:8057', CurlClientState::originKey('http:', '127.0.0.1', 8057));
        self::assertSame('https://example.com:443', CurlClientState::originKey('https', 'example.com', 443));
        self::assertSame('http://example.com:80', CurlClientState::originKey('http', 'example.com', 80));
        self::assertSame('http://example.com:80', CurlClientState::originKey('http', 'EXAMPLE.com', 80));
        self::assertSame('http://example.com:80', CurlClientState::originKey('HTTP', 'example.com', 80));
        self::assertSame('http://example.com:80', CurlClientState::originKey('http', 'example.com'));
        self::assertSame('https://example.com:443', CurlClientState::originKey('https', 'example.com'));
    }

    public function testResetClearsNtlmRequiresFreshConnection()
    {
        $state = new CurlClientState(6, 0);
        $state->ntlmRequiresFreshConnection['http://example.com:80'] = true;

        $state->reset();

        self::assertSame([], $state->ntlmRequiresFreshConnection);
    }
}
