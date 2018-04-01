<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Serializer\Normalizer;

/**
 * Adds the support of an extra $context parameter for the supportsNormalization method.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
interface ContextAwareNormalizerInterface extends NormalizerInterface
{
    /**
     * {@inheritdoc}
     *
     * @param array $context options that normalizers have access to
     */
    public function supportsNormalization($data, $format = null, array $context = array());
}
