<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Exception;

/**
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class MalformedCipherException extends \Exception implements EncryptionExceptionInterface
{
    public function __construct(\Throwable $previous = null)
    {
        parent::__construct('The message you provided does not look like a valid cipher text.', 0, $previous);
    }
}
