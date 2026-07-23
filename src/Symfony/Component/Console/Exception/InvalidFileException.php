<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Exception;

/**
 * @author Robin Chalas <robin.chalas@gmail.com>
 */
class InvalidFileException extends \RuntimeException implements ExceptionInterface
{
    public function __construct(string $message = '', int $code = 0, ?\Throwable $previous = null)
    {
        // These messages embed the offending file path, which may be attacker-influenced
        // (e.g. a name read from a directory the user does not control) and is rendered to
        // the terminal by the console error output. Strip terminal-escape introducer bytes
        // so a crafted path cannot inject escape sequences. Repeat until stable, since
        // removing a byte can splice two survivors into a fresh control sequence.
        do {
            $message = preg_replace("/[\x00-\x08\x0b-\x1f\x7f]|\xc2[\x80-\x9f]/", '', $message, -1, $count) ?? $message;
        } while ($count > 0);

        parent::__construct($message, $code, $previous);
    }
}
