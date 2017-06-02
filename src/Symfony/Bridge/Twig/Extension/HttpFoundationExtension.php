<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Twig\Extension;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RequestContext;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Twig extension for the Symfony HttpFoundation component.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class HttpFoundationExtension extends AbstractExtension
{
    private $requestStack;
    private $requestContext;

    public function __construct(RequestStack $requestStack, RequestContext $requestContext = null)
    {
        $this->requestStack = $requestStack;
        $this->requestContext = $requestContext;
    }

    /**
     * {@inheritdoc}
     */
    public function getFunctions()
    {
        return array(
            new TwigFunction('absolute_url', array($this, 'generateAbsoluteUrl')),
            new TwigFunction('relative_path', array($this, 'generateRelativePath')),
        );
    }

    /**
     * Returns the absolute URL for the given absolute or relative path.
     *
     * This method returns the path unchanged if no request is available.
     *
     * @param string $path The path
     *
     * @return string The absolute URL
     *
     * @see Request::getUriForPath()
     */
    public function generateAbsoluteUrl($path)
    {
        if (false !== strpos($path, '://') || '//' === substr($path, 0, 2)) {
            return $path;
        }

        if (!$request = $this->requestStack->getMasterRequest()) {
            if (null !== $this->requestContext && '' !== $host = $this->requestContext->getHost()) {
                $scheme = $this->requestContext->getScheme();
                $port = '';

                if ('http' === $scheme && 80 != $this->requestContext->getHttpPort()) {
                    $port = ':'.$this->requestContext->getHttpPort();
                } elseif ('https' === $scheme && 443 != $this->requestContext->getHttpsPort()) {
                    $port = ':'.$this->requestContext->getHttpsPort();
                }

                if ('/' !== $path[0]) {
                    $path = rtrim($this->requestContext->getBaseUrl(), '/').'/'.$path;
                }

                return $scheme.'://'.$host.$port.$path;
            }

            return $path;
        }

        if (!$path || '/' !== $path[0]) {
            $prefix = $request->getPathInfo();
            $last = strlen($prefix) - 1;
            if ($last !== $pos = strrpos($prefix, '/')) {
                $prefix = substr($prefix, 0, $pos).'/';
            }

            return $request->getUriForPath($prefix.$path);
        }

        return $request->getSchemeAndHttpHost().$path;
    }

    /**
     * Returns a relative path based on the current Request.
     *
     * This method returns the path unchanged if no request is available.
     *
     * @param string $path The path
     *
     * @return string The relative path
     *
     * @see Request::getRelativeUriForPath()
     */
    public function generateRelativePath($path)
    {
        if (false !== strpos($path, '://') || '//' === substr($path, 0, 2)) {
            return $path;
        }

        if (!$request = $this->requestStack->getMasterRequest()) {
            return $path;
        }

        return $request->getRelativeUriForPath($path);
    }

    /**
     * Returns the name of the extension.
     *
     * @return string The extension name
     */
    public function getName()
    {
        return 'request';
    }
}
