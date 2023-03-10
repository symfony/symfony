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

use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

// @deprecated remove in 7.0 in favor of direct use of the DenormalizerInterface
interface UpcomingDenormalizerInterface extends DenormalizerInterface
{
    public function getSupportedTypes(?string $format): array;
}
