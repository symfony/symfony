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
use Symfony\Component\Serializer\Encoder\YamlEncoder;

/**
 * A helper providing autocompletion for available YamlEncoder options.
 *
 * Note that the "indentation" setting is not offered in this builder because
 * it can only be set during the construction of the YamlEncoder, but not per
 * call.
 *
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 */
final class YamlEncoderContextBuilder implements ContextBuilderInterface
{
    use ContextBuilderTrait;

    /**
     * Configures the threshold to switch to inline YAML.
     */
    public function withInlineThreshold(?int $inlineThreshold): static
    {
        return $this->with(YamlEncoder::YAML_INLINE, $inlineThreshold);
    }

    /**
     * Configures the indentation level.
     *
     * Must be positive.
     *
     * @param int<0, max>|null $indentLevel
     */
    public function withIndentLevel(?int $indentLevel): static
    {
        return $this->with(YamlEncoder::YAML_INDENT, $indentLevel);
    }

    /**
     * Configures \Symfony\Component\Yaml\Dumper::dump flags bitmask.
     *
     * @see \Symfony\Component\Yaml\Yaml
     */
    public function withFlags(?int $flags): static
    {
        return $this->with(YamlEncoder::YAML_FLAGS, $flags);
    }

    /**
     * Configures whether to perserve empty objects "{}" or to convert them to null.
     */
    public function withPreservedEmptyObjects(?bool $preserveEmptyObjects): static
    {
        return $this->with(YamlEncoder::PRESERVE_EMPTY_OBJECTS, $preserveEmptyObjects);
    }
}
