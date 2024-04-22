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

final class UnresolvableUriException extends \RuntimeException
{
    public function __construct(string $uri)
    {
        parent::__construct(sprintf('The URI "%s" cannot be used as a base URI in a resolution.', $uri));
    }
}
