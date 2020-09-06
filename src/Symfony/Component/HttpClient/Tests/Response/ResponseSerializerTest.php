<?php

/*
 *  This file is part of the Symfony package.
 *
 *  (c) Fabien Potencier <fabien@symfony.com>
 *
 *  For the full copyright and license information, please view the LICENSE
 *  file that was distributed with this source code.
 */

namespace Symfony\Component\HttpClient\Tests\Response;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Response\ResponseSerializer;
use Symfony\Contracts\HttpClient\ResponseInterface;

class ResponseSerializerTest extends TestCase
{
    /**
     * @var ResponseSerializer
     */
    private $serializer;

    protected function setUp(): void
    {
        $this->serializer = new ResponseSerializer();
    }

    public function testSerialize()
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('getHeaders')->willReturn([
            'Content-Type' => ['application/json'],
            'Cache-Control' => ['no-cache', 'private'],
        ]);
        $response->method('getContent')->willReturn('{"foo": true}');

        $expected = <<<'EOL'
200

content-type: application/json
cache-control: no-cache, private

{"foo": true}
EOL;

        $this->assertSame($expected, $this->serializer->serialize($response));
    }

    public function testDeserialize()
    {
        $content = <<<'EOL'
200

content-type: application/json
cache-control: no-cache, private
x-robots-tag: noindex

{"foo": true}
EOL;
        $this->assertSame(
            [
                200,
                [
                    'content-type' => ['application/json'],
                    'cache-control' => ['no-cache', 'private'],
                    'x-robots-tag' => ['noindex'],
                ],
                '{"foo": true}',
            ],
            $this->serializer->deserialize($content)
        );
    }
}
