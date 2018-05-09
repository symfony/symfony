<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Normalizer;

/**
 * Defines the criteria by which normalizers and denormalizers may vary
 * their support.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
interface VaryingSupportInterface
{
    /**
     * Checks whether the normalization and denormalization support
     * will vary depending on the data and context provided.
     */
    public function isSupportVariedOnDataAndContext(): bool;
}
