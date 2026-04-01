<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Attribute;

/**
 * Declares that the annotated invokable class or method is a factory for another service.
 *
 * The service id to create is given via the $id parameter.
 * If $id is omitted, the return type of the factory method is used instead.
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD)]
class AsFactory
{
    /**
     * @param string $id the id of the service this factory creates; auto-detected from the return type when empty
     */
    public function __construct(
        public readonly string $id = '',
    ) {
    }
}
