<?php

namespace Symfony\Component\Ldap\Tests;

use PHPUnit\Framework\TestCase;

class LdapTestCase extends TestCase
{
    protected function getLdapConfig()
    {
        $h = @ldap_connect(getenv('LDAP_HOST'), getenv('LDAP_PORT'));

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
