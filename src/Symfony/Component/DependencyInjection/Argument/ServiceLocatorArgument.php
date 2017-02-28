<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Argument;

use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;

/**
 * Represents a service locator able to lazy load a given range of services.
 *
 * @author Robin Chalas <robin.chalas@gmail.com>
 */
class ServiceLocatorArgument implements ArgumentInterface
{
    private $values;

    /**
     * @param Reference[] $values An array of references indexed by identifier
     */
    public function __construct(array $values)
    {
        $this->setValues($values);
    }

    public function getValues()
    {
        return $this->values;
    }

    public function setValues(array $values)
    {
        foreach ($values as $v) {
            if (!$v instanceof Reference && null !== $v) {
                throw new InvalidArgumentException('Values of a ServiceLocatorArgument must be Reference objects.');
            }
        }

        $this->values = $values;
    }
}
