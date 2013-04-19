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
        return null;
    }
}