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

class InvalidOptionsException extends ValidatorException
{
    public function __construct(
        string $message,
        private array $options,
    ) {
        parent::__construct($message);
    }

    public function getOptions(): array
    {
        return $this->options;
    }
}
