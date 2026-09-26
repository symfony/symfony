<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpClient\Tests\Recorder\Matcher;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Recorder\Matcher\DefaultMatcher;
use Symfony\Component\HttpClient\Recorder\Redactor\DefaultRedactor;

class DefaultMatcherTest extends TestCase
{
    private DefaultMatcher $matcher;

    protected function setUp(): void
    {
        $this->matcher = new DefaultMatcher();
    }

    public function testMethodMismatch()
    {
        $harEntry = [
            'request' => [
                'method' => 'POST',
                'url' => 'https://example.com/test',
                'postData' => null,
            ],
            'response' => [],
        ];

        $this->assertFalse($this->matcher->matches($harEntry, 'GET', 'https://example.com/test', []));
    }

    public function testUrlMismatch()
    {
        $harEntry = [
            'request' => [
                'method' => 'GET',
                'url' => 'https://example.com/test',
                'postData' => null,
            ],
            'response' => [],
        ];

        $this->assertFalse($this->matcher->matches($harEntry, 'GET', 'https://example.com/other', []));
    }

    public function testGetWithEmptyBodyMatchesEntryWithoutPostData()
    {
        $harEntry = [
            'request' => [
                'method' => 'GET',
                'url' => 'https://example.com/test',
                'postData' => null,
            ],
            'response' => [],
        ];

        $this->assertTrue($this->matcher->matches($harEntry, 'GET', 'https://example.com/test', ['body' => '']));
    }

    public function testStringBodyMatchesBase64EncodedPostData()
    {
        $body = 'test body content';
        $harEntry = [
            'request' => [
                'method' => 'POST',
                'url' => 'https://example.com/test',
                'postData' => [
                    'text' => base64_encode($body),
                    'encoding' => 'base64',
                    'mimeType' => '',
                ],
            ],
            'response' => [],
        ];

        $this->assertTrue($this->matcher->matches($harEntry, 'POST', 'https://example.com/test', ['body' => $body]));
    }

    public function testStringBodyDoesNotMatchDifferentPostData()
    {
        $harEntry = [
            'request' => [
                'method' => 'POST',
                'url' => 'https://example.com/test',
                'postData' => [
                    'text' => 'different content',
                    'mimeType' => '',
                ],
            ],
            'response' => [],
        ];

        $this->assertFalse($this->matcher->matches($harEntry, 'POST', 'https://example.com/test', ['body' => 'test body content']));
    }

    public function testClosureBodyMatchesOnMethodAndUrlOnly()
    {
        $harEntry = [
            'request' => [
                'method' => 'POST',
                'url' => 'https://example.com/test',
                'postData' => null,
            ],
            'response' => [],
        ];

        $closure = static fn (): string => 'dynamic content';

        $this->assertTrue($this->matcher->matches($harEntry, 'POST', 'https://example.com/test', ['body' => $closure]));
    }

    public function testEmptyStringBodyMatchesEntryWithoutPostData()
    {
        $harEntry = [
            'request' => [
                'method' => 'POST',
                'url' => 'https://example.com/test',
                'postData' => null,
            ],
            'response' => [],
        ];

        // An empty string body should match an entry without postData since decodeContent([]) returns ''
        $this->assertTrue($this->matcher->matches($harEntry, 'POST', 'https://example.com/test', ['body' => '']));
    }

    public function testStringBodyMatchesPlainTextPostData()
    {
        $body = 'plain text body';
        $harEntry = [
            'request' => [
                'method' => 'POST',
                'url' => 'https://example.com/test',
                'postData' => [
                    'text' => $body,
                    'mimeType' => '',
                ],
            ],
            'response' => [],
        ];

        $this->assertTrue($this->matcher->matches($harEntry, 'POST', 'https://example.com/test', ['body' => $body]));
    }

    public function testRedactorIsAppliedToTheLiveRequest()
    {
        $entry = [
            'request' => [
                'method' => 'GET',
                'url' => 'https://example.com/x?token=%5BREDACTED%5D',
                'postData' => [
                    'text' => '{"password":"[REDACTED]"}',
                    'mimeType' => '',
                ],
            ],
            'response' => [],
        ];

        $matcher = new DefaultMatcher(new DefaultRedactor());

        $this->assertTrue($matcher->matches($entry, 'GET', 'https://example.com/x?token=secret', ['body' => '{"password":"hunter2"}']));

        $matcherWithoutRedactor = new DefaultMatcher();
        $this->assertFalse($matcherWithoutRedactor->matches($entry, 'GET', 'https://example.com/x?token=secret', ['body' => '{"password":"hunter2"}']));
    }
}
