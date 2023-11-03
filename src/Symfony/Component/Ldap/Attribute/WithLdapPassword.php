<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Ldap\Attribute;

/**
 * Marker to allow or not using ldap credentials for a non-ldap user.
 *
 * @author Laurent VOULLEMIER <laurent.voullemier@gmail.com>
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class WithLdapPassword
{
    public function __construct(public readonly bool $enabled = true) {}
}
