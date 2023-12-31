<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\Exception\Parser;

class MissingHostException extends \InvalidArgumentException
{
    public function __construct()
    {
        parent::__construct('The URL must contain a host.');
    }
}
