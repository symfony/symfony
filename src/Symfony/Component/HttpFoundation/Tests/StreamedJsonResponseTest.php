<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\StreamedJsonResponse;

class StreamedJsonResponseTest extends TestCase
{
    public function testResponseSimpleList()
    {
        $content = $this->createSendResponse(
            [
                '_embedded' => [
                    'articles' => $this->generatorSimple('Article'),
                    'news' => $this->generatorSimple('News'),
                ],
            ],
        );

        $this->assertSame('{"_embedded":{"articles":["Article 1","Article 2","Article 3"],"news":["News 1","News 2","News 3"]}}', $content);
    }

    public function testResponseSimpleGenerator()
    {
        $content = $this->createSendResponse($this->generatorSimple('Article'));

        $this->assertSame('["Article 1","Article 2","Article 3"]', $content);
    }

    public function testResponseNestedGenerator()
    {
        $content = $this->createSendResponse((function (): iterable {
            yield 'articles' => $this->generatorSimple('Article');
            yield 'news' => $this->generatorSimple('News');
        })());

        $this->assertSame('{"articles":["Article 1","Article 2","Article 3"],"news":["News 1","News 2","News 3"]}', $content);
    }

    public function testResponseEmptyList()
    {
        $content = $this->createSendResponse(
            [
                '_embedded' => [
                    'articles' => $this->generatorSimple('Article', 0),
                ],
            ],
        );

        $this->assertSame('{"_embedded":{"articles":[]}}', $content);
    }

    public function testResponseObjectsList()
    {
        $content = $this->createSendResponse(
            [
                '_embedded' => [
                    'articles' => $this->generatorArray('Article'),
                ],
            ],
        );

        $this->assertSame('{"_embedded":{"articles":[{"title":"Article 1"},{"title":"Article 2"},{"title":"Article 3"}]}}', $content);
    }

    public function testResponseWithoutGenerator()
    {
        // while it is not the intended usage, all kind of iterables should be supported for good DX
        $content = $this->createSendResponse(
            [
                '_embedded' => [
                    'articles' => ['Article 1', 'Article 2', 'Article 3'],
                ],
            ],
        );

        $this->assertSame('{"_embedded":{"articles":["Article 1","Article 2","Article 3"]}}', $content);
    }

    public function testResponseWithPlaceholder()
    {
        // the placeholder must not conflict with generator injection
        $content = $this->createSendResponse(
            [
                '_embedded' => [
                    'articles' => $this->generatorArray('Article'),
                    'placeholder' => '__symfony_json__',
                    'news' => $this->generatorSimple('News'),
                ],
                'placeholder' => '__symfony_json__',
            ],
        );

        $this->assertSame('{"_embedded":{"articles":[{"title":"Article 1"},{"title":"Article 2"},{"title":"Article 3"}],"placeholder":"__symfony_json__","news":["News 1","News 2","News 3"]},"placeholder":"__symfony_json__"}', $content);
    }

    public function testResponseWithMixedKeyType()
    {
        $content = $this->createSendResponse(
            [
                '_embedded' => [
                    'list' => (function (): \Generator {
                        yield 0 => 'test';
                        yield 'key' => 'value';
                    })(),
                    'map' => (function (): \Generator {
                        yield 'key' => 'value';
                        yield 0 => 'test';
                    })(),
                    'integer' => (function (): \Generator {
                        yield 1 => 'one';
                        yield 3 => 'three';
                    })(),
                ],
            ]
        );

        $this->assertSame('{"_embedded":{"list":["test","value"],"map":{"key":"value","0":"test"},"integer":{"1":"one","3":"three"}}}', $content);
    }

