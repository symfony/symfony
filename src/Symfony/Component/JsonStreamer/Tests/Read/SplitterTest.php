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
use Symfony\Component\JsonStreamer\Exception\UnexpectedValueException;
use Symfony\Component\JsonStreamer\Read\Splitter;

class SplitterTest extends TestCase
{
    public function testSplitNull()
    {
        $this->assertListBoundaries(null, 'null');
        $this->assertDictBoundaries(null, 'null');
    }

    public function testSplitList()
    {
        $this->assertListBoundaries([], '[]');
        $this->assertListBoundaries([[1, 3]], '[100]');
        $this->assertListBoundaries([[1, 3], [5, 3]], '[100,200]');
        $this->assertListBoundaries([[1, 1], [3, 5]], '[1,[2,3]]');
        $this->assertListBoundaries([[1, 1], [3, 7]], '[1,{"2":3}]');
    }

    public function testSplitDict()
    {
        $this->assertDictBoundaries([], '{}');
        $this->assertDictBoundaries(['k' => [5, 2]], '{"k":10}');
        $this->assertDictBoundaries(['k' => [5, 4]], '{"k":[10]}');
    }

    public function testSplitCollection()
    {
        foreach (['null' => null, '{}' => [], '[]' => [], '{"k":[10]}' => ['k' => [5, 4]], '[100,200]' => [[1, 3], [5, 3]], '[1,[2,3]]' => [[1, 1], [3, 5]]] as $content => $expectedBoundaries) {
            $resource = fopen('php://temp', 'w');
            fwrite($resource, $content);
            rewind($resource);

            $boundaries = Splitter::splitCollection($resource);

            $this->assertSame($expectedBoundaries, null !== $boundaries ? iterator_to_array($boundaries) : null);
        }
    }

    #[DataProvider('splitMatchesJsonDecodeDataProvider')]
    public function testSplitMatchesJsonDecode(string $json)
    {
        $expected = json_decode($json, true);
        $prefix = '  "prefix", ';

        $resource = fopen('php://temp', 'w');
        fwrite($resource, $prefix.$json.' , "suffix"');
        rewind($resource);

        $method = array_is_list($expected) && '[' === ltrim($json)[0] ? 'splitList' : 'splitDict';

        foreach ([$method, 'splitCollection'] as $method) {
            $boundaries = Splitter::$method($resource, \strlen($prefix), \strlen($json));

            $actual = [];
            foreach ($boundaries as $key => [$offset, $length]) {
                $actual[$key] = json_decode(stream_get_contents($resource, $length, $offset), true);
            }

            $this->assertSame($expected, $actual);
        }
    }

    public static function splitMatchesJsonDecodeDataProvider(): iterable
    {
        $long = str_repeat('a"b\\c{}[],: ', 10_000);

        yield 'list' => ['[1, -2.5e3, true, false, null, "x"]'];
        yield 'dict' => ['{"a": 1, "b": [1, {"c": "d"}], "e": {"f": []}}'];
        yield 'whitespaces' => [" \n\t[ \r\n 1 \n,\t{ \"a\" : [ ] } , \"b\" \n] \n"];
        yield 'escaped strings' => [json_encode(['a"b', 'c\\', '\\"', '{[,:]}', "\u{1F600}", "\n"])];
        yield 'escaped keys' => [json_encode(['a"b' => 1, 'c\\d' => 2, '{[,:]}' => 3, "é\n" => 4])];
        yield 'nested' => [json_encode([[[[['a' => [1, 2, ['b' => '}]']]]]]], ['c' => '"']])];
        yield 'long values' => [json_encode([$long, ['a' => $long], $long])];
        yield 'long keys' => [json_encode([$long => 1, $long.'2' => [$long]])];
        yield 'escape at chunk edge' => [json_encode([str_repeat('a', 65_531).'\\"', 'b'])];
    }

