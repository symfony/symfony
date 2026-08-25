<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Exception;

class ClaimCheckNotFoundException extends RuntimeException
{
    public function __construct(string $id)
    {
        parent::__construct(\sprintf('Claim check "%s" was not found.', $id));
    }
}
