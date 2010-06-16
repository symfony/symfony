<?php

namespace Symfony\Framework\WebBundle\Test;

use Symfony\Foundation\Test\WebTestCase as BaseWebTestCase;
use Symfony\Components\Finder\Finder;
use Symfony\Components\HttpKernel\Response;

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * WebTestCase is the base class for functional tests.
 *
 * @package    Symfony
 * @subpackage Framework_WebBundle
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
abstract class WebTestCase extends BaseWebTestCase
{
    /**
     * Gets a profiler for the given Response.
     *
     * @param Symfony\Components\HttpKernel\Response $response A Response instance
     *
     * @return Symfony\Components\HttpKernel\Profiler\Profiler A Profiler instance
     */
    public function getProfiler(Response $response)
    {
        $container = $this->kernel->getContainer();

        if ($container->hasService('profiler')) {
            return $container->getProfilerService()->load($response);
        }
    }

    /**
     * Creates a Kernel.
     *
     * If you run tests with the PHPUnit CLI tool, everything will work as expected.
     * If not, override this method in your test classes.
     *
     * Available options:
     *
     *  * environment
     *  * debug
     *
     * @param array $options An array of options
     *
     * @return Symfony\Components\HttpKernel\HttpKernelInterface A HttpKernelInterface instance
     */
    protected function createKernel(array $options = array())
    {
        // black magic below, you have been warned!
        $dir = getcwd();
        if (!isset($_SERVER['argv']) || false === strpos($_SERVER['argv'][0], 'phpunit')) {
            throw new \RuntimeException('You must override the WebTestCase::createKernel() method.');
        }

        // find the --configuration flag from PHPUnit
        $cli = implode(' ', $_SERVER['argv']);
        if (preg_match('/\-\-configuration[= ]+([^ ]+)/', $cli, $matches)) {
            $dir = $dir.'/'.dirname($matches[1]);
        }

        $finder = new Finder();
        $finder->name('*Kernel.php')->in($dir);
        if (!count($finder)) {
            throw new \RuntimeException('You must override the WebTestCase::createKernel() method.');
        }

        $file = current(iterator_to_array($finder));
        $class = $file->getBasename('.php');
        unset($finder);

        require_once $file;

        return new $class(
            isset($options['environment']) ? $options['environment'] : 'test',
            isset($options['debug']) ? $debug : true
        );
    }
}
