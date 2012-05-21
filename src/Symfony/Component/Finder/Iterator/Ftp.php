<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Finder\Iterator;

/**
 * Ftp client using ftp extension.
 *
 * @author Włodzimierz Gajda <gajdaw@gajdaw.pl>
 *
 */
class Ftp
{
    private $ftpParameters  = array();
    private $ftpResource = null;

    public function __construct(array $parameters = array())
    {
        $this->setFtpParameters($parameters);
    }

    public function setFtpParameters(array $ftpParameters)
    {
        $this->ftpParameters = $ftpParameters;
    }

    public function getFtpParameters()
    {
        return $this->ftpParameters;
    }

    public function connectAndLogin()
    {
        if (!$this->isConnected()) {
            $ftpConnId  = ftp_connect($this->getFtpHost());
            $loginResult = ftp_login($ftpConnId, $this->getFtpUser(), $this->getFtpPass());

            if ((!$ftpConnId) || (!$loginResult)) {
                throw new \RuntimeException('Cannnot ftp_connect() or ftp_login()');
            } else {
                $this->ftpResource = $ftpConnId;
                ftp_pasv($this->ftpResource, true);
            }
        }
    }

    public function isConnected()
    {
        return is_resource($this->ftpResource);
    }

    public function getFtpResource()
    {
        return $this->ftpResource;
    }

    public function getFtpHost()
    {
        if (isset($this->ftpParameters['host'])) {
            return $this->ftpParameters['host'];
        } else {
            return '';
        }
    }

    public function setFtpHost($host)
    {
        $this->ftpParameters['host'] = $host;
    }

    public function getFtpUser()
    {
        if (isset($this->ftpParameters['user'])) {
            return $this->ftpParameters['user'];
        } else {
            return '';
        }
    }

    public function setFtpUser($user)
    {
        $this->ftpParameters['user'] = $user;
    }

    public function getFtpPass()
    {
        if (isset($this->ftpParameters['pass'])) {
            return $this->ftpParameters['pass'];
        } else {
            return '';
        }
    }

    public function setFtpPass($pass)
    {
        $this->ftpParameters['pass'] = $pass;
    }

    public static function isValidFtpUrl($url)
    {
        if (0 !== strpos($url, 'ftp://')) {
            return false;
        }
        $parsedUrl = parse_url($url);
        if ($parsedUrl['scheme'] === 'ftp') {
            return true;
        }

        return false;
    }

    public function chDir($dir)
    {
        ftp_chdir($this->getFtpResource(), $dir);
    }

    public function nList($dir)
    {
        return ftp_nlist($this->getFtpResource(), $dir);
    }

    public function rawList($dir)
    {
        return ftp_rawlist($this->getFtpResource(), $dir);
    }

}
