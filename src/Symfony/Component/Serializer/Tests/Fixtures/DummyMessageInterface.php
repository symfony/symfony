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

/**
 * @DiscriminatorMap(typeProperty="type", mapping={
 *    "one"="Symfony\Component\Serializer\Tests\Fixtures\DummyMessageNumberOne",
 *    "two"="Symfony\Component\Serializer\Tests\Fixtures\DummyMessageNumberTwo"
 * })
 *
 * @author Samuel Roze <samuel.roze@gmail.com>
 */
interface DummyMessageInterface
{
}
