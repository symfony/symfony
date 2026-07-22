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

use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;

/**
 * A helper providing autocompletion for available ObjectNormalizer options.
 *
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 */
final class ObjectNormalizerContextBuilder extends AbstractObjectNormalizerContextBuilder
{
    /**
     * Configures whether to throw when the property accessor refuses to write
     * an attribute the metadata reports as writable, instead of ignoring it.
     */
    public function withThrowOnInaccessibleAttributes(?bool $throwOnInaccessibleAttributes): static
    {
        return $this->with(ObjectNormalizer::THROW_ON_INACCESSIBLE_ATTRIBUTES, $throwOnInaccessibleAttributes);
    }
}
