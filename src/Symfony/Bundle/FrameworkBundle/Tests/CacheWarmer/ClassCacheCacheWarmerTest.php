<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\CacheWarmer;

use Symfony\Bundle\FrameworkBundle\CacheWarmer\ClassCacheCacheWarmer;
use Symfony\Bundle\FrameworkBundle\Tests\Fixtures\DeclaredClass;
use Symfony\Bundle\FrameworkBundle\Tests\Fixtures\WarmedClass;
use Symfony\Bundle\FrameworkBundle\Tests\TestCase;

class ClassCacheCacheWarmerTest extends TestCase
{
    public function testWithDeclaredClasses()
    {
        $this->assertTrue(class_exists(WarmedClass::class, true));

        $dir = sys_get_temp_dir();
        @unlink($dir.'/classes.php');
        file_put_contents($dir.'/classes.map', sprintf('<?php return %s;', var_export(array(WarmedClass::class), true)));

        $warmer = new ClassCacheCacheWarmer(array(DeclaredClass::class));

        $warmer->warmUp($dir);

        $this->assertSame(<<<'EOTXT'
<?php 
namespace Symfony\Bundle\FrameworkBundle\Tests\Fixtures
{
class WarmedClass extends DeclaredClass
{
}
}
EOTXT
            , file_get_contents($dir.'/classes.php')
        );

        @unlink($dir.'/classes.map');
        @unlink($dir.'/classes.php');
    }
}
