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

use Symfony\Component\Serializer\Attribute\DiscriminatorMap;

/**
 * @author Samuel Roze <samuel.roze@gmail.com>
 */
#[DiscriminatorMap(typeProperty: 'type', mapping: [
    'one' => DummyMessageNumberOne::class,
    'two' => DummyMessageNumberTwo::class,
    'three' => DummyMessageNumberThree::class,
])]
interface DummyMessageInterface
{
}
