<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\Tests\Functional;

class CamelCasedProvidersCausesExceptionsTest extends WebTestCase
{
    public function testBugfixExceptionThenCamelCasedProviderIsGiven()
    {
        $client = $this->createClient(array('test_case' => 'CamelCasedProviders', 'root_config' => 'config.yml'));
    }
}
