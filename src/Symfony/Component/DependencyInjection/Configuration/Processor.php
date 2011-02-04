<?php

namespace Symfony\Component\DependencyInjection\Configuration;

use Symfony\Component\DependencyInjection\Extension\Extension;

/**
 * This class is the entry point for config normalization/merging/finalization.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class Processor
{
    public function process(NodeInterface $configTree, array $configs)
    {
        $configs = Extension::normalizeKeys($configs);

        $currentConfig = array();
        foreach ($configs as $config) {
            $config = $configTree->normalize($config);
            $currentConfig = $configTree->merge($currentConfig, $config);
        }

        return $configTree->finalize($currentConfig);
    }
}