    public function testResponseOtherTraversable()
    {
        $arrayObject = new \ArrayObject(['__symfony_json__' => '__symfony_json__']);

        $iteratorAggregate = new class implements \IteratorAggregate {
            public function getIterator(): \Traversable
            {
                return new \ArrayIterator(['__symfony_json__']);
            }
        };

        $jsonSerializable = new class implements \IteratorAggregate, \JsonSerializable {
            public function getIterator(): \Traversable
            {
                return new \ArrayIterator(['This should be ignored']);
            }

            public function jsonSerialize(): mixed
            {
                return ['__symfony_json__' => '__symfony_json__'];
            }
        };

        // while Generators should be used for performance reasons, the object should also work with any Traversable
        // to make things easier for a developer
        $content = $this->createSendResponse(
            [
                'arrayObject' => $arrayObject,
                'iteratorAggregate' => $iteratorAggregate,
                'jsonSerializable' => $jsonSerializable,
                // add a Generator to make sure it still work in combination with other Traversable objects
                'articles' => $this->generatorArray('Article'),
            ],
        );

        $this->assertSame('{"arrayObject":{"__symfony_json__":"__symfony_json__"},"iteratorAggregate":["__symfony_json__"],"jsonSerializable":{"__symfony_json__":"__symfony_json__"},"articles":[{"title":"Article 1"},{"title":"Article 2"},{"title":"Article 3"}]}', $content);
    }

    public function testPlaceholderAsKeyAndValueInStructure()
    {
        $content = $this->createSendResponse(
            [
                '__symfony_json__' => '__symfony_json__',
                'articles' => $this->generatorArray('Article'),
            ],
        );

        $this->assertSame('{"__symfony_json__":"__symfony_json__","articles":[{"title":"Article 1"},{"title":"Article 2"},{"title":"Article 3"}]}', $content);
    }

    public function testResponseStatusCode()
    {
        $response = new StreamedJsonResponse([], 201);

        $this->assertSame(201, $response->getStatusCode());
    }

    public function testPlaceholderAsObjectStructure()
    {
        $object = new class {
            public $__symfony_json__ = 'foo';
            public $bar = '__symfony_json__';
        };

        $content = $this->createSendResponse(
            [
                'object' => $object,
                // add a Generator to make sure it still work in combination with other object holding placeholders
                'articles' => $this->generatorArray('Article'),
            ],
        );

        $this->assertSame('{"object":{"__symfony_json__":"foo","bar":"__symfony_json__"},"articles":[{"title":"Article 1"},{"title":"Article 2"},{"title":"Article 3"}]}', $content);
    }

    public function testResponseHeaders()
    {
        $response = new StreamedJsonResponse([], 200, ['X-Test' => 'Test']);

        $this->assertSame('Test', $response->headers->get('X-Test'));
    }

    public function testCustomContentType()
    {
        $response = new StreamedJsonResponse([], 200, ['Content-Type' => 'application/json+stream']);

        $this->assertSame('application/json+stream', $response->headers->get('Content-Type'));
    }

    public function testEncodingOptions()
    {
        $response = new StreamedJsonResponse([
            '_embedded' => [
                'count' => '2', // options are applied to the initial json encode
                'values' => (function (): \Generator {
                    yield 'with/unescaped/slash' => 'With/a/slash'; // options are applied to key and values
                    yield '3' => '3'; // numeric check for value, but not for the key
                })(),
            ],
        ], encodingOptions: \JSON_UNESCAPED_SLASHES | \JSON_NUMERIC_CHECK);

        ob_start();
        $response->send();
        $content = ob_get_clean();

        $this->assertSame('{"_embedded":{"count":2,"values":{"with/unescaped/slash":"With/a/slash","3":3}}}', $content);
    }

    /**
     * @param iterable<mixed> $data
     */
    private function createSendResponse(iterable $data): string
    {
        $response = new StreamedJsonResponse($data);

        ob_start();
        $response->send();

        return ob_get_clean();
    }

    /**
     * @return \Generator<int, string>
     */
    private function generatorSimple(string $test, int $length = 3): \Generator
    {
        for ($i = 1; $i <= $length; ++$i) {
            yield $test.' '.$i;
        }
    }

    /**
     * @return \Generator<int, array{title: string}>
     */
    private function generatorArray(string $test, int $length = 3): \Generator
    {
        for ($i = 1; $i <= $length; ++$i) {
            yield ['title' => $test.' '.$i];
        }
    }
}
