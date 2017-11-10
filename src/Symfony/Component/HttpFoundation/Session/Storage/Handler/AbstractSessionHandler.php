<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\Session\Storage\Handler;

/**
 * This abstract session handler provides a generic implementation
 * of the PHP 7.0 SessionUpdateTimestampHandlerInterface,
 * enabling strict and lazy session handling.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
abstract class AbstractSessionHandler implements \SessionHandlerInterface, \SessionUpdateTimestampHandlerInterface
{
    private $sessionName;
    private $prefetchId;
    private $prefetchData;
    private $newSessionId;
    private $igbinaryEmptyData;

    /**
     * {@inheritdoc}
     */
    public function open($savePath, $sessionName)
    {
        $this->sessionName = $sessionName;
        if (!headers_sent() && !ini_get('session.cache_limiter') && '0' !== ini_get('session.cache_limiter')) {
            header(sprintf('Cache-Control: max-age=%d, private, must-revalidate', 60 * (int) ini_get('session.cache_expire')));
        }

        return true;
    }

    /**
     * @param string $sessionId
     *
     * @return string
     */
    abstract protected function doRead($sessionId);

    /**
     * @param string $sessionId
     * @param string $data
     *
     * @return bool
     */
    abstract protected function doWrite($sessionId, $data);

    /**
     * @param string $sessionId
     *
     * @return bool
     */
    abstract protected function doDestroy($sessionId);

    /**
     * {@inheritdoc}
     */
    public function validateId($sessionId)
    {
        $this->prefetchData = $this->read($sessionId);
        $this->prefetchId = $sessionId;

        return '' !== $this->prefetchData;
    }

    /**
     * {@inheritdoc}
     */
    public function read($sessionId)
    {
        if (null !== $this->prefetchId) {
            $prefetchId = $this->prefetchId;
            $prefetchData = $this->prefetchData;
            $this->prefetchId = $this->prefetchData = null;

            if ($prefetchId === $sessionId || '' === $prefetchData) {
                $this->newSessionId = '' === $prefetchData ? $sessionId : null;

                return $prefetchData;
            }
        }

        $data = $this->doRead($sessionId);
        $this->newSessionId = '' === $data ? $sessionId : null;
        if (\PHP_VERSION_ID < 70000) {
            $this->prefetchData = $data;
        }

        return $data;
    }

    /**
     * {@inheritdoc}
     */
    public function write($sessionId, $data)
    {
        if (\PHP_VERSION_ID < 70000 && $this->prefetchData) {
            $readData = $this->prefetchData;
            $this->prefetchData = null;

            if ($readData === $data) {
                return $this->updateTimestamp($sessionId, $data);
            }
        }
        if (null === $this->igbinaryEmptyData) {
            // see https://github.com/igbinary/igbinary/issues/146
            $this->igbinaryEmptyData = \function_exists('igbinary_serialize') ? igbinary_serialize(array()) : '';
        }
        if ('' === $data || $this->igbinaryEmptyData === $data) {
            return $this->destroy($sessionId);
        }
        $this->newSessionId = null;

        return $this->doWrite($sessionId, $data);
    }

    /**
     * {@inheritdoc}
     */
    public function destroy($sessionId)
    {
        if (\PHP_VERSION_ID < 70000) {
            $this->prefetchData = null;
        }
        if (!headers_sent() && ini_get('session.use_cookies')) {
            if (!$this->sessionName) {
                throw new \LogicException(sprintf('Session name cannot be empty, did you forget to call "parent::open()" in "%s"?.', get_class($this)));
            }
            $sessionCookie = sprintf(' %s=', urlencode($this->sessionName));
            $sessionCookieWithId = sprintf('%s%s;', $sessionCookie, urlencode($sessionId));
            $sessionCookieFound = false;
            $otherCookies = array();
            foreach (headers_list() as $h) {
                if (0 !== stripos($h, 'Set-Cookie:')) {
                    continue;
                }
                if (11 === strpos($h, $sessionCookie, 11)) {
                    $sessionCookieFound = true;

                    if (11 !== strpos($h, $sessionCookieWithId, 11)) {
                        $otherCookies[] = $h;
                    }
                } else {
                    $otherCookies[] = $h;
                }
            }
            if ($sessionCookieFound) {
                header_remove('Set-Cookie');
                foreach ($otherCookies as $h) {
                    header('Set-Cookie:'.$h, false);
                }
            } else {
                setcookie($this->sessionName, '', 0, ini_get('session.cookie_path'), ini_get('session.cookie_domain'), ini_get('session.cookie_secure'), ini_get('session.cookie_httponly'));
            }
        }

        return $this->newSessionId === $sessionId || $this->doDestroy($sessionId);
    }
}
