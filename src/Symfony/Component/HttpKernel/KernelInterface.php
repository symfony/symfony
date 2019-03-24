<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel;

use Symfony\Component\Kernel\KernelInterface as BaseKernelInterface;

/**
 * The HTTP Kernel is the heart of the Symfony system to handle HTTP request.
 *
 * It manages an environment made of application kernel and bundles.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @method string getProjectDir() Gets the project dir (path of the project's composer file) - not defining it is deprecated since Symfony 4.2
 */
interface KernelInterface extends BaseKernelInterface, HttpKernelInterface
{
}
