<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\ClassLoader;

use Symfony\Component\ClassLoader\DebugUniversalClassLoader;

class DebugUniversalClassLoaderTest extends \PHPUnit_Framework_TestCase
{
    public function testAClassNotFoundThrowsAnExplicitHelpException()
    {
        $loader = new DebugUniversalClassLoader();
        
        try {
            $loader->findFile('NonExistingClass');
        }
        catch (\RuntimeException $e) {
            $message = 'The autoloader expected class "NonExistingClass" to be defined in file "". You probably have a typo in the namespace or the class name.';
            $this->assertEquals($message, $e->getMessage());
        }
    }
    
    public function testAnUnreadableFileContainingTheClassToAutoloadThrowsAnExplicitHelpException()
    {      
        $fixtureDir     = __DIR__.DIRECTORY_SEPARATOR.'Fixtures';
        $unreadableFile = $fixtureDir.DIRECTORY_SEPARATOR.'Namespaced'.DIRECTORY_SEPARATOR.'Unreadable.php';
        $loader         = new DebugUniversalClassLoader();
        $loader->registerNamespace('Namespaced', $fixtureDir);
        touch($unreadableFile);
        chmod($unreadableFile, 0333);
        
        try {
            $loader->findFile('\\Namespaced\Unreadable');
            $this->fail('The autoloader should raise an explicit exception when unable to load a file due to its permissions.');
        }
        catch (\RuntimeException $e) {
            $message = 'The autoloader could not load "\Namespaced\Unreadable" from "'.__DIR__.'/Fixtures/Namespaced/Unreadable.php", the file is not readable by this PHP process.';
            $this->assertEquals($message, $e->getMessage());
        }
        
        unlink($unreadableFile);
    }
}
