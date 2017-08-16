<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Exception;

/**
 * This exception is thrown when a secret file is not found.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class SecretNotFoundException extends InvalidArgumentException
{
    public function __construct($path)
    {
        parent::__construct(sprintf('Secret file not found: "%s".', $path));
    }
}
