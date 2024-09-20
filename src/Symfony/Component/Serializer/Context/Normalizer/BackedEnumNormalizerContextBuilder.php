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

use Symfony\Component\Serializer\Context\ContextBuilderInterface;
use Symfony\Component\Serializer\Context\ContextBuilderTrait;
use Symfony\Component\Serializer\Normalizer\BackedEnumNormalizer;

/**
 * A helper providing autocompletion for available BackedEnumNormalizer options.
 *
 * @author Nicolas PHILIPPE <nikophil@gmail.com>
 */
final class BackedEnumNormalizerContextBuilder implements ContextBuilderInterface
{
    use ContextBuilderTrait;

    /**
     * Configures if invalid values are allowed in denormalization.
     * They will be denormalized into `null` values.
     */
    public function withAllowInvalidValues(bool $allowInvalidValues): static
    {
        return $this->with(BackedEnumNormalizer::ALLOW_INVALID_VALUES, $allowInvalidValues);
    }
}
