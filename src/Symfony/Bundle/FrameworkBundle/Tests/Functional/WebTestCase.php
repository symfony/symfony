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

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase as BaseWebTestCase;

class WebTestCase extends BaseWebTestCase
{
    public static function assertRedirect($response, $location)
    {
        self::assertTrue($response->isRedirect(), 'Response is not a redirect, got status code: '.$response->getStatusCode());
        self::assertEquals('http://localhost'.$location, $response->headers->get('Location'));
    }

    protected static function createKernel(array $options = array())
    {
        if (!isset($options['test_case'])) {
            throw new \InvalidArgumentException('The option "test_case" must be set.');
        }

        if (!isset($options['environment'])) {
            $options['environment'] = strtolower(static::getVarDir().$options['test_case']);
        }

        if (!isset($options['config_dir'])) {
            $options['config_dir'] = __DIR__.'/app';
        }
        
        return parent::createKernel($options);
    }

    protected static function getVarDir()
    {
        return 'FB'. parent::getVarDir();
    }
}
