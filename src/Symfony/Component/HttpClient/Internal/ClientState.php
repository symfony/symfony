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
 * Internal representation of the client state.
 *
 * @internal
 */
class ClientState
{
    public $handlesActivity = [];
    public $openHandles = [];
}
