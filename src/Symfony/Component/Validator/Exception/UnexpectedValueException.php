<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Exception;

/**
 * @author Christian Flothmann <christian.flothmann@sensiolabs.de>
 */
class UnexpectedValueException extends UnexpectedTypeException
{
    private $expectedType;

    public function __construct($value, string $expectedType)
    {
        parent::__construct($value, $expectedType);

        $this->expectedType = $expectedType;
    }

    public function getExpectedType(): string
    {
        return $this->expectedType;
    }
}
