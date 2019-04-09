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

use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * A helper providing autocompletion for available options.
 *
 * @see HttpClientInterface for a description of each options.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @experimental in 4.3
 */
class HttpOptions
{
    private $options = [];

    public function toArray(): array
    {
        return $this->options;
    }

    /**
     * @return $this
     */
    public function setAuthBasic(string $user, string $password = '')
    {
        $this->options['auth_basic'] = $user;

        if ('' !== $password) {
            $this->options['auth_basic'] .= ':'.$password;
        }

        return $this;
    }

    /**
     * @return $this
     */
    public function setAuthBearer(string $token)
    {
        $this->options['auth_bearer'] = $token;

        return $this;
    }

    /**
     * @return $this
     */
    public function setQuery(array $query)
    {
        $this->options['query'] = $query;

        return $this;
    }

    /**
     * @return $this
     */
    public function setHeaders(iterable $headers)
    {
        $this->options['headers'] = $headers;

        return $this;
    }

    /**
     * @param array|string|resource|\Traversable|\Closure $body
     *
     * @return $this
     */
    public function setBody($body)
    {
        $this->options['body'] = $body;

        return $this;
    }

    /**
     * @param array|\JsonSerializable $json
     *
     * @return $this
     */
    public function setJson($json)
    {
        $this->options['json'] = $json;

        return $this;
    }

    /**
     * @return $this
     */
    public function setUserData($data)
    {
        $this->options['user_data'] = $data;

        return $this;
    }

    /**
     * @return $this
     */
    public function setMaxRedirects(int $max)
    {
        $this->options['max_redirects'] = $max;

        return $this;
    }

    /**
     * @return $this
     */
    public function setHttpVersion(string $version)
    {
        $this->options['http_version'] = $version;

        return $this;
    }

    /**
     * @return $this
     */
    public function setBaseUri(string $uri)
    {
        $this->options['base_uri'] = $uri;

        return $this;
    }

    /**
     * @return $this
     */
    public function buffer(bool $buffer)
    {
        $this->options['buffer'] = $buffer;

        return $this;
    }

    /**
     * @return $this
     */
    public function setOnProgress(callable $callback)
    {
        $this->options['on_progress'] = $callback;

        return $this;
    }

    /**
     * @return $this
     */
    public function resolve(array $hostIps)
    {
        $this->options['resolve'] = $hostIps;

        return $this;
    }

    /**
     * @return $this
     */
    public function setProxy(string $proxy)
    {
        $this->options['proxy'] = $proxy;

        return $this;
    }

    /**
     * @return $this
     */
    public function setNoProxy(string $noProxy)
    {
        $this->options['no_proxy'] = $noProxy;

        return $this;
    }

    /**
     * @return $this
     */
    public function setTimeout(float $timeout)
    {
        $this->options['timeout'] = $timeout;

        return $this;
    }

    /**
     * @return $this
     */
    public function bindTo(string $bindto)
    {
        $this->options['bindto'] = $bindto;

        return $this;
    }

    /**
     * @return $this
     */
    public function verifyPeer(bool $verify)
    {
        $this->options['verify_peer'] = $verify;

        return $this;
    }

    /**
     * @return $this
     */
    public function verifyHost(bool $verify)
    {
        $this->options['verify_host'] = $verify;

        return $this;
    }

    /**
     * @return $this
     */
    public function setCaFile(string $cafile)
    {
        $this->options['cafile'] = $cafile;

        return $this;
    }

    /**
     * @return $this
     */
    public function setCaPath(string $capath)
    {
        $this->options['capath'] = $capath;

        return $this;
    }

    /**
     * @return $this
     */
    public function setLocalCert(string $cert)
    {
        $this->options['local_cert'] = $cert;

        return $this;
    }

    /**
     * @return $this
     */
    public function setLocalPk(string $pk)
    {
        $this->options['local_pk'] = $pk;

        return $this;
    }

    /**
     * @return $this
     */
    public function setPassphrase(string $passphrase)
    {
        $this->options['passphrase'] = $passphrase;

        return $this;
    }

    /**
     * @return $this
     */
    public function setCiphers(string $ciphers)
    {
        $this->options['ciphers'] = $ciphers;

        return $this;
    }

    /**
     * @param string|array $fingerprint
     *
     * @return $this
     */
    public function setPeerFingerprint($fingerprint)
    {
        $this->options['peer_fingerprint'] = $fingerprint;

        return $this;
    }

    /**
     * @return $this
     */
    public function capturePeerCertChain(bool $capture)
    {
        $this->options['capture_peer_cert_chain'] = $capture;

        return $this;
    }

    /**
     * @return $this
     */
    public function setExtra(string $name, $value)
    {
        $this->options['extra'][$name] = $value;

        return $this;
    }
}
