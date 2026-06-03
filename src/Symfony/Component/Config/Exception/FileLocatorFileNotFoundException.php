<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Config\Exception;

/**
 * File locator exception if a file does not exist.
 *
 * @author Leo Feyer <https://github.com/leofeyer>
 */
class FileLocatorFileNotFoundException extends \InvalidArgumentException
{
    public function __construct(
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
        private array $paths = [],
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function getPaths(): array
    {
        return $this->paths;
    }
}
