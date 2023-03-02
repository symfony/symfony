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

class FragmentTest extends AbstractWebTestCase
{
    /**
     * @dataProvider getConfigs
     */
    public function testFragment($insulate)
    {
        $client = $this->createClient(['test_case' => 'Fragment', 'root_config' => 'config.yml', 'debug' => true]);
        if ($insulate) {
            $client->insulate();
        }

        $client->request('GET', '/fragment_home');

        $this->assertEquals(<<<TXT
bar txt
--
html
--
es
--
fr
TXT
            , $client->getResponse()->getContent());
    }

    public static function getConfigs()
    {
        return [
            [false],
            [true],
        ];
    }

    public function testGenerateFragmentUri()
    {
        $client = self::createClient(['test_case' => 'Fragment', 'root_config' => 'config.yml', 'debug' => true]);
        $client->request('GET', '/fragment_uri');

        $this->assertSame('/_fragment?_hash=CCRGN2D%2FoAJbeGz%2F%2FdoH3bNSPwLCrmwC1zAYCGIKJ0E%3D&_path=_format%3Dhtml%26_locale%3Den%26_controller%3DSymfony%255CBundle%255CFrameworkBundle%255CTests%255CFunctional%255CBundle%255CTestBundle%255CController%255CFragmentController%253A%253AindexAction', $client->getResponse()->getContent());
    }
}
