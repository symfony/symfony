<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Exception;

/**
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
class PartialDenormalizationException extends UnexpectedValueException
{
    private ?ExtraAttributesException $extraAttributesError = null;

    /**
     * @param NotNormalizableValueException[] $notNormalizableErrors
     * @param ExtraAttributesException[]      $extraAttributesErrors
     */
    public function __construct(
        private mixed $data,
        private array $notNormalizableErrors,
        array $extraAttributesErrors = [],
    ) {
        $extraAttributes = [];
        foreach ($extraAttributesErrors as $error) {
            $extraAttributes = array_merge($extraAttributes, $error->getExtraAttributes());
        }
        if ($extraAttributes) {
            $this->extraAttributesError = new ExtraAttributesException($extraAttributes);
        }
    }

    public function getData(): mixed
    {
        return $this->data;
    }

    /**
     * @deprecated since Symfony 8.1, use getNotNormalizableValueErrors() instead
     */
    public function getErrors(): array
    {
        trigger_deprecation('symfony/serializer', '8.1', 'The "%s()" method is deprecated, use "%s::getNotNormalizableValueErrors()" instead.', __METHOD__, self::class);

        return $this->getNotNormalizableValueErrors();
    }

    /**
     * @return NotNormalizableValueException[]
     */
    public function getNotNormalizableValueErrors(): array
    {
        return $this->notNormalizableErrors;
    }

    public function getExtraAttributesError(): ?ExtraAttributesException
    {
        return $this->extraAttributesError;
    }
}
