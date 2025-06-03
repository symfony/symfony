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
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Kernel;

class DefaultKernel extends Kernel
{
    use MicroKernelTrait;

    public function __invoke(): Response
    {
        return new Response('OK');
    }

    private string $cacheDir;

    public function getCacheDir(): string
    {
        return $this->cacheDir ??= sys_get_temp_dir().'/sf_default_kernel';
    }

    public function getLogDir(): string
    {
        return $this->cacheDir;
    }

    public function getProjectDir(): string
    {
        return \dirname(__DIR__);
    }
}
