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
use Symfony\Component\Serializer\Normalizer\FormErrorNormalizer;

/**
 * A helper providing autocompletion for available FormErrorNormalizer options.
 *
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 */
final class FormErrorNormalizerContextBuilder implements ContextBuilderInterface
{
    use ContextBuilderTrait;

    /**
     * Configures the title of the normalized data.
     */
    public function withTitle(?string $title): static
    {
        return $this->with(FormErrorNormalizer::TITLE, $title);
    }

    /**
     * Configures the type of the normalized data.
     */
    public function withType(?string $type): static
    {
        return $this->with(FormErrorNormalizer::TYPE, $type);
    }

    /**
     * Configures the code of the normalized data.
     */
    public function withStatusCode(?int $statusCode): static
    {
        return $this->with(FormErrorNormalizer::CODE, $statusCode);
    }
}
