<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Fixtures\Bundles\YamlBundle\Entity;

class Person
{
    public function __construct(
        protected int $id,
        public string $name,
    ) {
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
