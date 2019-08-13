<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Functional\Bundle\LegacyBundle\Entity;

class LegacyPerson
{
    public $name;
    public $age;

    public function __construct(string $name, string $age)
    {
        $this->name = $name;
        $this->age = $age;
    }
}
