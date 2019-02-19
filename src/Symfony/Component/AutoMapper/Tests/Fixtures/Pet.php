<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\AutoMapper\Tests\Fixtures;

use Symfony\Component\Serializer\Annotation\DiscriminatorMap;

/**
 * @DiscriminatorMap(typeProperty="type", mapping={
 *    "cat"="Symfony\Component\AutoMapper\Tests\Fixtures\Cat",
 *    "dog"="Symfony\Component\AutoMapper\Tests\Fixtures\Dog"
 * })
 */
class Pet
{
    /** @var string */
    public $type;
}
