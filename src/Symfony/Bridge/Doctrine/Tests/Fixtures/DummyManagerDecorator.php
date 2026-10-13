<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Tests\Fixtures;

use Doctrine\Persistence\ObjectManager;
use Doctrine\Persistence\ObjectManagerDecorator;

class DummyManagerDecorator extends ObjectManagerDecorator
{
    public function __construct(ObjectManager $wrapped)
    {
        $this->wrapped = $wrapped;
    }

    public function getWrapped(): ObjectManager
    {
        return $this->wrapped;
    }
}
