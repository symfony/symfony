<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Fixtures;

use Doctrine\Common\Collections\ArrayCollection;

class ArrayCollectionWithoutOffsetUnset extends ArrayCollection
{
    public function offsetUnset($offset)
    {
        throw new \Exception('The offsetUnset method should not be called on this class.');
    }
}
