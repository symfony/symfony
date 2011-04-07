<?php

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Symfony\Bundle\AsseticBundle\Templating;

use Assetic\Asset\AssetInterface;
use Assetic\Factory\AssetFactory;
use Symfony\Component\Templating\Helper\AssetsHelper;

/**
 * The static "assetic" templating helper.
 *
 * @author Kris Wallsmith <kris.wallsmith@symfony.com>
 */
class StaticAsseticHelper extends AsseticHelper
{
    private $assetsHelper;

    /**
     * Constructor.
     *
     * @param AssetsHelper $assetsHelper The assets helper
     * @param AssetFactory $factory      The asset factory
     * @param Boolean      $debug        The debug mode
     */
    public function __construct(AssetsHelper $assetsHelper, AssetFactory $factory, $debug = false)
    {
        $this->assetsHelper = $assetsHelper;

        parent::__construct($factory, $debug);
    }

    protected function getAssetUrl(AssetInterface $asset, $options = array())
    {
        return $this->assetsHelper->getUrl($asset->getTargetUrl(), isset($options['package']) ? $options['package'] : null);
    }
}
