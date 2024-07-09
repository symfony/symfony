<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Encoder;

use Symfony\Component\Serializer\Exception\RuntimeException;
use Symfony\Component\Yaml\Dumper;
use Symfony\Component\Yaml\Parser;
use Symfony\Component\Yaml\Yaml;

/**
 * Encodes YAML data.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class YamlEncoder implements EncoderInterface, DecoderInterface
{
    public const FORMAT = 'yaml';
    private const ALTERNATIVE_FORMAT = 'yml';

    public const PRESERVE_EMPTY_OBJECTS = 'preserve_empty_objects';

    /**
     * Override the amount of spaces to use for indentation of nested nodes.
     *
     * This option only works in the constructor, not in calls to `encode`.
     */
    public const YAML_INDENTATION = 'yaml_indentation';

    public const YAML_INLINE = 'yaml_inline';
    /**
     * Initial indentation for root element.
     */
    public const YAML_INDENT = 'yaml_indent';
    public const YAML_FLAGS = 'yaml_flags';

    private readonly Dumper $dumper;
    private readonly Parser $parser;
    private array $defaultContext = [
        self::YAML_INLINE => 0,
        self::YAML_INDENT => 0,
        self::YAML_FLAGS => 0,
    ];

    public function __construct(?Dumper $dumper = null, ?Parser $parser = null, array $defaultContext = [])
    {
        if (!class_exists(Dumper::class)) {
            throw new RuntimeException('The YamlEncoder class requires the "Yaml" component. Try running "composer require symfony/yaml".');
        }

        if (!$dumper) {
            $dumper = \array_key_exists(self::YAML_INDENTATION, $defaultContext) ? new Dumper($defaultContext[self::YAML_INDENTATION]) : new Dumper();
        }
        $this->dumper = $dumper;
        $this->parser = $parser ?? new Parser();
        unset($defaultContext[self::YAML_INDENTATION]);
        $this->defaultContext = array_merge($this->defaultContext, $defaultContext);
    }

    public function encode(mixed $data, string $format, array $context = []): string
    {
        $context = array_merge($this->defaultContext, $context);

        if ($context[self::PRESERVE_EMPTY_OBJECTS] ?? false) {
            $context[self::YAML_FLAGS] |= Yaml::DUMP_OBJECT_AS_MAP;
        }

        return $this->dumper->dump($data, $context[self::YAML_INLINE], $context[self::YAML_INDENT], $context[self::YAML_FLAGS]);
    }

    public function supportsEncoding(string $format): bool
    {
        return self::FORMAT === $format || self::ALTERNATIVE_FORMAT === $format;
    }

    public function decode(string $data, string $format, array $context = []): mixed
    {
        $context = array_merge($this->defaultContext, $context);

        return $this->parser->parse($data, $context[self::YAML_FLAGS]);
    }

    public function supportsDecoding(string $format): bool
    {
        return self::FORMAT === $format || self::ALTERNATIVE_FORMAT === $format;
    }
}
