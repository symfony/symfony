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
use Symfony\Component\Templating\Helper\CoreAssetsHelper;

/**
 * The static "assetic" templating helper.
 *
 * @author Kris Wallsmith <kris@symfony.com>
 */
class StaticAsseticHelper extends AsseticHelper
{
    private $assetsHelper;

    /**
     * Constructor.
     *
     * @param CoreAssetsHelper $assetsHelper The assets helper
     * @param AssetFactory     $factory      The asset factory
     */
    public function __construct(CoreAssetsHelper $assetsHelper, AssetFactory $factory)
    {
        $this->assetsHelper = $assetsHelper;

        parent::__construct($factory);
    }

    protected function getAssetUrl(AssetInterface $asset, $options = array())
    {
        return $this->assetsHelper->getUrl($asset->getTargetPath(), isset($options['package']) ? $options['package'] : null);
    }
}
