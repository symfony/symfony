<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Validator\Listener\Exception;

use Symfony\Component\Validator\Exception\ValidationFailedException;

class EntityValidationFailedException extends \RuntimeException
{
    /**
     * @param ValidationFailedException[] $errors
     */
    public function __construct(private readonly array $errors)
    {
        parent::__construct('Validation failed for one or more entities.');
    }

    /**
     * @return ValidationFailedException[]
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
