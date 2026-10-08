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

/**
 * A date that can be cast to a string, as Carbon's.
 */
class StringableDateTime extends \DateTime implements \Stringable
{
    public function __toString(): string
    {
        return $this->format('Y-m-d H:i:s');
    }
}
