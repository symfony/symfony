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
 * @author Christian Flothmann <christian.flothmann@sensiolabs.de>
 */
class NotNormalizableValueException extends UnexpectedValueException
{
    private ?string $attribute;

    public function __construct(string $message, int $code = 0, \Throwable $previous = null, ?string $attribute = null)
    {
        $this->$attribute = $attribute;

        parent::__construct($message, $code, $previous);
    }

    public function getAttribute(): ?string
    {
        return $this->attribute;
    }
}