    #[DataProvider('listAndCollectionMethodsProvider')]
    public function testSplitWhenStreamIsConsumedBetweenChunks(string $method)
    {
        $json = '['.str_repeat('1, ', 40_000).'1]';
        $resource = fopen('php://temp', 'w');
        fwrite($resource, $json);

        $count = 0;
        foreach (Splitter::$method($resource, 0, \strlen($json)) as $boundary) {
            fseek($resource, 0, \SEEK_END);
            fread($resource, 1);
            ++$count;
        }

        $this->assertSame(40_001, $count);
    }

    #[DataProvider('listAndCollectionMethodsProvider')]
    public function testSplitDoesNotReadCollectionBeforeIteration(string $method)
    {
        $resource = fopen('php://temp', 'w+');
        fwrite($resource, '[1,2]');

        $boundaries = Splitter::$method($resource);

        rewind($resource);
        fwrite($resource, '[10,2]');

        $this->assertSame([[1, 2], [4, 1]], iterator_to_array($boundaries));
    }

    #[DataProvider('listAndCollectionMethodsProvider')]
    public function testSplitThrowOnRootChangedBeforeIteration(string $method)
    {
        $resource = fopen('php://temp', 'w+');
        fwrite($resource, '[1,2]');

        $boundaries = Splitter::$method($resource);

        rewind($resource);
        fwrite($resource, '{"a":1}');

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('JSON is not valid.');

        iterator_to_array($boundaries);
    }

    public static function listAndCollectionMethodsProvider(): iterable
    {
        yield ['splitList'];
        yield ['splitCollection'];
    }

    public function testSplitNonSeekableStream()
    {
        [$resource, $writer] = stream_socket_pair('\\' === \DIRECTORY_SEPARATOR ? \STREAM_PF_INET : \STREAM_PF_UNIX, \STREAM_SOCK_STREAM, \STREAM_IPPROTO_IP);
        fwrite($writer, '[1, {"a":2}]');
        fclose($writer);

        $this->assertSame([[1, 1], [4, 7]], iterator_to_array(Splitter::splitList($resource)));
    }

    #[DataProvider('nonSeekableCollectionDataProvider')]
    public function testSplitCollectionFromNonSeekableStream(string $json, ?array $expectedBoundaries)
    {
        [$resource, $writer] = stream_socket_pair('\\' === \DIRECTORY_SEPARATOR ? \STREAM_PF_INET : \STREAM_PF_UNIX, \STREAM_SOCK_STREAM, \STREAM_IPPROTO_IP);
        fwrite($writer, $json);
        fclose($writer);

        try {
            $boundaries = Splitter::splitCollection($resource);

            $this->assertSame($expectedBoundaries, null !== $boundaries ? iterator_to_array($boundaries) : null);
        } finally {
            fclose($resource);
        }
    }

    public static function nonSeekableCollectionDataProvider(): iterable
    {
        yield 'null' => ['null', null];
        yield 'empty list' => ['[]', []];
        yield 'empty dict' => ['{}', []];
        yield 'list' => ['[1, {"a":2}]', [[1, 1], [4, 7]]];
        yield 'dict' => ['{"k":[10]}', ['k' => [5, 4]]];
        yield 'whitespace' => [" \n[true,false]", [[3, 4], [8, 5]]];
    }

    #[DataProvider('invalidNonSeekableCollectionRootDataProvider')]
    public function testSplitCollectionRejectsInvalidNonSeekableRootBeforeIteration(string $json)
    {
        [$resource, $writer] = stream_socket_pair('\\' === \DIRECTORY_SEPARATOR ? \STREAM_PF_INET : \STREAM_PF_UNIX, \STREAM_SOCK_STREAM, \STREAM_IPPROTO_IP);
        fwrite($writer, $json);
        fclose($writer);

        $this->expectException(InvalidStreamException::class);

        try {
            Splitter::splitCollection($resource);
        } finally {
            fclose($resource);
        }
    }

    public static function invalidNonSeekableCollectionRootDataProvider(): iterable
    {
        yield 'boolean' => ['true'];
        yield 'number' => ['1'];
        yield 'string' => ['"x"'];
        yield 'null followed by scalar' => ['null true'];
        yield 'null followed by collection' => ['null {}'];
        yield 'null prefix' => ['nullx'];
    }

