<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement\BlindIndex\Projection;

use Symfony\Component\KeyManagement\BlindIndex\ProjectionInterface;

/**
 * Indexes the value byte for byte.
 *
 * Says out loud that nothing is folded, so `Ada` and `ada` are two values and a search has to
 * present the value exactly as it was written.
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 *
 * @experimental
 */
final class Verbatim implements ProjectionInterface
{
    public function project(#[\SensitiveParameter] string $value): string
    {
        return $value;
    }
}
