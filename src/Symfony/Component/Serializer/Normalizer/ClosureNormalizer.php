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

use Symfony\Component\Serializer\Exception\InvalidArgumentException;
use Symfony\Component\Serializer\Exception\LogicException;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Serializer\Exception\UnexpectedValueException;

/**
 * Normalizes a {@see \Closure} to a pure array, using the DeepClone extension.
 *
 * Only closures with an addressable declaration are supported:
 *  - as of PHP 8.5, static closures in constant expressions, encoded as a
 *    reference to their declaration site
 *  - first-class callables like `strlen(...)`, encoded by name, which need
 *    ALLOW_NAMED_CALLABLES on both ends
 */
final class ClosureNormalizer implements NormalizerInterface, DenormalizerInterface
{
    /**
     * Classes that may be instantiated, matched case insensitively. null (default)
     * allows all, [] allows none. \Closure::class is gated by this list too, so any
     * non-null value must include it. Alone, it allows function and static-method
     * targets and refuses a closure bound to an object, which is encoded with its
     * whole graph. A declaration-site reference also needs its declaring class.
     */
    public const ALLOWED_CLASSES = 'allowed_classes';

    /**
     * Whether closures over named callables may be encoded and resolved. Enable
     * only between ends that trust each other.
     */
    public const ALLOW_NAMED_CALLABLES = 'allow_named_callables';

    private const DEFAULT_CONTEXT = [
        self::ALLOWED_CLASSES => null,
        self::ALLOW_NAMED_CALLABLES => false,
    ];

    public function __construct(
        private array $defaultContext = self::DEFAULT_CONTEXT,
    ) {
        if (!\function_exists('deepclone_to_array')) {
            throw new LogicException('The ClosureNormalizer class requires the "deepclone" extension or its polyfill. Try running "composer require symfony/polyfill-deepclone".');
        }

        $this->defaultContext += self::DEFAULT_CONTEXT;
    }

    /**
     * @return array<string, bool|null>
     */
    public function getSupportedTypes(?string $format): array
    {
        return [
            \Closure::class => true,
        ];
    }

    /**
     * @throws InvalidArgumentException
     * @throws UnexpectedValueException
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        if (!$data instanceof \Closure) {
            throw new InvalidArgumentException('The object must be an instance of "\Closure".');
        }

        try {
            return deepclone_to_array($data, ...$this->options($context));
        } catch (\DeepClone\NotInstantiableException|\ValueError $e) {
            $r = new \ReflectionFunction($data);

            if (!$r->isAnonymous()) {
                throw new UnexpectedValueException($e->getMessage(), $e->getCode(), $e);
            }

            throw new UnexpectedValueException(\sprintf('Cannot normalize the closure declared in "%s" on line %d: only first-class callables and, as of PHP 8.5, static closures declared in constant expressions are supported.', $r->getFileName(), $r->getStartLine()), 0, $e);
        }
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof \Closure;
    }

    /**
     * @throws NotNormalizableValueException
     */
    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): \Closure
    {
        if (!\is_array($data)) {
            throw NotNormalizableValueException::createForUnexpectedDataType('The data is not an array, you should pass the array produced by the "'.self::class.'".', $data, ['array'], $context['deserialization_path'] ?? null, true);
        }

        [$allowedClasses, $allowNamedCallables] = $this->options($context);

        try {
            $closure = deepclone_from_array($data, $allowedClasses, $allowNamedCallables);
        } catch (\DeepClone\ClassNotFoundException|\DeepClone\NotInstantiableException|\ValueError $e) {
            throw NotNormalizableValueException::createForUnexpectedDataType($e->getMessage(), $data, ['array'], $context['deserialization_path'] ?? null, false, $e->getCode(), $e);
        }

        if (!$closure instanceof \Closure) {
            throw NotNormalizableValueException::createForUnexpectedDataType('The data does not describe a closure.', $closure, ['array'], $context['deserialization_path'] ?? null, true);
        }

        return $closure;
    }

    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        return \Closure::class === $type;
    }

    /**
     * @return array{0: list<class-string>|null, 1: bool}
     */
    private function options(array $context): array
    {
        return [
            $context[self::ALLOWED_CLASSES] ?? $this->defaultContext[self::ALLOWED_CLASSES],
            $context[self::ALLOW_NAMED_CALLABLES] ?? $this->defaultContext[self::ALLOW_NAMED_CALLABLES],
        ];
    }
}
