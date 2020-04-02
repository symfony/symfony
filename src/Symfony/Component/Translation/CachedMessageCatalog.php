<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation;

/**
 * @author Jannik Zschiesche <hi@jannik.io>
 */
class CachedMessageCatalog extends MessageCatalogue
{
    private $cachedResourcesGenerator;
    private $cachedResourcesLoaded = [];

    /**
     * @internal
     */
    public function setCachedResources(callable $resourcesGenerator): void
    {
        $this->cachedResourcesGenerator = $resourcesGenerator;
    }

    /**
     * {@inheritdoc}
     */
    public function getResources()
    {
        if (!$this->cachedResourcesLoaded) {
            if ($this->cachedResourcesGenerator) {
                foreach (($this->cachedResourcesGenerator)() as $resource) {
                    parent::addResource($resource);
                }
            }

            $this->cachedResourcesLoaded = true;
        }

        return parent::getResources();
    }
}
