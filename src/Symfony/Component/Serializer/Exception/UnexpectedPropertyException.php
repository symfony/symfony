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
 * UnexpectedPropertyException.
 *
 * @author Aurélien Pillevesse <aurelienpillevesse@hotmail.fr>
 */
class UnexpectedPropertyException extends \UnexpectedValueException implements ExceptionInterface
{
    public function __construct(
        public readonly string $property,
        ?\Throwable $previous = null,
    ) {
        $msg = \sprintf('Property is not allowed ("%s" is unknown).', $this->property);

        parent::__construct($msg, 0, $previous);
    }
}
