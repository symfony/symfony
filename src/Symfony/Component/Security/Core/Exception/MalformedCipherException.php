<?php

declare(strict_types=1);


namespace Symfony\Component\Security\Core\Exception;

/**
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class MalformedCipherException extends \Exception implements EncryptionExceptionInterface
{
    public function __construct(\Throwable $previous = null)
    {
        parent::__construct('The message you provided does not look like a valid cipher text.', 0, $previous);
    }
}
