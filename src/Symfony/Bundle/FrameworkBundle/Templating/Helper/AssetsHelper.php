<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Templating\Helper;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Templating\Helper\AssetsHelper as BaseAssetsHelper;

/**
 * AssetsHelper is the base class for all helper classes that manages assets.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class AssetsHelper extends BaseAssetsHelper
{
    /**
     * Constructor.
     *
     * @param Request      $request  A Request instance
     * @param string|array $baseURLs The domain URL or an array of domain URLs
     * @param string       $version  The version
     * @param array        $packages Asset packages indexed by name
     */
    public function __construct(Request $request, $baseURLs = array(), $version = null, $packages = array())
    {
        parent::__construct($request->getBasePath(), $baseURLs, $version, $packages);
    }
}
