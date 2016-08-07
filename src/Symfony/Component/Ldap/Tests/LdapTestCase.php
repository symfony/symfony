<?php

namespace Symfony\Component\Ldap\Tests;

class LdapTestCase extends \PHPUnit_Framework_TestCase
{
    protected function getLdapConfig()
    {
        return array(
            'host' => getenv('LDAP_HOST'),
            'port' => getenv('LDAP_PORT'),
        );
    }
}
