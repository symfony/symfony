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
use Symfony\Bridge\Doctrine\Tests\PropertyInfo\Fixtures\EnumInt;
use Symfony\Bridge\Doctrine\Tests\PropertyInfo\Fixtures\EnumString;

#[ORM\Entity]
class DoctrineLoaderEnum
{
    #[ORM\Id, ORM\Column]
    public $id;

    #[ORM\Column(type: 'string', enumType: EnumString::class, length: 1)]
    public $enumString;

    #[ORM\Column(type: 'integer', enumType: EnumInt::class)]
    public $enumInt;
}
