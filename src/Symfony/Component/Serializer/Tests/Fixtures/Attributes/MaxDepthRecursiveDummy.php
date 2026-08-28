<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests\Fixtures\Attributes;

use Symfony\Component\Serializer\Attribute\MaxDepth;

class MaxDepthRecursiveDummy
{
    public $name;

    #[MaxDepth(1)]
    public $linked;

    public function getName()
    {
        return $this->name;
    }

    public function getLinked()
    {
        return $this->linked;
    }
}
