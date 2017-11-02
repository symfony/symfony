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

use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\Column;

/** @Entity */
class Price
{
    /** @Id @Column(type="integer") */
    public $id;

    /** @Column(type="decimal") */
    public $value;

    /**
     * @param int $id
     * @param float $value
     */
    public function __construct(int $id, float $value)
    {
        $this->id = $id;
        $this->value = $value;
    }
}
