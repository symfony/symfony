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

use Psr\Container\ContainerInterface;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;
use Symfony\Component\Serializer\Attribute\Context;
use Symfony\Component\Serializer\Exception\BadMethodCallException;
use Symfony\Component\Serializer\Exception\LogicException;

/**
 * Denormalizer that sanitizes string properties based on the Context attribute.
 *
 * @author Mohamed Senoussi <lesfootix@gmail.com>
 */
final class SanitizeDenormalizer implements DenormalizerInterface, DenormalizerAwareInterface
{
    use DenormalizerAwareTrait;

    public const SANITIZER_KEY = 'sanitizer';
    public const DEFAULT_SANITIZER = 'default';

    public function __construct(
        private ContainerInterface $sanitizers,
    ) {
    }

    public function getSupportedTypes(?string $format): array
    {
        return ['*' => false];
    }

    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): mixed
    {
        if (!isset($this->denormalizer)) {
            throw new BadMethodCallException('Please set a denormalizer before calling denormalize()!');
        }

        if (!\is_array($data)) {
            return $this->denormalizer->denormalize($data, $type, $format, $context);
        }

        $reflection = new \ReflectionClass($type);
        $sanitizedData = $data;

        foreach ($reflection->getProperties() as $property) {
            $name = $property->getName();
            if (!isset($data[$name])) {
                continue;
            }

            foreach ($property->getAttributes(Context::class) as $attribute) {
                $attributeContext = $attribute->newInstance();
                if (!isset($attributeContext->denormalizationContext[self::SANITIZER_KEY])) {
                    continue;
                }
                $sanitizer = $this->getSanitizer($attributeContext->denormalizationContext[self::SANITIZER_KEY]);
                $sanitizedData[$name] = $this->sanitizeValue($data[$name], $sanitizer, $name);
                $context['sanitized'] = true;
            }
        }

        return $this->denormalizer->denormalize($sanitizedData, $type, $format, $context);
    }

    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        if (!isset($this->denormalizer)) {
            return false;
        }

        if (!class_exists($type)) {
            return false;
        }

        if(isset($context['sanitized'])) {
            return false;
        }

        $reflection = new \ReflectionClass($type);
        foreach ($reflection->getProperties() as $property) {
            foreach ($property->getAttributes(Context::class) as $attribute) {
                $attrContext = $attribute->newInstance();
                if (isset($attrContext->denormalizationContext[self::SANITIZER_KEY])) {
                    return true;
                }
            }
        }

        return false;
    }

    private function sanitizeValue(mixed $value, HtmlSanitizerInterface $sanitizer, string $propertyName): mixed
    {
        return match (true) {
            \is_string($value) => $sanitizer->sanitize($value),
            \is_array($value) => array_map(static function ($v) use ($sanitizer, $propertyName) {
                if (!is_string($v)) {
                    throw new LogicException(\sprintf('Cannot sanitize property "%s". Only string items are supported.', $propertyName));
                }
                return $sanitizer->sanitize($v);
            }, $value),
            default => throw new LogicException(\sprintf('Cannot sanitize property "%s". Only string or array of strings are supported.', $propertyName)),
        };
    }

    private function getSanitizer(string $name): HtmlSanitizerInterface
    {
        if (!$this->sanitizers->has($name)) {
            throw new LogicException(\sprintf('Sanitizer "%s" is not defined in the container.', $name));
        }

        $sanitizer = $this->sanitizers->get($name);
        if (!$sanitizer instanceof HtmlSanitizerInterface) {
            throw new LogicException(\sprintf('Sanitizer "%s" must implement HtmlSanitizerInterface.', $name));
        }

        return $sanitizer;
    }
}
