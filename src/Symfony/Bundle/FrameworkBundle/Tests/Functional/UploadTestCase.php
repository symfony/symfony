<?php

namespace Symfony\Bundle\FrameworkBundle\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class UploadTestCase extends WebTestCase
{
    static protected function createKernel(array $options = array())
    {
        return new AppKernel(
            isset($options['config']) ? $options['config'] : 'default.yml'
        );
    }

    /**
     * The point of this is to send this file to the upload file
     *
     * The route should respond with the number of files sent to it. In this case 1.
     *
     * @return void
     */
    public function testUploadFile()
    {
        $client = $this->createClient();
        $crawler = $client->request(
                         'POST',
                         '/submit',
                         array('name' => 'Fabien'),
                         array('photo' => __FILE__)
                         );
        $this->assertEquals("1 File", $crawler->text());
        $this->assertEquals(1, count($client->getRequest()->files->all()));
    }

}