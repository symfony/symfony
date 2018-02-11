<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\TwigBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\Debug\AutowiringInfoProviderInterface;
use Symfony\Component\DependencyInjection\Debug\AutowiringTypeInfo;
use Twig\Environment;

/**
 * @author Ryan Weaver <ryan@knpuniversity.com>
 */
final class TwigAutowiringInfoProvider implements AutowiringInfoProviderInterface
{
    public function getTypeInfos(): array
    {
        return array(
            AutowiringTypeInfo::create(Environment::class, 'Twig Templating')
                ->setDescription('use to render templates'),
        );
    }
}
