<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Uid\Exception;

/**
 * The $invalidValue property holds the raw value that failed validation, which
 * is typically untrusted user input of arbitrary size or content. The exception
 * message itself is safe to surface, but consumers should not serialize the
 * exception verbatim (var_export, serialize, structured loggers dumping public
 * properties) without redacting or truncating $invalidValue.
 */
class InvalidArgumentException extends \InvalidArgumentException
{
    public function __construct(
        string $message,
        public readonly mixed $invalidValue = null,
    ) {
        parent::__construct($message);
    }
}
