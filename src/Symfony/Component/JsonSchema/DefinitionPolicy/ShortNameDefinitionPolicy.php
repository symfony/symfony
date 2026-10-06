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
use Symfony\Component\JsonSchema\DefinitionParent;
use Symfony\Component\JsonSchema\SchemaDocument;

/**
 * Names definitions after the class short name (or the configured prefix for the root definition),
 * suffixed by the groups, attributes, ignored attributes, extra attributes, validation groups, format and direction.
 *
 * In a document, a name is owned by the first class claiming it: another class asking for the same
 * name gets one built from more namespace segments instead.
 *
 * Otherwise, a name only depends on the class and the configuration. The rare shapes that still read alike
 * (attributes ["first_name"] and ["first", "name"]) make the generator throw, as they need a custom policy.
 *
 * @experimental
 */
final class ShortNameDefinitionPolicy implements DefinitionPolicyInterface
{
    private const GLUE = '.';

    /**
     * Definition name prefix => class that claimed it first in a document, so two classes sharing a short name
     * (App\Input\Book, App\Output\Book) never end up under the same definition.
     *
     * @var \WeakMap<SchemaDocument, array<string, class-string>>
     */
    private \WeakMap $owners;

    public function __construct()
    {
        $this->owners = new \WeakMap();
    }

    public function nameFor(string $class, Configuration $config, SchemaDocument $document, ?DefinitionParent $parent = null): string
    {
        $prefix = (null === $parent && null !== $config->definitionPrefix ? $this->claim($config->definitionPrefix, $class, $document) : null) ?? $this->claimShortName($class, $document);

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

            if ($config->ignoredAttributes) {
                $parts[] = 'ignored'.self::GLUE.implode('_', $config->ignoredAttributes);
            }

            if (!$config->allowExtraAttributes) {
                $parts[] = 'closed';
            }

            if (null !== $validationGroups = $config->getValidationGroupNames()) {
                $parts[] = 'validation'.self::GLUE.($validationGroups ? implode('_', $validationGroups) : 'none');
            }

            if (null !== $config->format) {
                $parts[] = 'format'.self::GLUE.$config->format;
            }

            if (null !== $config->direction) {
                $parts[] = strtolower($config->direction->name);
            }

            $name = $parts ? $prefix.'-'.implode('_', $parts) : $prefix;
        }

        return (string) preg_replace('/[^a-zA-Z0-9.\-_]/', self::GLUE, $name);
    }

    /**
     * @param class-string $class
     */
    private function claim(string $name, string $class, SchemaDocument $document): ?string
    {
        $owners = $this->owners[$document] ?? [];
        $owner = $owners[$name] ??= $class;
        $this->owners[$document] = $owners;

        return $owner === $class ? $name : null;
    }

    /**
     * @param class-string $class
     */
    private function claimShortName(string $class, SchemaDocument $document): string
    {
        $segments = explode('\\', $class);

        for ($length = 1, $count = \count($segments); $length <= $count; ++$length) {
            if (null !== $name = $this->claim(implode(self::GLUE, \array_slice($segments, -$length)), $class, $document)) {
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
