<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpClient\ResponseRecorder;

use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\ResponseRecorderInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Saves responses in memory. Responses will be lost at the end of the PHP process.
 *
 * @author Gary PEGEOT <garypegeot@gmail.com>
 */
class InMemoryRecorder implements ResponseRecorderInterface, ResetInterface
{
    /**
     * @var ResponseInterface[]
     */
    private $responses = [];

    public function record(string $name, ResponseInterface $response): void
    {
        $this->responses[$name] = $response;
    }

    public function replay(string $name): ?ResponseInterface
    {
        return $this->responses[$name] ?? null;
    }

    public function reset()
    {
        $this->responses = [];
    }
}
