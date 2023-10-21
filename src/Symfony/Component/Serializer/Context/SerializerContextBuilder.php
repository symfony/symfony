<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Context;

use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Serializer;

/**
 * A helper providing autocompletion for available Serializer options.
 *
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 */
final class SerializerContextBuilder implements ContextBuilderInterface
{
    use ContextBuilderTrait;

    /**
     * Configures whether an empty array should be transformed to an
     * object (in JSON: {}) or to a list (in JSON: []).
     */
    public function withEmptyArrayAsObject(?bool $emptyArrayAsObject): static
    {
        return $this->with(Serializer::EMPTY_ARRAY_AS_OBJECT, $emptyArrayAsObject);
    }

    public function withCollectDenormalizationErrors(?bool $collectDenormalizationErrors): static
    {
        return $this->with(DenormalizerInterface::COLLECT_DENORMALIZATION_ERRORS, $collectDenormalizationErrors);
    }
}
