<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpClient\Internal;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @internal
 */
final class Canary
{
    public function __construct(
        private \Closure $canceller,
    ) {
    }

    public function cancel(): void
    {
        if (isset($this->canceller)) {
            $canceller = $this->canceller;
            unset($this->canceller);
            $canceller();
        }
    }

    public function __destruct()
    {
        $this->cancel();
    }
}
