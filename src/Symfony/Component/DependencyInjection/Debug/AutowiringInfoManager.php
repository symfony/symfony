<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Debug;

/**
 * @author Ryan Weaver <ryan@knpuniversity.com>
 */
final class AutowiringInfoManager
{
    /**
     * @var AutowiringInfoProviderInterface[]
     */
    private $autowiringInfoProviders;

    private $typeInfos = null;

    public function __construct(array $autowiringInfoProviders)
    {
        $this->autowiringInfoProviders = $autowiringInfoProviders;
    }

    /**
     * @param string $type
     *
     * @return AutowiringTypeInfo|null
     */
    public function getInfo(string $type)
    {
        if (null === $this->typeInfos) {
            $this->populateTypesInfo();
        }

        return isset($this->typeInfos[$type]) ? $this->typeInfos[$type] : null;
    }

    private function populateTypesInfo()
    {
        $typeInfos = array();
        foreach ($this->autowiringInfoProviders as $provider) {
            foreach ($provider->getTypeInfos() as $typeInfo) {
                $typeInfos[$typeInfo->getType()] = $typeInfo;
            }
        }

        $this->typeInfos = $typeInfos;
    }
}
