<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Decorator;

use Symfony\Component\Decorator\Attribute\DecoratorAttribute;

/**
 * Wraps persistence method operations within a single Doctrine transaction.
 *
 * @author Yonel Ceruto <open@yceruto.dev>
 */
#[\Attribute(\Attribute::TARGET_METHOD)]
class Transactional extends DecoratorAttribute
{
    /**
     * @param string|null $name The entity manager name (null for the default one)
     */
    public function __construct(
        public ?string $name = null,
    ) {
    }
}
