<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Functional;

class SubRequestsTest extends WebTestCase
{
    public function testStateAfterSubRequest()
    {
        $client = $this->createClient(array('test_case' => 'StateAfterSubRequest'));
        $client->request('GET', 'https://localhost/subrequest/en');

        $this->assertEquals('--fr/json--en/html--fr/json--http://localhost/subrequest/fragment/en', $client->getResponse()->getContent());
    }

    public function testSubRequestControllerServicesAreResolved()
    {
        $client = $this->createClient(array('test_case' => 'SubRequestControllerServicesAreResolved'));
        $client->request('GET', 'https://localhost/subrequest');

        $this->assertEquals('---', $client->getResponse()->getContent());
    }

    protected static function createKernel(array $options = array())
    {
        return parent::createKernel($options + array('config_dir' => __DIR__.'/app/SubRequests'));
    }
}
