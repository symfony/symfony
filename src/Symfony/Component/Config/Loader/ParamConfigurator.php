<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Config\Loader;

/**
 * Placeholder for a parameter.
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class ParamConfigurator
{
    public function __construct(
        private string $name,
    ) {
    }

    public function __toString(): string
    {
        return '%'.$this->name.'%';
    }
}
