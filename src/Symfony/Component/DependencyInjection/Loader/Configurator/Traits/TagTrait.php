<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Loader\Configurator\Traits;

use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;

trait TagTrait
{
    /**
     * Adds a tag for this definition.
     *
     * @return $this
     */
    final public function tag(string $name, array $attributes = []): self
    {
        if ('' === $name) {
            throw new InvalidArgumentException(sprintf('The tag name for service "%s" must be a non-empty string.', $this->id));
        }

        $this->recursiveValidateAttributes($name, $attributes);

        $this->definition->addTag($name, $attributes);

        return $this;
    }

    private function recursiveValidateAttributes(string $name, array $attributes, string $prefix = ''): void
    {
        foreach ($attributes as $attribute => $value) {
            if (\is_array($value)) {
                $this->recursiveValidateAttributes($name, $attributes, $attribute.'.');
            } elseif (!is_scalar($value) && null !== $value) {
                throw new InvalidArgumentException(sprintf('A tag attribute must be of a scalar-type or an array of scalar-types for service "%s", tag "%s", attribute "%s".', $this->id, $name, $prefix.$attribute));
            }
        }
    }
}
