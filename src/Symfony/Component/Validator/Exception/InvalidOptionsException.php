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
    private $options;

    public function __construct($message, array $options, \Exception $previous = null)
    {
        parent::__construct($message, 0, $previous);

        $this->options = $options;
    }

    public function getOptions()
    {
        return $this->options;
    }
}
