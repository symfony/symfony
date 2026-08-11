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
 * An attribute to register this class as a service only when no service or alias with the given id exists.
 *
 * This makes the service an overridable default: any definition of the given id
 * coming from the app or from any bundle wins over the annotated class.
 *
 * @author Javier Eguiluz <javier.eguiluz@gmail.com>
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
class WhenMissingService
{
    /**
     * @param string $id The service id (or alias) whose absence enables this service
     */
    public function __construct(public string $id)
    {
    }
}
