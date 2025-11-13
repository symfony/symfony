<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Functional\Bundle\TestBundle\TestServiceContainer;

class ResettableService
{
    private $count = 0;

    public function myCustomName(): void
    {
        ++$this->count;
    }

    public function getCount(): int
    {
        return $this->count;
    }
}
