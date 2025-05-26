<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http;

class RequestSupport
{
    public bool $result;
    public ?bool $lazy = null;

    /** @var list<string> */
    public array $reasons = [];

    public function addReason(string $reason): void
    {
        $this->reasons[] = $reason;
    }
}
