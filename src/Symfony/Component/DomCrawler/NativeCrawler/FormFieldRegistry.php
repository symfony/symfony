<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DomCrawler\NativeCrawler;

use Symfony\Component\DomCrawler\FormFieldRegistryTrait;
use Symfony\Component\DomCrawler\NativeCrawler\Field\ChoiceFormField;
use Symfony\Component\DomCrawler\NativeCrawler\Field\FormField;

/**
 * @author Alexandre Daubois <alex.daubois@gmail.com>
 *
 * @internal
 */
class FormFieldRegistry
{
    use FormFieldRegistryTrait;

    /**
     * Adds a field to the registry.
     */
    public function add(FormField $field): void
    {
        $segments = $this->getSegments($field->getName());

        $target = &$this->fields;
        while ($segments) {
            if (!\is_array($target)) {
                $target = [];
            }
            $path = array_shift($segments);
            if ('' === $path) {
                $target = &$target[];
            } else {
                $target = &$target[$path];
            }
        }
        $target = $field;
    }

    /**
     * Returns the value of the field based on the fully qualified name and its children.
     *
     * @return FormField|FormField[]|FormField[][]
     *
     * @throws \InvalidArgumentException if the field does not exist
     */
    public function &get(string $name): FormField|array
    {
        $segments = $this->getSegments($name);
        $target = &$this->fields;
        while ($segments) {
            $path = array_shift($segments);
            if (!\is_array($target) || !\array_key_exists($path, $target)) {
                throw new \InvalidArgumentException(\sprintf('Unreachable field "%s".', $path));
            }
            $target = &$target[$path];
        }

        return $target;
    }

    /**
     * Set the value of a field based on the fully qualified name and its children.
     *
     * @throws \InvalidArgumentException if the field does not exist
     */
    public function set(string $name, mixed $value): void
    {
        $target = &$this->get($name);
        if ((!\is_array($value) && $target instanceof FormField) || $target instanceof ChoiceFormField) {
            $target->setValue($value);
        } elseif (\is_array($value)) {
            $registry = new static();
            $registry->base = $name;
            $registry->fields = $value;
            foreach ($registry->all() as $k => $v) {
                $this->set($k, $v);
            }
        } else {
            throw new \InvalidArgumentException(\sprintf('Cannot set value on a compound field "%s".', $name));
        }
    }
}
