<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonPath\Exception;

/**
 * @author Alexandre Daubois <alex.daubois@gmail.com>
 */
class InvalidJsonPathException extends \LogicException implements ExceptionInterface
{
    public function __construct(string $message, ?int $position = null)
    {
        parent::__construct(\sprintf('JSONPath syntax error%s: %s', $position ? ' at position '.$position : '', $message));
    }
}
