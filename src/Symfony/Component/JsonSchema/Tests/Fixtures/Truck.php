<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonSchema\Tests\Fixtures;

use Symfony\Component\JsonSchema\Attribute\JsonSchemaConstraint;

class Truck extends Vehicle
{
    public int $axles = 2;

    #[JsonSchemaConstraint(description: 'The kind of vehicle.')]
    public string $type = 'truck';
}
