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

use Symfony\Component\DependencyInjection\Attribute\AutowireClassMap;

class ClassMapConsumer
{
    public function __construct(
        #[AutowireClassMap(
            namespace: 'Symfony\Component\DependencyInjection\Tests\Fixtures\ClassMap\Valid',
            path: '%fixtures_dir%/ClassMap/Valid',
        )]
        public array $classMap,
    ) {
    }
}
