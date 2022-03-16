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

    public const YAML_INLINE = 'yaml_inline';
    public const YAML_INDENT = 'yaml_indent';
    public const YAML_FLAGS = 'yaml_flags';

    private $dumper;
    private $parser;
    private $defaultContext = [
        self::YAML_INLINE => 0,
        self::YAML_INDENT => 0,
        self::YAML_FLAGS => 0,
    ];

    public function __construct(Dumper $dumper = null, Parser $parser = null, array $defaultContext = [])
    {
        if (!class_exists(Dumper::class)) {
            throw new RuntimeException('The YamlEncoder class requires the "Yaml" component. Install "symfony/yaml" to use it.');
        }

        $this->dumper = $dumper ?? new Dumper();
        $this->parser = $parser ?? new Parser();
        $this->defaultContext = array_merge($this->defaultContext, $defaultContext);
    }

    /**
     * {@inheritdoc}
     */
    public function encode(mixed $data, string $format, array $context = []): string
    {
        $context = array_merge($this->defaultContext, $context);

        if ($context[self::PRESERVE_EMPTY_OBJECTS] ?? false) {
            $context[self::YAML_FLAGS] |= Yaml::DUMP_OBJECT_AS_MAP;
        }

        return $this->dumper->dump($data, $context[self::YAML_INLINE], $context[self::YAML_INDENT], $context[self::YAML_FLAGS]);
    }

    /**
     * {@inheritdoc}
     *
     * @param array $context
     */
    public function supportsEncoding(string $format /*, array $context = [] */): bool
    {
        return self::FORMAT === $format || self::ALTERNATIVE_FORMAT === $format;
    }

    /**
     * {@inheritdoc}
     */
    public function decode(string $data, string $format, array $context = []): mixed
    {
        $context = array_merge($this->defaultContext, $context);

        return $this->parser->parse($data, $context[self::YAML_FLAGS]);
    }

    /**
     * {@inheritdoc}
     *
     * @param array $context
     */
    public function supportsDecoding(string $format /*, array $context = [] */): bool
    {
        return self::FORMAT === $format || self::ALTERNATIVE_FORMAT === $format;
    }
}
