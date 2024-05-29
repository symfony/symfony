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
use Symfony\Component\Serializer\Normalizer\ProblemNormalizer;

/**
 * A helper providing autocompletion for available ProblemNormalizer options.
 *
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 */
final class ProblemNormalizerContextBuilder implements ContextBuilderInterface
{
    use ContextBuilderTrait;

    /**
     * Configure the title field of normalized data.
     */
    public function withTitle(?string $title): static
    {
        return $this->with(ProblemNormalizer::TITLE, $title);
    }

    /**
     * Configure the type field of normalized data.
     */
    public function withType(?string $type): static
    {
        return $this->with(ProblemNormalizer::TYPE, $type);
    }

    /**
     * Configure the status field of normalized data.
     */
    public function withStatusCode(int|string|null $statusCode): static
    {
        return $this->with(ProblemNormalizer::STATUS, $statusCode);
    }
}
