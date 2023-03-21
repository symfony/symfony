<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Context\Encoder;

use Symfony\Component\Serializer\Context\ContextBuilderInterface;
use Symfony\Component\Serializer\Context\ContextBuilderTrait;
use Symfony\Component\Serializer\Encoder\XmlEncoder;

/**
 * A helper providing autocompletion for available XmlEncoder options.
 *
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 */
final class XmlEncoderContextBuilder implements ContextBuilderInterface
{
    use ContextBuilderTrait;

    /**
     * Configures whether the decoded result should be considered as a collection
     * or as a single element.
     */
    public function withAsCollection(?bool $asCollection): static
    {
        return $this->with(XmlEncoder::AS_COLLECTION, $asCollection);
    }

    /**
     * Configures node types to ignore while decoding.
     *
     * @see https://www.php.net/manual/en/dom.constants.php
     *
     * @param list<int>|null $decoderIgnoredNodeTypes
     */
    public function withDecoderIgnoredNodeTypes(?array $decoderIgnoredNodeTypes): static
    {
        return $this->with(XmlEncoder::DECODER_IGNORED_NODE_TYPES, $decoderIgnoredNodeTypes);
    }

    /**
     * Configures node types to ignore while encoding.
     *
     * @see https://www.php.net/manual/en/dom.constants.php
     *
     * @param list<int>|null $encoderIgnoredNodeTypes
     */
    public function withEncoderIgnoredNodeTypes(?array $encoderIgnoredNodeTypes): static
    {
        return $this->with(XmlEncoder::ENCODER_IGNORED_NODE_TYPES, $encoderIgnoredNodeTypes);
    }

    /**
     * Configures the DOMDocument encoding.
     *
     * @see https://www.php.net/manual/en/class.domdocument.php#domdocument.props.encoding
     */
    public function withEncoding(?string $encoding): static
    {
        return $this->with(XmlEncoder::ENCODING, $encoding);
    }

    /**
     * Configures whether to encode with indentation and extra space.
     *
     * @see https://php.net/manual/en/class.domdocument.php#domdocument.props.formatoutput
     */
    public function withFormatOutput(?bool $formatOutput): static
    {
        return $this->with(XmlEncoder::FORMAT_OUTPUT, $formatOutput);
    }

    /**
     * Configures the DOMDocument::loadXml options bitmask.
     *
     * @see https://www.php.net/manual/en/libxml.constants.php
     *
     * @param positive-int|null $loadOptions
     */
    public function withLoadOptions(?int $loadOptions): static
    {
        return $this->with(XmlEncoder::LOAD_OPTIONS, $loadOptions);
    }

    /**
     * Configures the DOMDocument::saveXml options bitmask.
     *
     * @see https://www.php.net/manual/en/libxml.constants.php
     *
     * @param positive-int|null $saveOptions
     */
    public function withSaveOptions(?int $saveOptions): static
    {
        return $this->with(XmlEncoder::SAVE_OPTIONS, $saveOptions);
    }

    /**
     * Configures whether to keep empty nodes.
     */
    public function withRemoveEmptyTags(?bool $removeEmptyTags): static
    {
        return $this->with(XmlEncoder::REMOVE_EMPTY_TAGS, $removeEmptyTags);
    }

    /**
     * Configures name of the root node.
     */
    public function withRootNodeName(?string $rootNodeName): static
    {
        return $this->with(XmlEncoder::ROOT_NODE_NAME, $rootNodeName);
    }

    /**
     * Configures whether the document will be standalone.
     *
     * @see https://php.net/manual/en/class.domdocument.php#domdocument.props.xmlstandalone
     */
    public function withStandalone(?bool $standalone): static
    {
        return $this->with(XmlEncoder::STANDALONE, $standalone);
    }

    /**
     * Configures whether casting numeric string attributes to integers or floats.
     */
    public function withTypeCastAttributes(?bool $typeCastAttributes): static
    {
        return $this->with(XmlEncoder::TYPE_CAST_ATTRIBUTES, $typeCastAttributes);
    }

    /**
     * Configures the version number of the document.
     *
     * @see https://php.net/manual/en/class.domdocument.php#domdocument.props.xmlversion
     */
    public function withVersion(?string $version): static
    {
        return $this->with(XmlEncoder::VERSION, $version);
    }
}
