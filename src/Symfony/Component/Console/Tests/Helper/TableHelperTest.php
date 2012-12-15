<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Tests\Helper;

use Symfony\Component\Console\Helper\TableHelper;
use Symfony\Component\Console\Output\StreamOutput;

class TableHelperTest extends \PHPUnit_Framework_TestCase
{
    protected $stream;

    protected function setUp()
    {
        $this->stream = fopen('php://memory', 'r+');
    }

    protected function tearDown()
    {
        fclose($this->stream);
        $this->stream = null;
    }

    /**
     * @dataProvider testRenderProvider
     */
    public function testRender($headers, $rows, $expected)
    {
        $table = new TableHelper();
        $table
            ->setHeaders($headers)
            ->setRows($rows)
        ;
        $table->render($output = $this->getOutputStream());

        $this->assertEquals($expected, $this->getOutputContent($output));
    }

    /**
     * @dataProvider testRenderProvider
     */
    public function testRenderAddRows($headers, $rows, $expected)
    {
        $table = new TableHelper();
        $table
            ->setHeaders($headers)
            ->addRows($rows)
        ;
        $table->render($output = $this->getOutputStream());

        $this->assertEquals($expected, $this->getOutputContent($output));
    }

    /**
     * @dataProvider testRenderProvider
     */
    public function testRenderAddRowsOneByOne($headers, $rows, $expected)
    {
        $table = new TableHelper();
        $table->setHeaders($headers);
        foreach ($rows as $row) {
            $table->addRow($row);
        }
        $table->render($output = $this->getOutputStream());

        $this->assertEquals($expected, $this->getOutputContent($output));
    }

    public static function testRenderProvider()
    {
        return array(
            array(
                array('ISBN', 'Title', 'Author'),
                array(
                    array('99921-58-10-7', 'Divine Comedy', 'Dante Alighieri'),
                    array('9971-5-0210-0', 'A Tale of Two Cities', 'Charles Dickens'),
                    array('960-425-059-0', 'The Lord of the Rings', 'J. R. R. Tolkien'),
                    array('80-902734-1-6', 'And Then There Were None', 'Agatha Christie'),
                ),
<<<TABLE
+---------------+--------------------------+------------------+
| ISBN          | Title                    | Author           |
+---------------+--------------------------+------------------+
| 99921-58-10-7 | Divine Comedy            | Dante Alighieri  |
| 9971-5-0210-0 | A Tale of Two Cities     | Charles Dickens  |
| 960-425-059-0 | The Lord of the Rings    | J. R. R. Tolkien |
| 80-902734-1-6 | And Then There Were None | Agatha Christie  |
+---------------+--------------------------+------------------+

TABLE
            ),
            array(
                array('ISBN', 'Title'),
                array(
                    array('99921-58-10-7', 'Divine Comedy', 'Dante Alighieri'),
                    array('9971-5-0210-0'),
                    array('960-425-059-0', 'The Lord of the Rings', 'J. R. R. Tolkien'),
                    array('80-902734-1-6', 'And Then There Were None', 'Agatha Christie'),
                ),
<<<TABLE
+---------------+--------------------------+------------------+
| ISBN          | Title                    |                  |
+---------------+--------------------------+------------------+
| 99921-58-10-7 | Divine Comedy            | Dante Alighieri  |
| 9971-5-0210-0 |                          |                  |
| 960-425-059-0 | The Lord of the Rings    | J. R. R. Tolkien |
| 80-902734-1-6 | And Then There Were None | Agatha Christie  |
+---------------+--------------------------+------------------+

TABLE
            ),
            array(
                array(),
                array(
                    array('99921-58-10-7', 'Divine Comedy', 'Dante Alighieri'),
                    array('9971-5-0210-0'),
                    array('960-425-059-0', 'The Lord of the Rings', 'J. R. R. Tolkien'),
                    array('80-902734-1-6', 'And Then There Were None', 'Agatha Christie'),
                ),
<<<TABLE
+---------------+--------------------------+------------------+
| 99921-58-10-7 | Divine Comedy            | Dante Alighieri  |
| 9971-5-0210-0 |                          |                  |
| 960-425-059-0 | The Lord of the Rings    | J. R. R. Tolkien |
| 80-902734-1-6 | And Then There Were None | Agatha Christie  |
+---------------+--------------------------+------------------+

TABLE
            ),
            array(
                array('ISBN', 'Title'),
                array(),
<<<TABLE
+------+-------+
| ISBN | Title |
+------+-------+

TABLE
            ),
            array(
                array(),
                array(),
                '',
            ),
        );
    }

    protected function getOutputStream()
    {
        return new StreamOutput($this->stream);
    }

    protected function getOutputContent(StreamOutput $output)
    {
        rewind($output->getStream());

        return stream_get_contents($output->getStream());
    }
}
