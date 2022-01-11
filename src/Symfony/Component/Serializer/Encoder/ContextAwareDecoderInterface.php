<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Encoder;

/**
 * Adds the support of an extra $context parameter for the supportsDecoding method.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 *
 * @deprecated since symfony/serializer 6.1, use DecoderInterface instead
 */
interface ContextAwareDecoderInterface extends DecoderInterface
{
    /**
     * {@inheritdoc}
     *
     * @param array $context options that decoders have access to
     */
    public function supportsDecoding(string $format, array $context = []): bool;
}
