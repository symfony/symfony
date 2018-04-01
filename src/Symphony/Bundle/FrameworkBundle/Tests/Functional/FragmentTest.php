<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Bundle\FrameworkBundle\Tests\Functional;

class FragmentTest extends WebTestCase
{
    /**
     * @dataProvider getConfigs
     */
    public function testFragment($insulate)
    {
        $client = $this->createClient(array('test_case' => 'Fragment', 'root_config' => 'config.yml'));
        if ($insulate) {
            $client->insulate();
        }

        $client->request('GET', '/fragment_home');

        $this->assertEquals('bar txt--html--es--fr', $client->getResponse()->getContent());
    }

    public function getConfigs()
    {
        return array(
            array(false),
            array(true),
        );
    }
}
