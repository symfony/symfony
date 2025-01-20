<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\AssetMapper\Compressor;

/**
 * @internal
 *
 * @author Kévin Dunglas <kevin@dunglas.dev>
 */
interface SupportedCompressorInterface extends CompressorInterface
{
    /**
     * Returns null if the compressor is supported, or the reason why the compressor it is not.
     */
    public function getUnsupportedReason(): ?string;
}
