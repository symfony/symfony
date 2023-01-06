<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Context\Normalizer;

use Symfony\Component\Serializer\Normalizer\PropertyNormalizer;

/**
 * A helper providing autocompletion for available PropertyNormalizer options.
 *
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 */
final class PropertyNormalizerContextBuilder extends AbstractObjectNormalizerContextBuilder
{
    /**
     * Configures whether fields should be output based on visibility.
     */
    public function withNormalizeVisibility(int $normalizeVisibility): static
    {
        return $this->with(PropertyNormalizer::NORMALIZE_VISIBILITY, $normalizeVisibility);
    }
}
