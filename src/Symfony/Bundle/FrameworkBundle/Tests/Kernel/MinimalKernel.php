<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Kernel;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel;

abstract class MinimalKernel extends Kernel
{
    use MicroKernelTrait;

    private string $cacheDir;

    public function __construct(string $cacheDir)
    {
        parent::__construct('test', false);

        $this->cacheDir = sys_get_temp_dir().'/'.$cacheDir;
    }

    public function getCacheDir(): string
    {
        return $this->cacheDir;
    }

    public function getLogDir(): string
    {
        return $this->cacheDir;
    }
}
