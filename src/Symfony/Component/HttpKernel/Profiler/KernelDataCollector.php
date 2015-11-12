<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Profiler;

use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Profiler\DataCollector\ConfigDataCollector;

/**
 * KernelDataCollector.
 *
 * @author Jelte Steijaert <jelte@khepri.be>
 */
class KernelDataCollector extends ConfigDataCollector
{
    /**
     * @var KernelInterface
     */
    private $kernel;

    /**
     * Constructor.
     *
     * @param KernelInterface $kernel A KernelInterface instance
     */
    public function __construct(KernelInterface $kernel)
    {
        $this->kernel = $kernel;
    }

    /**
     * {@inheritdoc}
     */
    public function getCollectedData()
    {
        return new KernelData($this->kernel, $this->doCollect());
    }
}
