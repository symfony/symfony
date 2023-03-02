<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests\Context\Encoder;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Context\Encoder\XmlEncoderContextBuilder;
use Symfony\Component\Serializer\Encoder\XmlEncoder;

/**
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 */
class XmlEncoderContextBuilderTest extends TestCase
{
    private XmlEncoderContextBuilder $contextBuilder;

    protected function setUp(): void
    {
        $this->contextBuilder = new XmlEncoderContextBuilder();
    }

    /**
     * @dataProvider withersDataProvider
     *
     * @param array<string, mixed> $values
     */
    public function testWithers(array $values)
    {
        $context = $this->contextBuilder
            ->withAsCollection($values[XmlEncoder::AS_COLLECTION])
            ->withDecoderIgnoredNodeTypes($values[XmlEncoder::DECODER_IGNORED_NODE_TYPES])
            ->withEncoderIgnoredNodeTypes($values[XmlEncoder::ENCODER_IGNORED_NODE_TYPES])
            ->withEncoding($values[XmlEncoder::ENCODING])
            ->withFormatOutput($values[XmlEncoder::FORMAT_OUTPUT])
            ->withLoadOptions($values[XmlEncoder::LOAD_OPTIONS])
            ->withRemoveEmptyTags($values[XmlEncoder::REMOVE_EMPTY_TAGS])
            ->withRootNodeName($values[XmlEncoder::ROOT_NODE_NAME])
            ->withStandalone($values[XmlEncoder::STANDALONE])
            ->withTypeCastAttributes($values[XmlEncoder::TYPE_CAST_ATTRIBUTES])
            ->withVersion($values[XmlEncoder::VERSION])
            ->toArray();

        $this->assertSame($values, $context);
    }

    /**
     * @return iterable<array{0: array<string, mixed>|}>
     */
    public static function withersDataProvider(): iterable
    {
        yield 'With values' => [[
            XmlEncoder::AS_COLLECTION => true,
            XmlEncoder::DECODER_IGNORED_NODE_TYPES => [\XML_PI_NODE, \XML_COMMENT_NODE],
            XmlEncoder::ENCODER_IGNORED_NODE_TYPES => [\XML_TEXT_NODE],
            XmlEncoder::ENCODING => 'UTF-8',
            XmlEncoder::FORMAT_OUTPUT => false,
            XmlEncoder::LOAD_OPTIONS => \LIBXML_COMPACT,
            XmlEncoder::REMOVE_EMPTY_TAGS => true,
            XmlEncoder::ROOT_NODE_NAME => 'root',
            XmlEncoder::STANDALONE => false,
            XmlEncoder::TYPE_CAST_ATTRIBUTES => true,
            XmlEncoder::VERSION => '1.0',
        ]];

        yield 'With null values' => [[
            XmlEncoder::AS_COLLECTION => null,
            XmlEncoder::DECODER_IGNORED_NODE_TYPES => null,
            XmlEncoder::ENCODER_IGNORED_NODE_TYPES => null,
            XmlEncoder::ENCODING => null,
            XmlEncoder::FORMAT_OUTPUT => null,
            XmlEncoder::LOAD_OPTIONS => null,
            XmlEncoder::REMOVE_EMPTY_TAGS => null,
            XmlEncoder::ROOT_NODE_NAME => null,
            XmlEncoder::STANDALONE => null,
            XmlEncoder::TYPE_CAST_ATTRIBUTES => null,
            XmlEncoder::VERSION => null,
        ]];
    }
}
