<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Intl\Tests\ResourceBundle\Writer;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Intl\ResourceBundle\Writer\TextBundleWriter;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @see http://source.icu-project.org/repos/icu/icuhtml/trunk/design/bnf_rb.txt
 */
class TextBundleWriterTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var TextBundleWriter
     */
    private $writer;

    private $directory;

    /**
     * @var Filesystem
     */
    private $filesystem;

    protected function setUp()
    {
        $this->writer = new TextBundleWriter();
        $this->directory = sys_get_temp_dir() . '/TextBundleWriterTest/' . rand(1000, 9999);
        $this->filesystem = new Filesystem();

        $this->filesystem->mkdir($this->directory);
    }

    protected function tearDown()
    {
        $this->filesystem->remove($this->directory);
    }

    public function testWrite()
    {
        $this->writer->write($this->directory, 'en', array(
            'Entry1' => array(
                'Array' => array('foo', 'bar', array('Key' => 'value')),
                'Integer' => 5,
                'IntVector' => array(0, 1, 2, 3),
                'FalseBoolean' => false,
                'TrueBoolean' => true,
                'Null' => null,
                'Float' => 1.23,
            ),
            'Entry2' => 'String',
        ));

        $this->assertFileEquals(__DIR__ . '/Fixtures/en.txt', $this->directory . '/en.txt');
    }
}
