<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Debug;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Exception\ExceptionInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Formats debug file links.
 *
 * @author Jérémy Romey <jeremy@free-agent.fr>
 */
class FileLinkFormatter implements \Serializable
{
    private $fileLinkFormat;
    private $requestStack;
    private $baseDir;
    private $urlFormat;

    /**
     * @param string|\Closure $urlFormat the URL format, or a closure that returns it on-demand
     */
    public function __construct($fileLinkFormat = null, RequestStack $requestStack = null, $baseDir = null, $urlFormat = null)
    {
        $fileLinkFormat = $fileLinkFormat ?: ini_get('xdebug.file_link_format') ?: get_cfg_var('xdebug.file_link_format');
        if ($fileLinkFormat && !\is_array($fileLinkFormat)) {
            $i = strpos($f = $fileLinkFormat, '&', max(strrpos($f, '%f'), strrpos($f, '%l'))) ?: \strlen($f);
            $fileLinkFormat = array(substr($f, 0, $i)) + preg_split('/&([^>]++)>/', substr($f, $i), -1, PREG_SPLIT_DELIM_CAPTURE);
        }

        $this->fileLinkFormat = $fileLinkFormat;
        $this->requestStack = $requestStack;
        $this->baseDir = $baseDir;
        $this->urlFormat = $urlFormat;
    }

    public function format($file, $line)
    {
        if ($fmt = $this->getFileLinkFormat()) {
            for ($i = 1; isset($fmt[$i]); ++$i) {
                if (0 === strpos($file, $k = $fmt[$i++])) {
                    $file = substr_replace($file, $fmt[$i], 0, \strlen($k));
                    break;
                }
            }

            return strtr($fmt[0], array('%f' => $file, '%l' => $line));
        }

        return false;
    }

    public function serialize()
    {
        return serialize($this->getFileLinkFormat());
    }

    public function unserialize($serialized)
    {
        if (\PHP_VERSION_ID >= 70000) {
            $this->fileLinkFormat = unserialize($serialized, array('allowed_classes' => false));
        } else {
            $this->fileLinkFormat = unserialize($serialized);
        }
    }

    /**
     * @internal
     */
    public static function generateUrlFormat(UrlGeneratorInterface $router, $routeName, $queryString)
    {
        try {
            return $router->generate($routeName).$queryString;
        } catch (ExceptionInterface $e) {
            return null;
        }
    }

    private function getFileLinkFormat()
    {
        if ($this->fileLinkFormat) {
            return $this->fileLinkFormat;
        }
        if ($this->requestStack && $this->baseDir && $this->urlFormat) {
            $request = $this->requestStack->getMasterRequest();
            if ($request instanceof Request) {
                if ($this->urlFormat instanceof \Closure && !$this->urlFormat = \call_user_func($this->urlFormat)) {
                    return;
                }

                return array(
                    $request->getSchemeAndHttpHost().$request->getBasePath().$this->urlFormat,
                    $this->baseDir.\DIRECTORY_SEPARATOR, '',
                );
            }
        }
    }
}
