<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DomCrawler\Tests\Field;

use Symfony\Component\DomCrawler\Field\FileFormField;

class FileFormFieldTest extends FormFieldTestCase
{
    public function testInitialize()
    {
        $node = $this->createNode('input', '', ['type' => 'file']);
        $field = new FileFormField($node);

        $this->assertEquals(['name' => '', 'type' => '', 'tmp_name' => '', 'error' => \UPLOAD_ERR_NO_FILE, 'size' => 0], $field->getValue(), '->initialize() sets the value of the field to no file uploaded');

        $node = $this->createNode('textarea', '');
        try {
            new FileFormField($node);
            $this->fail('->initialize() throws a \LogicException if the node is not an input field');
        } catch (\LogicException $e) {
            $this->assertTrue(true, '->initialize() throws a \LogicException if the node is not an input field');
        }

        $node = $this->createNode('input', '', ['type' => 'text']);
        try {
            new FileFormField($node);
            $this->fail('->initialize() throws a \LogicException if the node is not a file input field');
        } catch (\LogicException $e) {
            $this->assertTrue(true, '->initialize() throws a \LogicException if the node is not a file input field');
        }
    }

    /**
     * @dataProvider getSetValueMethods
     */
    public function testSetValue($method)
    {
        $node = $this->createNode('input', '', ['type' => 'file']);
        $field = new FileFormField($node);

        $field->$method(null);
        $this->assertEquals(['name' => '', 'type' => '', 'tmp_name' => '', 'error' => \UPLOAD_ERR_NO_FILE, 'size' => 0], $field->getValue(), "->$method() clears the uploaded file if the value is null");

        $field->$method(__FILE__);
        $value = $field->getValue();

        $this->assertEquals(basename(__FILE__), $value['name'], "->$method() sets the name of the file field");
        $this->assertEquals('', $value['type'], "->$method() sets the type of the file field");
        $this->assertIsString($value['tmp_name'], "->$method() sets the tmp_name of the file field");
        $this->assertFileExists($value['tmp_name'], "->$method() creates a copy of the file at the tmp_name path");
        $this->assertEquals(0, $value['error'], "->$method() sets the error of the file field");
        $this->assertEquals(filesize(__FILE__), $value['size'], "->$method() sets the size of the file field");

        $origInfo = pathinfo(__FILE__);
        $tmpInfo = pathinfo($value['tmp_name']);
        $this->assertEquals(
            $origInfo['extension'],
            $tmpInfo['extension'],
            "->$method() keeps the same file extension in the tmp_name copy"
        );

        $field->$method(__DIR__.'/../Fixtures/no-extension');
        $value = $field->getValue();

        $this->assertArrayNotHasKey(
            'extension',
            pathinfo($value['tmp_name']),
            "->$method() does not add a file extension in the tmp_name copy"
        );
    }

    public static function getSetValueMethods()
    {
        return [
            ['setValue'],
            ['upload'],
        ];
    }

    public function testTemporaryUploadsAreRemovedAtShutdown()
    {
        $code = <<<'PHP'
            namespace Symfony\Component\DomCrawler\Field {
                function register_shutdown_function(callable $callback): void
                {
                    ++$GLOBALS['shutdownCallbacks'];
                    \register_shutdown_function($callback);
                }
            }

            namespace {
                $GLOBALS['shutdownCallbacks'] = 0;
                require $argv[1].'/FormField.php';
                require $argv[1].'/FileFormField.php';

                $document = new \DOMDocument();
                $node = $document->createElement('input');
                $node->setAttribute('type', 'file');
                $field = new \Symfony\Component\DomCrawler\Field\FileFormField($node);
                $field->upload($argv[2]);
                $first = $field->getValue()['tmp_name'];
                $field->upload($argv[2]);
                $second = $field->getValue()['tmp_name'];
                $other = new \Symfony\Component\DomCrawler\Field\FileFormField($node);
                $other->upload($argv[2]);
                $third = $other->getValue()['tmp_name'];
                unset($field, $other);

                echo json_encode([$first, $second, $third, is_file($first), is_file($second), is_file($third), $GLOBALS['shutdownCallbacks']]);
            }
            PHP;
        $process = proc_open([\PHP_BINARY, '-r', $code, \dirname(__DIR__, 2).'/Field', __FILE__], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        $output = stream_get_contents($pipes[1]);
        $errors = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $this->assertSame(0, proc_close($process), $errors);
        [$first, $second, $third, $firstExists, $secondExists, $thirdExists, $shutdownCallbacks] = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);

        try {
            $this->assertTrue($firstExists);
            $this->assertTrue($secondExists);
            $this->assertTrue($thirdExists);
            $this->assertFileDoesNotExist($first);
            $this->assertFileDoesNotExist($second);
            $this->assertFileDoesNotExist($third);
            $this->assertFileExists(__FILE__);
            $this->assertSame(1, $shutdownCallbacks);
        } finally {
            @unlink($first);
            @unlink($second);
            @unlink($third);
        }
    }

    public function testSetErrorCode()
    {
        $node = $this->createNode('input', '', ['type' => 'file']);
        $field = new FileFormField($node);

        $field->setErrorCode(\UPLOAD_ERR_FORM_SIZE);
        $value = $field->getValue();
        $this->assertEquals(\UPLOAD_ERR_FORM_SIZE, $value['error'], '->setErrorCode() sets the file input field error code');

        try {
            $field->setErrorCode(12345);
            $this->fail('->setErrorCode() throws a \InvalidArgumentException if the error code is not valid');
        } catch (\InvalidArgumentException $e) {
            $this->assertTrue(true, '->setErrorCode() throws a \InvalidArgumentException if the error code is not valid');
        }
    }

    public function testSetRawFilePath()
    {
        $node = $this->createNode('input', '', ['type' => 'file']);
        $field = new FileFormField($node);
        $field->setFilePath(__FILE__);

        $this->assertEquals(__FILE__, $field->getValue());
    }
}
