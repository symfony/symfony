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
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
#[ORM\Entity, Assert\DisableAutoMapping]
class DoctrineLoaderNoAutoMappingEntity
{
    #[ORM\Id, ORM\Column]
    public $id;

    #[ORM\Column(length: 20, unique: true)]
    public $maxLength;

    #[Assert\EnableAutoMapping, ORM\Column(length: 20)]
    public $autoMappingExplicitlyEnabled;
}
