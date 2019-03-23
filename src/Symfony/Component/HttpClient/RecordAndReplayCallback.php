<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpClient;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\ResponseNamingStrategyInterface;
use Symfony\Contracts\HttpClient\ResponseRecorderInterface;

/**
 * Provides a way to record & replay responses.
 *
 * @author Gary PEGEOT <garypegeot@gmail.com>
 */
class RecordAndReplayCallback
{
    const MODE_REPLAY = 'replay';
    const MODE_RECORD = 'record';
    const MODE_REPLAY_OR_RECORD = 'replay_or_record';

    /**
     * @var HttpClientInterface
     */
    private $client;

    /**
     * @var ResponseNamingStrategyInterface
     */
    private $strategy;

    /**
     * @var ResponseRecorderInterface
     */
    private $recorder;

    /**
     * @var string
     */
    private $mode;

    public function __construct(ResponseNamingStrategyInterface $strategy, ResponseRecorderInterface $recorder, string $mode, ?HttpClientInterface $client = null)
    {
        $this->strategy = $strategy;
        $this->recorder = $recorder;
        $this->setMode($mode);
        $this->client = $client ?? HttpClient::create();
    }

    public function __invoke(string $method, string $url, array $options): ResponseInterface
    {
        $response = null;
        $name = $this->strategy->name($method, $url, $options);

        if (static::MODE_RECORD !== $this->mode) {
            $response = $this->recorder->replay($name);
        }

        if (static::MODE_RECORD === $this->mode || (!$response && $this->mode === static::MODE_REPLAY_OR_RECORD)) {
            $response = $this->client->request($method, $url, $options);

            $this->recorder->record($name, $response);
        }

        if (null === $response) {
            throw new TransportException("Unable to retrieve the response \"$name\".");
        }

        return $response;
    }

    /**
     * @return $this
     */
    public function setMode(string $mode)
    {
        $modes = static::getModes();

        if (!\in_array($mode, $modes, true)) {
            throw new \InvalidArgumentException(sprintf('Invalid provided mode "%s", available choices are: %s', $mode, implode(', ', $modes)));
        }

        $this->mode = $mode;

        return $this;
    }

    public static function getModes(): array
    {
        $modes = [];
        $ref = new \ReflectionClass(__CLASS__);

        foreach ($ref->getConstants() as $constant => $value) {
            if (0 === strpos($constant, 'MODE_')) {
                $modes[] = $value;
            }
        }

        return $modes;
    }
}
