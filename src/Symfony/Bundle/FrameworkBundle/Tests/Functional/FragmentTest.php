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

/**
 * @group functional
 */
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

        $this->assertEquals('bar txt', $client->getResponse()->getContent());
    }

    public function getConfigs()
    {
        return array(
            array(false),
            array(true),
        );
    }
}
