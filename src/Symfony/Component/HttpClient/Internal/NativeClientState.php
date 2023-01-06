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
 * Internal representation of the native client's state.
 *
 * @author Alexander M. Turek <me@derrabus.de>
 *
 * @internal
 */
final class NativeClientState extends ClientState
{
    public int $id;
    public int $maxHostConnections = \PHP_INT_MAX;
    public int $responseCount = 0;
    /** @var string[] */
    public array $dnsCache = [];
    public bool $sleep = false;
    /** @var int[] */
    public array $hosts = [];

    public function __construct()
    {
        $this->id = random_int(\PHP_INT_MIN, \PHP_INT_MAX);
    }

    public function reset()
    {
        $this->responseCount = 0;
        $this->dnsCache = [];
        $this->hosts = [];
    }
}
