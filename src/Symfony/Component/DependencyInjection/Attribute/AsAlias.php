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
 * An attribute to tell under which alias a service should be registered or to use the implemented interface if no parameter is given.
 *
 * @author Alan Poulain <contact@alanpoulain.eu>
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
final class AsAlias
{
    /**
     * @var list<string>
     */
    public array $when = [];

    /**
     * @param string|null         $id     The id of the alias
     * @param bool                $public Whether to declare the alias public
     * @param string|list<string> $when   The environments under which the class will be registered as a service (i.e. "dev", "test", "prod")
     */
    public function __construct(
        public ?string $id = null,
        public bool $public = false,
        string|array $when = [],
    ) {
        $this->when = (array) $when;
    }
}
