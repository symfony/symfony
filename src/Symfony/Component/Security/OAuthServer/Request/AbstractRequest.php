<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\OAuthServer\Request;

use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\OAuthServer\Bridge\Psr7Trait;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
abstract class AbstractRequest
{
    use Psr7Trait;

    protected $options = [];

    /**
     * @param object|null $request
     */
    private function __construct($request = null)
    {
        if (null === $request) {
            self::createFromGlobals();
        }

        if ($request instanceof Request) {
            self::createFromRequest($request);
        }

        if ($request instanceof ServerRequestInterface) {
            self::createFromPsr7Request($request);
        }
    }

    public static function create($request = null): self
    {
        return new static($request);
    }

    private function createFromRequest(Request $request)
    {
        $this->options['type'] = 'http_foundation';
        $this->options['GET'] = $request->query->all();
        $this->options['POST'] = $request->request->all();
        $this->options['SERVER'] = $request->server->all();
    }

    private function createFromGlobals()
    {
        $this->options['type'] = 'globals';
        $this->options['GET'] = $_GET;
        $this->options['POST'] = $_POST;
        $this->options['SERVER'] = $_SERVER;
    }

    public function getValue($key, $default = null)
    {
        if (\array_key_exists($key, $this->options)) {
            return $this->options[$key];
        }

        if (\array_key_exists($key, $this->options['GET'])) {
            return $this->options['GET'][$key];
        }

        if (\array_key_exists($key, $this->options['POST'])) {
            return $this->options['POST'][$key];
        }

        if (\array_key_exists($key, $this->options['SERVER'])) {
            return $this->options['SERVER'][$key];
        }

        return $default;
    }

    /**
     * Return an array which contains the request main informations,
     * this method is mainly used during the last request event in order to compare
     * both request & response.
     *
     * @return array
     */
    abstract public function returnAsReadOnly(): array;
}
