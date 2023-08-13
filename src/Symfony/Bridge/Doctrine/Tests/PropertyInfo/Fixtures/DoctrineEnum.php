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

#[Entity]
class DoctrineEnum
{
    #[Id, Column(type: 'smallint')]
    public $id;

    #[Column(type: 'string', enumType: EnumString::class)]
    protected $enumString;

    #[Column(type: 'integer', enumType: EnumInt::class)]
    protected $enumInt;

    #[Column(type: 'array', enumType: EnumString::class)]
    protected $enumStringArray;

    #[Column(type: 'simple_array', enumType: EnumInt::class)]
    protected $enumIntArray;

    #[Column(type: 'custom_foo', enumType: EnumInt::class)]
    protected $enumCustom;
}
