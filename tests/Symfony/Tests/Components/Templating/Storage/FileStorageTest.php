<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Components\Templating\Storage;

use Symfony\Components\Templating\Storage\Storage;
use Symfony\Components\Templating\Storage\FileStorage;

class FileStorageTest extends \PHPUnit_Framework_TestCase
{
    public function testGetContent()
    {
        $storage = new FileStorage('foo');
        $this->assertInstanceOf('Symfony\Components\Templating\Storage\Storage', $storage, 'FileStorage is an instance of Storage');
        $storage = new FileStorage(__DIR__.'/../Fixtures/templates/foo.php');
        $this->assertEquals('<?php echo $foo ?>', $storage->getContent(), '->getContent() returns the content of the template');
    }
}
