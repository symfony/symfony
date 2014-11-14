<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Fixtures;

use Symfony\Component\Console\CommandRegistryInterface;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;

/**
 * Class CommandRegistryBundle
 *
 * @author  Yannick Voyer (http://github.com/yvoyer)
 */
interface CommandRegistryBundle extends BundleInterface, CommandRegistryInterface
{
}
 