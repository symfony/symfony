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

class AmbiguousServiceException extends RuntimeException
{
    public function __construct($type, $services)
    {
        parent::__construct(sprintf('Ambiguous services for class "%s". You should use concrete service name instead of class: "%s"', $type, implode('", "', $services)));
    }
}
