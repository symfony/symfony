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
 *
 * @deprecated since symfony/serializer 6.1, use DenormalizerInterface instead
 */
interface ContextAwareDenormalizerInterface extends DenormalizerInterface
{
    /**
     * @param array $context options that denormalizers have access to
     */
    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool;
}
