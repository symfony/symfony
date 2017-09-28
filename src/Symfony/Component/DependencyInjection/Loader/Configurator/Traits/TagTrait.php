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
     * @param string $name       The tag name
     * @param array  $attributes An array of attributes
     *
     * @return $this
     */
    final public function tag($name, array $attributes = array())
    {
        if (!is_string($name) || '' === $name) {
            throw new InvalidArgumentException(sprintf('The tag name for service "%s" must be a non-empty string.', $this->id));
        }

        foreach ($attributes as $attribute => $value) {
            if (!is_scalar($value) && null !== $value) {
                throw new InvalidArgumentException(sprintf('A tag attribute must be of a scalar-type for service "%s", tag "%s", attribute "%s".', $this->id, $name, $attribute));
            }
        }

        $this->definition->addTag($name, $attributes);

        return $this;
    }
}
