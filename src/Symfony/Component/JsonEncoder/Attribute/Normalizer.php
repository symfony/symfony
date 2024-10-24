<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonEncoder\Attribute;

/**
 * Defines a {@see \Symfony\Component\JsonEncoder\Encode\Normalizer\NormalizerInterface} service id
 * that will be used to normalize the property data during encoding.
 *
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 *
 * @experimental
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
class Normalizer
{
    public function __construct(
        private string $serviceId,
    ) {
    }

    public function getServiceId(): string
    {
        return $this->serviceId;
    }
}
