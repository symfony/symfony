<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Symfony\Component\Messenger\Attribute;

/**
 * @author Maxim Dovydenok <dovydenok.maxim@gmail.com>
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class Senders
{
    public array $senders;

    /**
     * @param string[] $senders
     */
    public function __construct(string ...$senders)
    {
        $this->senders = $senders;
    }
}
