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

@trigger_error(sprintf('The "%s" class is deprecated since Symfony 4.4.', BusNameStamp::class), E_USER_DEPRECATED);

/**
 * Stamp used to identify which bus it was passed to.
 *
 * @deprecated since Symfony 4.4
 *
 * @author Ryan Weaver <ryan@symfonycasts.com>
 */
final class BusNameStamp implements StampInterface
{
    private $busName;

    public function __construct(string $busName)
    {
        $this->busName = $busName;
    }

    public function getBusName(): string
    {
        return $this->busName;
    }
}
