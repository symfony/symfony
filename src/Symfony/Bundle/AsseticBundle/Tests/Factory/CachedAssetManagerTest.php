<?php

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Symfony\Bundle\AsseticBundle\Tests\Factory;

use Symfony\Bundle\AsseticBundle\Factory\CachedAssetManager;

class CachedAssetManagerTest extends \PHPUnit_Framework_TestCase
{
    protected function setUp()
    {
        if (!class_exists('Assetic\\AssetManager')) {
            $this->markTestSkipped('Assetic is not available.');
        }
    }

    public function testLoadFormulae()
    {
        $file = tempnam(sys_get_temp_dir(), 'assetic');
        file_put_contents($file, '<?php return array(\'foo\' => array());');

        $factory = $this->getMockBuilder('Assetic\\Factory\\AssetFactory')
            ->disableOriginalConstructor()
            ->getMock();

        $am = new CachedAssetManager($factory);
        $am->addCacheFile($file);

        $this->assertTrue($am->has('foo'), '->loadFormulae() loads formulae');

        unlink($file);
    }
}
