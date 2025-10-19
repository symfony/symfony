<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Normalizer;

use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;
use Symfony\Component\Serializer\Attribute\Sanitize;
use Symfony\Component\Serializer\Exception\BadMethodCallException;
use Symfony\Component\Serializer\Exception\InvalidArgumentException;
use Symfony\Component\Serializer\Exception\LogicException;

/**
 * Denormalizer that sanitizes string properties marked with the #[Sanitize] attribute.
 *
 * @author Mohamed Senoussi <lesfootix@gmail.com>
 */
final class SanitizeDenormalizer implements DenormalizerInterface
{
    use DenormalizerAwareTrait;

    public function __construct(
        private readonly HtmlSanitizerInterface $defaultSanitizer,
        private readonly array $sanitizers = [],
    ) {}

    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): mixed
    {
        if (!isset($this->denormalizer)) {
            throw new BadMethodCallException('Please set a denormalizer before calling denormalize()!');
        }

        if (!is_array($data) || !class_exists($type)) {
            return $data;
        }

        $reflectionClass = new \ReflectionClass($type);
        foreach ($reflectionClass->getProperties() as $property) {
            $attributes = $property->getAttributes(Sanitize::class);
            if ($attributes && array_key_exists($property->getName(), $data)) {
                /** @var Sanitize $sanitizeAttribute */
                $sanitizeAttribute = $attributes[0]->newInstance();
                $sanitizer = $this->getSanitizer($sanitizeAttribute->sanitizer);
                $data[$property->getName()] = $this->sanitizeValue($data[$property->getName()], $sanitizer, $property, $type);
            }
        }

        return $this->denormalizer->denormalize($data, $type, $format, $context);
    }

    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        if (!class_exists($type)) {
            return false;
        }
        $reflectionClass = new \ReflectionClass($type);
        foreach ($reflectionClass->getProperties() as $property) {
            if ($property->getAttributes(Sanitize::class)) {
                return true;
            }
        }

        return false;
    }

    public function getSupportedTypes(?string $format): array
    {
        return ['object' => true];
    }

    private function sanitizeValue(mixed $value, HtmlSanitizerInterface $sanitizer, \ReflectionProperty $property, string $className): string|array
    {
        return match (true) {
            is_string($value) && $property->getType()?->getName() === 'string' => $sanitizer->sanitize($value),
            is_array($value) && $property->getType()?->getName() === 'array' =>
            array_map(static function ($item) use ($sanitizer, $property, $className) {
                if (!is_string($item)) {
                    throw new LogicException(sprintf(
                        'The #[Sanitize] attribute can only be applied to array of string properties. Property $%s in class %s contains a non-string value.',
                        $property->getName(),
                        $className
                    ));
                }
                return $sanitizer->sanitize($item);
            }, $value),
            default => throw new LogicException(sprintf(
                'The #[Sanitize] attribute can only be applied to string or array of string properties. Property $%s in class %s is not supported.',
                $property->getName(),
                $className
            )),
        };
    }

    private function getSanitizer(?string $name): HtmlSanitizerInterface
    {
        if (null === $name) {
            return $this->defaultSanitizer;
        }

        if (!isset($this->sanitizers[$name])) {
            throw new InvalidArgumentException(sprintf('The HTML sanitizer "%s" does not exist. Available sanitizers are: %s', $name, implode(', ', array_keys($this->sanitizers))));
        }

        return $this->sanitizers[$name];
    }
}
