<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Config\Definition\Exception;

/**
 * This exception is thrown if an invalid type is encountered.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class InvalidTypeException extends InvalidConfigurationException
{
    protected $hint;
    protected $containsHints = false;

    public function addHint($hint)
    {
        if (!$this->containsHints) {
            $this->message .= "\nHint: ".$hint;
        } else {
            $this->message .= ', '.$hint;
        }
    }
}
