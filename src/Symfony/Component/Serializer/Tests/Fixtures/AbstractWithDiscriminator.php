<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests\Fixtures;

use Symfony\Component\Serializer\Annotation\DiscriminatorMap;

#[DiscriminatorMap(typeProperty: 'discr', mapping: ['concrete' => ConcreteWithDiscriminator::class])]
abstract class AbstractWithDiscriminator
{
    /**
     * @var int The id
     */
    public ?int $id = null;

    /**
     * @var string The dummy name
     */
    public string $name;
}
