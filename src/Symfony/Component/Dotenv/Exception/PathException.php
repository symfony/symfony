<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Dotenv\Exception;

/**
 * Thrown when a file does not exist or is not readable.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class PathException extends \RuntimeException implements ExceptionInterface
{
    public function __construct(string $path, int $code = 0, \Exception $previous = null)
    {
        parent::__construct(sprintf('Unable to read the "%s" environment file.', $path), $code, $previous);
    }
}
