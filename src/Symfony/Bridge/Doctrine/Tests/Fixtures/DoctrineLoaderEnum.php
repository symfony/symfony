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

/**
 * @ORM\Entity
 */
class DoctrineLoaderEnum
{
    /**
     * @ORM\Id
     * @ORM\Column
     */
    public $id;

    /**
     * @ORM\Column(type="string", enumType="Symfony\Bridge\Doctrine\Tests\PropertyInfo\Fixtures\EnumString", length=1)
     */
    public $enumString;

    /**
     * @ORM\Column(type="integer", enumType="Symfony\Bridge\Doctrine\Tests\PropertyInfo\Fixtures\EnumInt")
     */
    public $enumInt;
}
