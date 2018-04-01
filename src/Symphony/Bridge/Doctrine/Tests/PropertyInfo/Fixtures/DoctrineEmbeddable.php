<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Bridge\Doctrine\Tests\PropertyInfo\Fixtures;

use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Embeddable;

/**
 * @Embeddable
 *
 * @author Udaltsov Valentin <udaltsov.valentin@gmail.com>
 */
class DoctrineEmbeddable
{
    /**
     * @Column(type="string")
     */
    protected $field;
}
