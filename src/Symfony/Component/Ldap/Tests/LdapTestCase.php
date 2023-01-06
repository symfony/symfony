<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Ldap\Tests;

use PHPUnit\Framework\TestCase;

class LdapTestCase extends TestCase
{
    protected function getLdapConfig()
    {
        $h = @ldap_connect(getenv('LDAP_HOST'), getenv('LDAP_PORT'));
        @ldap_set_option($h, \LDAP_OPT_PROTOCOL_VERSION, 3);

        if (!$h || !@ldap_bind($h)) {
            $this->markTestSkipped('No server is listening on LDAP_HOST:LDAP_PORT');
        }

        ldap_unbind($h);

        return [
            'host' => getenv('LDAP_HOST'),
            'port' => getenv('LDAP_PORT'),
        ];
    }
}
