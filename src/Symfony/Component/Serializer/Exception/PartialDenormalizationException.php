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

    public function __construct(
        private mixed $data,
        /**
         * @var NotNormalizableValueException[]
         */
        private array $notNormalizableErrors,
        array $extraAttributesErrors = []
    )
    {
        $this->data = $data;
        $this->notNormalizableErrors = $notNormalizableErrors;
        $extraAttributes = [];
        foreach ($extraAttributesErrors as $error) {
            $extraAttributes = \array_merge($extraAttributes, $error->getExtraAttributes());
        }
        if ($extraAttributes) {
            $this->extraAttributesError = new ExtraAttributesException($extraAttributes);
        }
    }

    public function getData()
    {
        return $this->data;
    }

    /**
     * @deprecated since Symfony 6.2, use getNotNormalizableValueErrors() instead.
     */
    public function getErrors(): array
    {
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
