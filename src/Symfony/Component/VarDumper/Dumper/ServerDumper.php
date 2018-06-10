<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\VarDumper\Dumper;

use Symfony\Component\VarDumper\Cloner\Data;
use Symfony\Component\VarDumper\Dumper\ContextProvider\ContextProviderInterface;

/**
 * ServerDumper forwards serialized Data clones to a server.
 *
 * @author Maxime Steinhausser <maxime.steinhausser@gmail.com>
 */
class ServerDumper implements DataDumperInterface
{
    private $host;
    private $wrappedDumper;
    private $contextProviders;
    private $socket;

    /**
     * @param string                     $host             The server host
     * @param DataDumperInterface|null   $wrappedDumper    A wrapped instance used whenever we failed contacting the server
     * @param ContextProviderInterface[] $contextProviders Context providers indexed by context name
     */
    public function __construct(string $host, DataDumperInterface $wrappedDumper = null, array $contextProviders = array())
    {
        if (false === strpos($host, '://')) {
            $host = 'tcp://'.$host;
        }

        $this->host = $host;
        $this->wrappedDumper = $wrappedDumper;
        $this->contextProviders = $contextProviders;
    }

    public function getContextProviders(): array
    {
        return $this->contextProviders;
    }

    /**
     * {@inheritdoc}
     */
    public function dump(Data $data, $output = null): void
    {
        set_error_handler(array(self::class, 'nullErrorHandler'));

        $failed = false;
        try {
            if (!$this->socket = $this->socket ?: $this->createSocket()) {
                $failed = true;

                return;
            }
        } finally {
            restore_error_handler();
            if ($failed && $this->wrappedDumper) {
                $this->wrappedDumper->dump($data);
            }
        }

        set_error_handler(array(self::class, 'nullErrorHandler'));

        $context = array('timestamp' => time());
        foreach ($this->contextProviders as $name => $provider) {
            $context[$name] = $provider->getContext();
        }
        $context = array_filter($context);

        $encodedPayload = base64_encode(serialize(array($data, $context)))."\n";
        $failed = false;

        try {
            $retry = 3;
            while ($retry > 0 && $failed = (-1 === stream_socket_sendto($this->socket, $encodedPayload))) {
                stream_socket_shutdown($this->socket, STREAM_SHUT_RDWR);
                if ($failed = !$this->socket = $this->createSocket()) {
                    break;
                }

                --$retry;
            }
        } finally {
            restore_error_handler();
            if ($failed && $this->wrappedDumper) {
                $this->wrappedDumper->dump($data);
            }
        }
    }

    private static function nullErrorHandler()
    {
        // noop
    }

    private function createSocket()
    {
        $socket = stream_socket_client($this->host, $errno, $errstr, 1, STREAM_CLIENT_CONNECT | STREAM_CLIENT_PERSISTENT);

        if ($socket) {
            stream_set_blocking($socket, false);
        }

        return $socket;
    }
}
