<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Serializer\Tests\Fixtures;

class GroupDummyChild extends GroupDummy
{
    private $baz;

    /**
     * @return mixed
     */
    public function getBaz()
    {
        return $this->baz;
    }

    /**
     * @param mixed $baz
     */
    public function setBaz($baz)
    {
        $this->baz = $baz;
    }
}
