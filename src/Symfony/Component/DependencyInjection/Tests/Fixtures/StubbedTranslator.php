<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Tests\Fixtures;

use Psr\Container\ContainerInterface;

/**

 */
class StubbedTranslator
{
    public function __construct(ContainerInterface $container)
    {
    }

    public function addResource($format, $resource, $locale, $domain = null)
    {
    }
}
