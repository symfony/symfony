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
class WebTestHelperTest extends \PHPUnit_Framework_TestCase
{
    public function testWebTestHelper()
    {
        $client = WebTestHelper::createClient(array('test_case' => 'Session'));

        $crawler = $client->request('GET', '/session');
        $this->assertContains('You are new here and gave no name.', $crawler->text());
    }

    public function tearDown()
    {
        WebTestHelper::shutdownKernel();
    }
}
