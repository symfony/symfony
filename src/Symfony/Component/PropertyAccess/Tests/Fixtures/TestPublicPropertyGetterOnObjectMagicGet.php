<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyAccess\Tests\Fixtures;

class TestPublicPropertyGetterOnObjectMagicGet
{
    public $a = 'A';
    private $b = 'B';

    public function __get($property)
    {
        if ('b' === $property) {
            return $this->b;
        }
    }
}
