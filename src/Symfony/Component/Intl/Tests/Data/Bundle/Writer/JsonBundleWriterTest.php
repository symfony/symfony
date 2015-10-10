<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Intl\Tests\Data\Bundle\Writer;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Intl\Data\Bundle\Writer\JsonBundleWriter;
use Symfony\Component\Intl\Intl;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 * @requires PHP 5.4
 */
class JsonBundleWriterTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var JsonBundleWriter
     */
    private $writer;

    private $directory;

    /**
     * @var Filesystem
     */
    private $filesystem;

    protected function setUp()
    {
        $this->writer = new JsonBundleWriter();
        $this->directory = sys_get_temp_dir().'/JsonBundleWriterTest/'.mt_rand(1000, 9999);
        $this->filesystem = new Filesystem();

        $this->filesystem->mkdir($this->directory);
    }

    protected function tearDown()
    {
        if (PHP_VERSION_ID < 50400) {
            return;
        }

        $this->filesystem->remove($this->directory);
    }

    public function testWrite()
    {
        $this->writer->write($this->directory, 'en', array(
            'Entry1' => array(
                'Array' => array('foo', 'bar'),
                'Integer' => 5,
                'Boolean' => false,
                'Float' => 1.23,
            ),
            'Entry2' => 'String',
            'Traversable' => new \ArrayIterator(array(
                'Foo' => 'Bar',
            )),
        ));

        $this->assertFileEquals(__DIR__.'/Fixtures/en.json', $this->directory.'/en.json');
    }

    /**
     * @requires extension intl
     */
    public function testWriteResourceBundle()
    {
        $bundle = new \ResourceBundle('rb', __DIR__.'/Fixtures', false);

        $this->writer->write($this->directory, 'en', $bundle);

        $this->assertFileEquals(__DIR__.'/Fixtures/rb.json', $this->directory.'/en.json');
    }
}
