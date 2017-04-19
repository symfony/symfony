<?php

namespace Symfony\Component\Image\Tests\Regression;

use Symfony\Component\Image\Fixtures\Loader as FixturesLoader;
use Symfony\Component\Image\Gd\Loader;
use Symfony\Component\Image\Exception\RuntimeException;
use Symfony\Component\Image\Tests\TestCase;

class RegressionErrorTest extends TestCase
{
    private function getLoader()
    {
        try {
            $loader = new Loader();
        } catch (RuntimeException $e) {
            $this->markTestSkipped($e->getMessage());
        }

        return $loader;
    }

    /**
     * @expectedException \Symfony\Component\Image\Exception\RuntimeException
     */
    public function testShouldThrowExceptionNotError()
    {
        $invalidPath = '/thispathdoesnotexist';

        $loader = $this->getLoader();

        $loader->open(FixturesLoader::getFixture('large.jpg'))
            ->save($invalidPath.'/myfile.jpg');
    }
}
