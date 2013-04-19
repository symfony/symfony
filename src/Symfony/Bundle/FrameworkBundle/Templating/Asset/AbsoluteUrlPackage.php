<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Templating\Asset;

use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Templating\Asset\Package;

/**
 * This package attempts to return absolute URL for assets
 *
 * @author Roman Marintšenko <roman.marintsenko@knplabs.com>
 */
class AbsoluteUrlPackage extends Package
{
    private $context;
    
    /**
     * @param RequestContext $context Request context
     * @param string         $version The version
     * @param string         $format  The version format
     */
    public function __construct(RequestContext $context, $version = null, $format = null)
    {
        $this->context = $context;
        
        parent::__construct($version, $format);
    }
    
    public function getUrl($path)
    {
        if (false !== strpos($path, '://') || 0 === strpos($path, '//')) {
            return $path;
        }
        
        $url = ltrim($path, '/');
        if ($baseUrl = $this->context->getBaseUrl()) {
            $url = trim($baseUrl, '/').'/'.$url;
        }
        
        // get scheme, host and port from RequestContext
        // based on \Symfony\Component\Routing\Generator\UrlGenerator::doGenerate()
        $schemeAuthority = '';
        if ($host = $this->context->getHost()) {
            $scheme = $this->context->getScheme();
            
            $port = '';
            if ('http' === $scheme && 80 != $this->context->getHttpPort()) {
                $port = ':'.$this->context->getHttpPort();
            } elseif ('https' === $scheme && 443 != $this->context->getHttpsPort()) {
                $port = ':'.$this->context->getHttpsPort();
            }

            $schemeAuthority .= $scheme.'://'.$host.$port;
        }

        $url = $schemeAuthority.'/'.$url;
        
        $url = $this->applyVersion($url);
        
        return $url;
    }
}