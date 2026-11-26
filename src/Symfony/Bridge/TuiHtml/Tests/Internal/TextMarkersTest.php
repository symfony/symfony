<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\TuiHtml\Tests\Internal;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\TuiHtml\Internal\TextMarkers;

class TextMarkersTest extends TestCase
{
    public function testUnscaledPayloadPreservesTheOriginalOnRegexFailure()
    {
        $payload = "\x1b]66;s=2;hello\x07";
        $limit = ini_set('pcre.backtrack_limit', '0');
        try {
            self::assertSame($payload, TextMarkers::unscaledPayload($payload));
        } finally {
            ini_set('pcre.backtrack_limit', $limit);
        }
    }

    public function testUnscaledPayloadKeepsTextAndStyleWithoutTheSizingEnvelope()
    {
        self::assertSame("\x1b[31mhello\x1b[0m", TextMarkers::unscaledPayload("\x1b[31m\x1b]66;s=2;hello\x07\x1b[0m"));
    }
}
