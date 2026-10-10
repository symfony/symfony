<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonSchema\DefinitionPolicy;

use Symfony\Component\JsonSchema\Configuration;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Names definitions after the class short name (or the configured prefix for the root definition),
 * suffixed by the groups, attributes and validation groups.
 *
 * A name is owned by the first class claiming it: another class asking for the same
 * name gets one built from more namespace segments instead.
 *
 * Within a class, a name is owned by the first shape (configuration) claiming it: a different shape
 * resolving to the same name gets a numbered suffix (".2", ".3", ...) in order of appearance.
 *
 * @experimental
 */
final class ShortNameDefinitionPolicy implements DefinitionPolicyInterface, ResetInterface
{
    private const GLUE = '.';

    /**
     * Definition name prefix => class that claimed it first, so two classes sharing a short name
     * (App\Input\Book, App\Output\Book) never end up under the same definition.
     *
     * Names are stable across the generate() calls sharing this instance, as they describe one document;
     * reset() starts a new one.
     *
     * @var array<string, class-string>
     */
    private array $owners = [];

    /**
     * Definition name => fingerprint of the shape that claimed it, so shapes that are readably named alike
     * (attributes ["first_name"] and ["first", "name"], ignored attributes, extra attributes, format)
     * get ".2", ".3" in order of appearance rather than silently sharing a definition.
     *
     * One document per instance: the same shape always gets the same name until reset() starts a new one.
     *
     * @var array<string, string>
     */
    private array $shapes = [];

    public function nameFor(string $class, Configuration $config, ?DefinitionParent $parent = null): string
    {
        $prefix = (null === $parent && null !== $config->definitionPrefix ? $this->claim($config->definitionPrefix, $class) : null) ?? $this->claimShortName($class);

        if (null !== $config->definitionName) {
            $name = '' === $config->definitionName ? $prefix : $prefix.'-'.$config->definitionName;
        } else {
            $parts = [];

            if ($config->groups) {
                $parts[] = implode('_', $config->groups);
            }

            if ($config->attributes) {
                $parts[] = self::attributesToString($config->attributes);
            }

            if (null !== $validationGroups = $config->getValidationGroupNames()) {
                $parts[] = 'validation'.self::GLUE.($validationGroups ? implode('_', $validationGroups) : 'none');
            }

            $name = $parts ? $prefix.'-'.implode('_', $parts) : $prefix;
        }

        return $this->claimShape(
            (string) preg_replace('/[^a-zA-Z0-9.\-_]/', self::GLUE, $name),
            serialize([$class, $config->groups, $config->attributes, $config->ignoredAttributes, $config->allowExtraAttributes, $config->getValidationGroupNames(), $config->format]),
        );
    }

    public function reset(): void
    {
        $this->owners = [];
        $this->shapes = [];
    }

    private function claimShape(string $name, string $fingerprint): string
    {
        $candidate = $name;

        for ($suffix = 2; ($this->shapes[$candidate] ??= $fingerprint) !== $fingerprint; ++$suffix) {
            $candidate = $name.self::GLUE.$suffix;
        }

        return $candidate;
    }

    private function claim(string $name, string $class): ?string
    {
        return ($this->owners[$name] ??= $class) === $class ? $name : null;
    }

    private function claimShortName(string $class): string
    {
        $segments = explode('\\', $class);

        for ($length = 1, $count = \count($segments); $length <= $count; ++$length) {
            if (null !== $name = $this->claim(implode(self::GLUE, \array_slice($segments, -$length)), $class)) {
                return $name;
            }
        }

        return implode(self::GLUE, $segments);
    }

    /**
     * @param array<int|string, mixed> $attributes
     */
    private static function attributesToString(array $attributes): string
    {
        $parts = [];

        foreach ($attributes as $key => $value) {
            if (\is_array($value)) {
                foreach (explode('_', self::attributesToString($value)) as $child) {
                    $parts[] = $key.self::GLUE.$child;
                }
            } else {
                $parts[] = \is_string($key) ? $key : $value;
            }
        }

        return implode('_', $parts);
    }
}
