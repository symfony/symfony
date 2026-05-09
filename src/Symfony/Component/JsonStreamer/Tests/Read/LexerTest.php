<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonStreamer\Tests\Read;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\JsonStreamer\Exception\InvalidStreamException;
use Symfony\Component\JsonStreamer\Read\Lexer;

class LexerTest extends TestCase
{
    private const RFC8259_TEST_SUITE_PATH = '/vendor/nst/json-test-suite';

    public function testTokens()
    {
        $this->assertTokens([['1', 0]], '1');
        $this->assertTokens([['false', 0]], 'false');
        $this->assertTokens([['null', 0]], 'null');
        $this->assertTokens([['"string"', 0]], '"string"');
        $this->assertTokens([['[', 0], [']', 1]], '[]');
        $this->assertTokens([['[', 0], ['10', 2], [',', 4], ['20', 6], [']', 9]], '[ 10, 20 ]');
        $this->assertTokens([['[', 0], ['1', 1], [',', 2], ['[', 4], ['2', 5], [']', 6], [']', 8]], '[1, [2] ]');
        $this->assertTokens([['{', 0], ['}', 1]], '{}');
        $this->assertTokens([['{', 0], ['"foo"', 1], [':', 6], ['{', 8], ['"bar"', 9], [':', 14], ['"baz"', 15], ['}', 20], ['}', 21]], '{"foo": {"bar":"baz"}}');
    }

    public function testTokensSubset()
    {
        $this->assertTokens([['false', 7]], '[1, 2, false]', 7, 5);
    }

    public function testTokenizeOverflowingBuffer()
    {
        $veryLongString = \sprintf('"%s"', str_repeat('.', 20000));

        $this->assertTokens([[$veryLongString, 0]], $veryLongString);
    }

    #[DataProvider('rfc8259ComplianceProvider')]
    public function testRfc8259Compliance(string $name, string $json, bool $valid)
    {
        $resource = fopen('php://temp', 'w');
        fwrite($resource, $json);
        rewind($resource);

        try {
            iterator_to_array((new Lexer())->getTokens($resource, 0, null));
            fclose($resource);

            if (!$valid) {
                $this->fail(\sprintf('"%s" should not be parseable.', $name));
            }

            $this->addToAssertionCount(1);
        } catch (InvalidStreamException) {
            fclose($resource);

            if ($valid) {
                $this->fail(\sprintf('"%s" should be parseable.', $name));
            }

            $this->addToAssertionCount(1);
        }
    }

    /**
     * @return iterable<array{0: string, 1: string, 2: bool}>
     */
    public static function rfc8259ComplianceProvider(): iterable
    {
        $testSuitePath = self::getRfc8259TestSuitePath();
        if (null === $testSuitePath) {
            yield 'json-test-suite-missing' => ['JSON Test Suite Missing', '{}', true];

            return;
        }

        $skip = [];

        // Contrary to what https://datatracker.ietf.org/doc/html/rfc8259 says,
        // duplicate keys must result in error, see https://github.com/golang/go/discussions/63397.
        // Therefore "object_duplicated_key" and "object_duplicated_key_and_value" are considered
        // as invalid.
        yield 'object_duplicated_key' => ['object_duplicated_key', '{"a":"b","a":"c"}', false];
        $skip['y_object_duplicated_key.json'] = true;

        yield 'object_duplicated_key_and_value' => ['object_duplicated_key_and_value', '{"a":"b","a":"b"}', false];
        $skip['y_object_duplicated_key_and_value.json'] = true;

        foreach (glob($testSuitePath.'/test_parsing/*.json') as $file) {
            $filename = basename($file);
            if (isset($skip[$filename]) || str_starts_with($filename, 'i_')) {
                continue;
            }

            $name = substr($filename, 2, -5);
            $valid = str_starts_with($filename, 'y_');

            yield $name => [$name, file_get_contents($file), $valid];
        }
    }

    private static function getRfc8259TestSuitePath(): ?string
    {
        $monorepoPath = \dirname(__DIR__, 6).self::RFC8259_TEST_SUITE_PATH;
        $standalonePath = \dirname(__DIR__, 2).self::RFC8259_TEST_SUITE_PATH;

        if (file_exists($monorepoPath)) {
            return $monorepoPath;
        }

        return file_exists($standalonePath) ? $standalonePath : null;
    }

    private function assertTokens(array $tokens, string $content, int $offset = 0, ?int $length = null): void
    {
        $resource = fopen('php://temp', 'w');
        fwrite($resource, $content);
        rewind($resource);

        $this->assertSame($tokens, iterator_to_array((new Lexer())->getTokens($resource, $offset, $length)));
    }
}
