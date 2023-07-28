<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Security;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\PhpUnit\ExpectDeprecationTrait;
use Symfony\Component\Ldap\Security\LdapBadge;

final class LdapBadgeTest extends TestCase
{
    use ExpectDeprecationTrait;

    /**
     * @group legacy
     */
    public function testDeprecationOnResolvedInitialValue()
    {
        $this->expectDeprecation('Since symfony/ldap 6.4: Passing "false" as resolved initial value is deprecated, use "true" instead.');

        new LdapBadge('foo');
    }

    /**
     * @group legacy
     */
    public function testDeprecationOnMarkAsResolved()
    {
        $this->expectDeprecation('Since symfony/ldap 6.4: Symfony\Component\Ldap\Security\LdapBadge::markResolved is deprecated and will be removed in 7.0. Symfony\Component\Ldap\Security\LdapBadge is intended to bear LDAP information and doesn\'t need to be resolved anymore.');

        $sut = new LdapBadge('foo', '{user_identifier}', '', '', null, true);
        $sut->markResolved();
    }
}
