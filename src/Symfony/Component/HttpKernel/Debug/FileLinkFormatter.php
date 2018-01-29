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

use Symfony\Component\HttpFoundation\RequestStack;
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
    private $urlGenerator;
    private $baseDir;
    private $queryString;

    /**
     * @param $urlGenerator UrlGeneratorInterface
     */
    public function __construct($fileLinkFormat = null, $urlGenerator = null, $baseDir = null, $queryString = null)
    {
        $fileLinkFormat = $fileLinkFormat ?: ini_get('xdebug.file_link_format') ?: get_cfg_var('xdebug.file_link_format');
        if ($fileLinkFormat && !is_array($fileLinkFormat)) {
            $i = strpos($f = $fileLinkFormat, '&', max(strrpos($f, '%f'), strrpos($f, '%l'))) ?: strlen($f);
            $fileLinkFormat = array(substr($f, 0, $i)) + preg_split('/&([^>]++)>/', substr($f, $i), -1, PREG_SPLIT_DELIM_CAPTURE);
        }

        $this->fileLinkFormat = $fileLinkFormat;
        $this->baseDir = $baseDir;
        $this->queryString = $queryString;

        if ($urlGenerator instanceof RequestStack) {
            @trigger_error(sprintf('Passing a RequestStack to %s() as a second argument is deprecated since version 3.4 and will be unsupported in 4.0. Pass a UrlGeneratorInterface instead.', __METHOD__), E_USER_DEPRECATED);
            $this->requestStack = $urlGenerator;
        } elseif ($urlGenerator instanceof UrlGeneratorInterface) {
            $this->urlGenerator = $urlGenerator;
        } elseif (null !== $urlGenerator) {
            throw new \InvalidArgumentException('The second argument of %s() must either implement UrlGeneratorInterface or RequestStack.');
        }
    }

    public function format($file, $line)
    {
        if ($fmt = $this->getFileLinkFormat()) {
            for ($i = 1; isset($fmt[$i]); ++$i) {
                if (0 === strpos($file, $k = $fmt[$i++])) {
                    $file = substr_replace($file, $fmt[$i], 0, strlen($k));
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

    private function getFileLinkFormat()
    {
        if ($this->fileLinkFormat) {
            return $this->fileLinkFormat;
        }

        if (null !== $this->urlGenerator) {
            return array(
                $this->urlGenerator->generate('_profiler_open_file').$this->queryString,
                $this->baseDir.DIRECTORY_SEPARATOR, '',
            );
        }

        if (null !== $this->requestStack) {
            $request = $this->requestStack->getMasterRequest();
            if ($request instanceof Request) {
                return array(
                    $request->getSchemeAndHttpHost().$request->getBaseUrl().$this->urlFormat,
                    $this->baseDir.DIRECTORY_SEPARATOR, '',
                );
            }
        }
    }
}
