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
    /**
     * @param NotNormalizableValueException[] $errors
     */
    public function __construct(
        private mixed $data,
        private array $errors,
    ) {
    }

    public function getData(): mixed
    {
        return $this->data;
    }

    /**
     * @return NotNormalizableValueException[]
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
