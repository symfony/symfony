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

class UpdateCompositeIntIdEntity
{
    public $id1;
    public $id2;
    public $name;

    public function __construct($id1, $id2, $name)
    {
        $this->id1 = $id1;
        $this->id2 = $id2;
        $this->name = $name;
    }
}
