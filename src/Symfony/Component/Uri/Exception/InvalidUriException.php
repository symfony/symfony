<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Uri\Exception;

final class InvalidUriException extends \RuntimeException
{
    public function __construct(string $uri)
    {
        parent::__construct(sprintf('The URI "%s" is invalid.', $uri));
    }
}
