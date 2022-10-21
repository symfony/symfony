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
    private $data;
    private $errors;

    public function __construct($data, array $errors)
    {
        $this->data = $data;
        $this->errors = $errors;
    }

    public function getData()
    {
        return $this->data;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}
