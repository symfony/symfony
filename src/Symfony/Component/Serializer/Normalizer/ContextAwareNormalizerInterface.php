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
 * Adds the support of an extra $context parameter for the supportsNormalization method.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 *
 * @deprecated since symfony/serializer 6.1, use NormalizerInterface instead
 */
interface ContextAwareNormalizerInterface extends NormalizerInterface
{
    /**
     * {@inheritdoc}
     *
     * @param array $context options that normalizers have access to
     */
    public function supportsNormalization(mixed $data, string $format = null, array $context = []): bool;
}
