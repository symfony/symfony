<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Stamp;

/**
 * Stamp used to identify which bus it was passed to.
 *
 * @author Ryan Weaver <ryan@symfonycasts.com>
 */
final class BusNameStamp implements StampInterface
{
    private string $busName;

    public function __construct(string $busName)
    {
        $this->busName = $busName;
    }

    public function getBusName(): string
    {
        return $this->busName;
    }
}
