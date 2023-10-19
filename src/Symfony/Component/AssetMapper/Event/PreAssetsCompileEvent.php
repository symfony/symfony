<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\AssetMapper\Event;

use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched during the asset-map:compile command, before the assets are compiled.
 *
 * @author Ryan Weaver <ryan@symfonycasts.com>
 */
class PreAssetsCompileEvent extends Event
{
    private string $outputDir;
    private OutputInterface $output;

    public function __construct(string $outputDir, OutputInterface $output)
    {
        $this->outputDir = $outputDir;
        $this->output = $output;
    }

    public function getOutputDir(): string
    {
        return $this->outputDir;
    }

    public function getOutput(): OutputInterface
    {
        return $this->output;
    }
}
