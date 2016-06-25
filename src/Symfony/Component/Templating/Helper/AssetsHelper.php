<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Templating\Helper;

@trigger_error('The Symfony\Component\Templating\Helper\AssetsHelper is deprecated since version 2.7 and will be removed in 3.0. Use the Asset component instead.', E_USER_DEPRECATED);

use Symfony\Component\Templating\Asset\PathPackage;
use Symfony\Component\Templating\Asset\UrlPackage;

/**
 * AssetsHelper helps manage asset URLs.
 *
 * Usage:
 *
 * <code>
 *   <img src="<?php echo $view['assets']->getUrl('foo.png') ?>" />
 * </code>
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Kris Wallsmith <kris@symfony.com>
 *
 * @deprecated since 2.7, will be removed in 3.0. Use the Asset component instead.
 */
class AssetsHelper extends CoreAssetsHelper
{
    /**
     * Constructor.
     *
     * @param string       $basePath      The base path
     * @param string|array $baseUrls      Base asset URLs
     * @param string       $version       The asset version
     * @param string       $format        The version format
     * @param array        $namedPackages Additional packages
     */
    public function __construct($basePath = null, $baseUrls = array(), $version = null, $format = null, $namedPackages = array())
    {
        if ($baseUrls) {
            $defaultPackage = new UrlPackage($baseUrls, $version, $format);
        } else {
            $defaultPackage = new PathPackage($basePath, $version, $format);
        }

        parent::__construct($defaultPackage, $namedPackages);
    }
}
