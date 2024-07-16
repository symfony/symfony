<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DomCrawler;

use Symfony\Component\DomCrawler\Field\FormField;

/**
 * @author Alexandre Daubois <alex.daubois@gmail.com>
 *
 * @internal
 */
trait FormFieldRegistryTrait
{
    private array $fields = [];
    private string $base = '';

    /**
     * Removes a field based on the fully qualified name and its children from the registry.
     */
    public function remove(string $name): void
    {
        $segments = $this->getSegments($name);
        $target = &$this->fields;
        while (\count($segments) > 1) {
            $path = array_shift($segments);
            if (!\is_array($target) || !\array_key_exists($path, $target)) {
                return;
            }
            $target = &$target[$path];
        }
        unset($target[array_shift($segments)]);
    }

    /**
     * Tests whether the form has the given field based on the fully qualified name.
     */
    public function has(string $name): bool
    {
        try {
            $this->get($name);

            return true;
        } catch (\InvalidArgumentException) {
            return false;
        }
    }

    /**
     * Returns the list of field with their value.
     *
     * @return FormField[] The list of fields as [string] Fully qualified name => (mixed) value)
     */
    public function all(): array
    {
        return $this->walk($this->fields, $this->base);
    }

    /**
     * Transforms a PHP array in a list of fully qualified name / value.
     */
    private function walk(array $array, ?string $base = '', array &$output = []): array
    {
        foreach ($array as $k => $v) {
            $path = $base ? \sprintf('%s[%s]', $base, $k) : $k;
            if (\is_array($v)) {
                $this->walk($v, $path, $output);
            } else {
                $output[$path] = $v;
            }
        }

        return $output;
    }

    /**
     * Splits a field name into segments as a web browser would do.
     *
     *     getSegments('base[foo][3][]') = ['base', 'foo, '3', ''];
     *
     * @return string[]
     */
    private function getSegments(string $name): array
    {
        if (preg_match('/^(?P<base>[^[]+)(?P<extra>(\[.*)|$)/', $name, $m)) {
            $segments = [$m['base']];
            while (!empty($m['extra'])) {
                $extra = $m['extra'];
                if (preg_match('/^\[(?P<segment>.*?)\](?P<extra>.*)$/', $extra, $m)) {
                    $segments[] = $m['segment'];
                } else {
                    $segments[] = $extra;
                }
            }

            return $segments;
        }

        return [$name];
    }
}
