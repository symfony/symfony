<?php

/*
 * This file is part of the Symfony package.
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Components\DependencyInjection\Loader;

require_once __DIR__.'/../Fixtures/includes/ProjectExtension.php';

use Symfony\Components\DependencyInjection\Loader\Loader;
use Symfony\Components\DependencyInjection\BuilderConfiguration;

class ProjectLoader1 extends Loader
{
    public function load($resource, $main = true, BuilderConfiguration $configuration = null)
    {
    }
}

class LoaderTest extends \PHPUnit_Framework_TestCase
{
    public function testExtension()
    {
        ProjectLoader1::registerExtension($extension = new \ProjectExtension());
        $this->assertTrue(ProjectLoader1::getExtension('project') === $extension, '::registerExtension() registers an extension');
    }
}
