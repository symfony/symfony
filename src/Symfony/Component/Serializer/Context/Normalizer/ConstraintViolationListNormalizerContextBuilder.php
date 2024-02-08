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
use Symfony\Component\Serializer\Normalizer\ConstraintViolationListNormalizer;

/**
 * A helper providing autocompletion for available ConstraintViolationList options.
 *
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 */
final class ConstraintViolationListNormalizerContextBuilder implements ContextBuilderInterface
{
    use ContextBuilderTrait;

    /**
     * Configure the instance field of normalized data.
     */
    public function withInstance(mixed $instance): static
    {
        return $this->with(ConstraintViolationListNormalizer::INSTANCE, $instance);
    }

    /**
     * Configure the status field of normalized data.
     */
    public function withStatus(?int $status): static
    {
        return $this->with(ConstraintViolationListNormalizer::STATUS, $status);
    }

    /**
     * Configure the title field of normalized data.
     */
    public function withTitle(?string $title): static
    {
        return $this->with(ConstraintViolationListNormalizer::TITLE, $title);
    }

    /**
     * Configure the type field of normalized data.
     */
    public function withType(?string $type): static
    {
        return $this->with(ConstraintViolationListNormalizer::TYPE, $type);
    }

    /**
     * Configures the payload fields which will act as an allowlist
     * for the payload field of normalized data.
     *
     * Eg: ['foo', 'bar']
     *
     * @param list<string>|null $payloadFields
     */
    public function withPayloadFields(?array $payloadFields): static
    {
        return $this->with(ConstraintViolationListNormalizer::PAYLOAD_FIELDS, $payloadFields);
    }
}
