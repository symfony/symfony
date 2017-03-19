<?php

namespace Symfony\Component\Ldap\Tests;

use PHPUnit\Framework\TestCase;

class LdapTestCase extends TestCase
{
    protected function getLdapConfig()
    {
        return array(
            'host' => getenv('LDAP_HOST'),
            'port' => getenv('LDAP_PORT'),
        );
    }
}
