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

use Doctrine\ORM\Mapping AS ORM;

/**
 * An entity whose primary key is a foreign key to another entity.
 *
 * @see http://docs.doctrine-project.org/projects/doctrine-orm/en/latest/tutorials/composite-primary-keys.html#identity-through-foreign-entities
 * @ORM\Entity
 */
class AssociationKeyEntity
{
    /**
     * @ORM\Id @ORM\OneToOne(targetEntity="SingleIntIdEntity")
     *
     * @var SingleIntIdEntity
     */
    public $single;

    /**
     * AssociationKeyEntity constructor.
     *
     * @param SingleIntIdEntity $single
     */
    public function __construct(SingleIntIdEntity $single)
    {
        $this->single = $single;
    }

    public function getName()
    {
        return $this->single->name;
    }
}