    public function testRejectsLengthBeyondEndOfStream()
    {
        $resource = fopen('php://temp', 'w');
        fwrite($resource, '[1, 2');

        $this->expectException(InvalidStreamException::class);
        $this->expectExceptionMessage('Unterminated JSON.');

        iterator_to_array(Splitter::splitList($resource, 0, 100));
    }

    #[DataProvider('maxDepthDataProvider')]
    public function testMaxDepth(string $json, bool $valid)
    {
        $resource = fopen('php://temp', 'w');
        fwrite($resource, $json);
        rewind($resource);

        if (!$valid) {
            $this->expectException(InvalidStreamException::class);
            $this->expectExceptionMessage('Maximum stack depth of 512 exceeded.');
        }

        iterator_to_array('[' === $json[0] ? Splitter::splitList($resource) : Splitter::splitDict($resource));

        $this->addToAssertionCount(1);
    }

    public static function maxDepthDataProvider(): iterable
    {
        yield 'list below max' => [str_repeat('[', 511).'1'.str_repeat(']', 511), true];
        yield 'list at max' => [str_repeat('[', 512).'1'.str_repeat(']', 512), false];
        yield 'dict at max' => [str_repeat('{"a":', 512).'1'.str_repeat('}', 512), false];
    }

    #[DataProvider('rfc8259ComplianceProvider')]
    public function testRfc8259Compliance(string $name, string $json, bool $valid)
    {
        $resource = fopen('php://temp', 'w');
        fwrite($resource, $json);
        rewind($resource);

        try {
            $boundaries = match (ltrim($json, " \t\n\r")[0] ?? '') {
                '[' => Splitter::splitList($resource),
                '{' => Splitter::splitDict($resource),
                default => [[0, \strlen($json)]],
            };

            foreach ($boundaries as [$offset, $length]) {
                json_decode(stream_get_contents($resource, $length, $offset), flags: \JSON_THROW_ON_ERROR);
            }

            $parsed = true;
        } catch (UnexpectedValueException|\JsonException) {
            $parsed = false;
        }

        $this->assertSame($valid, $parsed, \sprintf('"%s" should%s be parseable.', $name, $valid ? '' : ' not'));
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

        // Duplicate keys are rejected at the split level.
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

    public function testSplitDictDoesNotRetainKeys()
    {
        $countRetainedValues = static function (): int {
            $count = 0;

            foreach ((new \ReflectionClass(Splitter::class))->getStaticProperties() as $value) {
                if (\is_array($value)) {
                    array_walk_recursive($value, static function () use (&$count): void {
                        ++$count;
                    });
                }
            }

            return $count;
        };

        $splitUniqueKeys = static function (int $batch): void {
            for ($i = 0; $i < 100; ++$i) {
                $resource = fopen('php://temp', 'w');
                fwrite($resource, \sprintf('{"key_%d_%d":1}', $batch, $i));
                rewind($resource);

                iterator_to_array((new Splitter())->splitDict($resource));
            }
        };

        $splitUniqueKeys(1);
        $retainedAfterFirstBatch = $countRetainedValues();

        $splitUniqueKeys(2);

        $this->assertSame($retainedAfterFirstBatch, $countRetainedValues());
    }

    #[DataProvider('splitDictInvalidDataProvider')]
    public function testSplitDictInvalidThrowException(string $expectedMessage, string $content)
    {
        $this->expectException(InvalidStreamException::class);
        $this->expectExceptionMessage($expectedMessage);

        $resource = fopen('php://temp', 'w');
        fwrite($resource, $content);
        rewind($resource);

        iterator_to_array((new Splitter())->splitDict($resource));
    }

    /**
     * @return iterable<array{0: list<array{0: string, 1: string}>}>
     */
    public static function splitDictInvalidDataProvider(): iterable
    {
        yield ['Unterminated JSON.', '{"foo":1'];
        yield ['Unexpected "{" token.', '{{}'];
        yield ['Unexpected "}" token.', '}'];
        yield ['Unexpected "}" token.', '{}}'];
        yield ['Unexpected "," token.', ','];
        yield ['Unexpected "," token.', '{"foo",}'];
        yield ['Unexpected ":" token.', ':'];
        yield ['Unexpected ":" token.', '{:'];
        yield ['Unexpected "0" token.', '{"foo" 0}'];
        yield ['Unterminated JSON.', '{"foo":_'];
        yield ['Expected dict key, but got "100".', '{100'];
        yield ['Got "foo" dict key twice.', '{"foo":1,"foo"'];
        yield 'escaped duplicate' => ['Got "\u0061" dict key twice.', '{"a":1,"\u0061":2}'];
        yield ['Expected dict key, but got', "{\"\x01\":1}"];
        yield ['Expected dict key, but got', "{\"\xff\":1}"];
        yield ['Unexpected "}" token.', '{"a":[},"b":1}'];
        yield ['Expected end, but got ""x"".', '{"a": true} "x"'];
    }

    #[DataProvider('splitListInvalidDataProvider')]
    public function testSplitListInvalidThrowException(string $expectedMessage, string $content)
    {
        $this->expectException(InvalidStreamException::class);
        $this->expectExceptionMessage($expectedMessage);

        $resource = fopen('php://temp', 'w');
        fwrite($resource, $content);
        rewind($resource);

        iterator_to_array((new Splitter())->splitList($resource));
    }

    /**
     * @return iterable<array{0: string, 1: string}>
     */
    public static function splitListInvalidDataProvider(): iterable
    {
        yield ['Unterminated JSON.', '[100'];
        yield ['Unexpected "[" token.', '[]['];
        yield ['Unexpected "]" token.', ']'];
        yield ['Unexpected "]" token.', '[]]'];
        yield ['Unexpected "," token.', ','];
        yield ['Unexpected "," token.', '[100,,]'];
        yield ['Unexpected ":" token.', ':'];
        yield ['Unexpected ":" token.', '[100:'];
        yield ['Unexpected "}" token.', '[[1}]'];
        yield ['Unexpected ""b"" token.', '["a" "b"]'];
        yield ['Unterminated JSON.', '[_'];
        yield ['Expected end, but got "100".', '[true] 100'];
    }

    private static function getRfc8259TestSuitePath(): ?string
    {
        $monorepoPath = \dirname(__DIR__, 6).'/vendor/nst/json-test-suite';
        $standalonePath = \dirname(__DIR__, 2).'/vendor/nst/json-test-suite';

        if (file_exists($monorepoPath)) {
            return $monorepoPath;
        }

        return file_exists($standalonePath) ? $standalonePath : null;
    }

    private function assertListBoundaries(?array $expectedBoundaries, string $content): void
    {
        $resource = fopen('php://temp', 'w');
        fwrite($resource, $content);
        rewind($resource);

        $boundaries = (new Splitter())->splitList($resource);
        $boundaries = null !== $boundaries ? iterator_to_array($boundaries) : null;

        $this->assertSame($expectedBoundaries, $boundaries);

        $resource = fopen('php://temp', 'w');
        fwrite($resource, $content);
        rewind($resource);

        $boundaries = (new Splitter())->splitList($resource, 0, null);
        $boundaries = null !== $boundaries ? iterator_to_array($boundaries) : null;

        $this->assertSame($expectedBoundaries, $boundaries);
    }

    private function assertDictBoundaries(?array $expectedBoundaries, string $content): void
    {
        $resource = fopen('php://temp', 'w');
        fwrite($resource, $content);
        rewind($resource);

        $boundaries = (new Splitter())->splitDict($resource);
        $boundaries = null !== $boundaries ? iterator_to_array($boundaries) : null;

        $this->assertSame($expectedBoundaries, $boundaries);

        $resource = fopen('php://temp', 'w');
        fwrite($resource, $content);
        rewind($resource);

        $boundaries = (new Splitter())->splitDict($resource, 0, null);
        $boundaries = null !== $boundaries ? iterator_to_array($boundaries) : null;

        $this->assertSame($expectedBoundaries, $boundaries);
    }
}
