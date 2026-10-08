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

class ConstructorInitializedShipment
{
    public string $carrier;
    public string $label;

    public function __construct(
        public string $reference,
        public ?string $note,
        string $label,
        public int $quantity = 1,
    ) {
        $this->label = $label;
    }
}
