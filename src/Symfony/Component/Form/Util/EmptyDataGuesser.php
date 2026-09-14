<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Util;

use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyWriteInfo;

/**
 * Derives "empty_data" from the type of the property a field is mapped to, when it refuses null.
 *
 * Since "empty_data" is view data, the guess is expressed in view space and is
 * restricted to types that map a scalar to a single control.
 *
 * @internal
 */
final class EmptyDataGuesser
{
    private ReflectionExtractor $writeInfoExtractor;

    /** @var array<string, string|bool|null> */
    private array $guesses = [];

    public function __construct()
    {
        // the same extractor as PropertyAccessor, so that the guess follows what it will write to
        $this->writeInfoExtractor = new ReflectionExtractor(['set'], null, null, false);
    }

    /**
     * @return string|bool|null The value to use as "empty_data", or null when nothing can be guessed
     */
    public function guess(string $class, string $property): string|bool|null
    {
        // the guess does not depend on the form type, so a class and a property are enough to identify it
        $key = $class.'::'.$property;

        if (\array_key_exists($key, $this->guesses)) {
            return $this->guesses[$key];
        }

        $writeTargetType = $this->getWriteTargetType($class, $property);

        if (!$writeTargetType instanceof \ReflectionNamedType || $writeTargetType->allowsNull()) {
            return $this->guesses[$key] = null;
        }

        return $this->guesses[$key] = match ($writeTargetType->getName()) {
            'string' => '',
            'int', 'float' => '0',
            'bool' => false,
            default => null,
        };
    }

    /**
     * Resolves the type of the target that PropertyAccessor writes the mapped value to.
     *
     * Only a publicly writable mutator method or property is a write target. The type of an accessor
     * or of a constructor argument says nothing about what the value is written through.
     */
    private function getWriteTargetType(string $class, string $property): ?\ReflectionType
    {
        $writeInfo = $this->writeInfoExtractor->getWriteInfo($class, $property, [
            'enable_getter_setter_extraction' => true,
            'enable_constructor_extraction' => false,
            'enable_adder_remover_extraction' => false,
        ]);

        if (null === $writeInfo) {
            return null;
        }

        try {
            // PropertyWriteInfo names the target without describing it, so the target is reflected here
            $target = match ($writeInfo->getType()) {
                PropertyWriteInfo::TYPE_METHOD => (new \ReflectionMethod($class, $writeInfo->getName()))->getParameters()[0] ?? null,
                PropertyWriteInfo::TYPE_PROPERTY => new \ReflectionProperty($class, $writeInfo->getName()),
                default => null,
            };
        } catch (\ReflectionException) {
            return null;
        }

        // the visibility is carried by the write info only, the target does not tell whether it can be written to
        if (null === $target || PropertyWriteInfo::VISIBILITY_PUBLIC !== $writeInfo->getVisibility()) {
            return null;
        }

        if ($target instanceof \ReflectionProperty) {
            $target = $target->getHook(\PropertyHookType::Set)?->getParameters()[0] ?? $target;
        }

        return $target->getType();
    }
}
