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
 * Adds the support of an extra $context parameter for the supportsDenormalization method.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
interface ContextAwareDenormalizerInterface extends DenormalizerInterface
{
    /**
     * {@inheritdoc}
     *
     * @param array $context options that denormalizers have access to
     */
    public function supportsDenormalization($data, string $type, ?string $format = null, array $context = []);
}
