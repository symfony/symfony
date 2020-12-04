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
class UnsupportedAlgorithmException extends \LogicException implements EncryptionExceptionInterface
{
    public function __construct(string $algorithm, \Throwable $previous = null)
    {
        parent::__construct(sprintf('The cipher text is encrypted with "%s" algorithm. Decryption of that algorithm is not supported.', $algorithm));
    }
}
