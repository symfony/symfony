<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Functional\Bundle\TestBundle\ConfigureContainer;

class Greeter
{
    public function __construct(
        private string $greeting = 'real',
    ) {
    }

    public function greet(): string
    {
        return $this->greeting;
    }
}
