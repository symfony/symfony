<?php

declare(strict_types=1);


namespace Symfony\Component\Security\Core\Exception;

/**
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class UnsupportedAlgorithmException extends \LogicException implements EncryptionExceptionInterface
{
    public function __construct(string $algorithm, \Throwable $previous = null)
    {
        parent::__construct(sprintf('The cipher text is encrypted with "%s" algorithm. Decryption of that algorithm is not supported.', $algorithm));
    }
}
