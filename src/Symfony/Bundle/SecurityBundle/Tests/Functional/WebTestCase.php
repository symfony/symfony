<?php

namespace Symfony\Bundle\SecurityBundle\Tests\Functional;

use Symfony\Component\HttpKernel\Util\Filesystem;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase as BaseWebTestCase;

class WebTestCase extends BaseWebTestCase
{
    public static function assertRedirect($response, $location)
    {
        self::assertTrue($response->isRedirect());
        self::assertEquals('http://localhost'.$location, $response->headers->get('Location'));
    }

    protected function deleteTmpDir($testCase)
    {
        if (!file_exists($dir = sys_get_temp_dir().'/'.$testCase)) {
            return;
        }

        $fs = new Filesystem();
        $fs->remove($testCase);
    }

    protected function getKernelClass()
    {
        require_once __DIR__.'/app/AppKernel.php';

        return 'Symfony\Bundle\SecurityBundle\Tests\Functional\AppKernel';
    }

    protected function createKernel(array $options = array())
    {
        $class = $this->getKernelClass();

        if (!isset($options['test_case'])) {
            throw new \InvalidArgumentException('The option "test_case" must be set.');
        }

        return new $class(
            $options['test_case'],
            isset($options['environment']) ? $options['environment'] : 'test',
            isset($options['debug']) ? $options['debug'] : true
        );
    }
}