<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Validator\Exception;

class MissingOptionsException extends ValidatorException
{
    private $options;

    public function __construct(string $message, array $options)
    {
        parent::__construct($message);

        $this->options = $options;
    }

    public function getOptions()
    {
        return $this->options;
    }
}
