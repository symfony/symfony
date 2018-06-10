<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Tests\PropertyInfo\Fixtures;

use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\Embedded;

/**
 * @Entity
 *
 * @author Udaltsov Valentin <udaltsov.valentin@gmail.com>
 */
class DoctrineWithEmbedded
{
    /**
     * @Id
     * @Column(type="smallint")
     */
    public $id;

    /**
     * @Embedded(class="DoctrineEmbeddable")
     */
    protected $embedded;
}
