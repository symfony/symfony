<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Asset\Exception;

/**
 * Represents an asset not found in a manifest.
 */
class AssetNotFoundException extends RuntimeException
{
    private array $alternatives;

    /**
     * @param string     $message      Exception message to throw
     * @param array      $alternatives List of similar defined names
     * @param int        $code         Exception code
     * @param \Throwable $previous     Previous exception used for the exception chaining
     */
    public function __construct(string $message, array $alternatives = [], int $code = 0, \Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);

        $this->alternatives = $alternatives;
    }

    public function getAlternatives(): array
    {
        return $this->alternatives;
    }
}
