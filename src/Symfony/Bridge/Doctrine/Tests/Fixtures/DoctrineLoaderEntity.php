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

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Entity
 * @UniqueEntity(fields={"alreadyMappedUnique"})
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class DoctrineLoaderEntity
{
    /**
     * @ORM\Id
     * @ORM\Column
     */
    public $id;

    /**
     * @ORM\Column(length=20)
     */
    public $maxLength;

    /**
     * @ORM\Column(length=20)
     * @Assert\Length(min=5)
     */
    public $mergedMaxLength;

    /**
     * @ORM\Column(length=20)
     * @Assert\Length(min=1, max=10)
     */
    public $alreadyMappedMaxLength;

    /**
     * @ORM\Column(unique=true)
     */
    public $unique;

    /**
     * @ORM\Column(unique=true)
     */
    public $alreadyMappedUnique;
}
