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

use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableStyle;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Output\StreamOutput;

class TableTest extends \PHPUnit_Framework_TestCase
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
    public function testRender($headers, $rows, $style, $expected)
    {
        $table = new Table($output = $this->getOutputStream());
        $table
            ->setHeaders($headers)
            ->setRows($rows)
            ->setStyle($style)
        ;
        $table->render();

        $this->assertEquals($expected, $this->getOutputContent($output));
    }

    /**
     * @dataProvider testRenderProvider
     */
    public function testRenderAddRows($headers, $rows, $style, $expected)
    {
        $table = new Table($output = $this->getOutputStream());
        $table
            ->setHeaders($headers)
            ->addRows($rows)
            ->setStyle($style)
        ;
        $table->render();

        $this->assertEquals($expected, $this->getOutputContent($output));
    }

    /**
     * @dataProvider testRenderProvider
     */
    public function testRenderAddRowsOneByOne($headers, $rows, $style, $expected)
    {
        $table = new Table($output = $this->getOutputStream());
        $table
            ->setHeaders($headers)
            ->setStyle($style)
        ;
        foreach ($rows as $row) {
            $table->addRow($row);
        }
        $table->render();

        $this->assertEquals($expected, $this->getOutputContent($output));
    }

    public function testRenderProvider()
    {
        $books = array(
            array('99921-58-10-7', 'Divine Comedy', 'Dante Alighieri'),
            array('9971-5-0210-0', 'A Tale of Two Cities', 'Charles Dickens'),
            array('960-425-059-0', 'The Lord of the Rings', 'J. R. R. Tolkien'),
            array('80-902734-1-6', 'And Then There Were None', 'Agatha Christie'),
        );

        return array(
            array(
                array('ISBN', 'Title', 'Author'),
                $books,
                'default',
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
                array('ISBN', 'Title', 'Author'),
                $books,
                'compact',
<<<TABLE
 ISBN          Title                    Author           
 99921-58-10-7 Divine Comedy            Dante Alighieri  
 9971-5-0210-0 A Tale of Two Cities     Charles Dickens  
 960-425-059-0 The Lord of the Rings    J. R. R. Tolkien 
 80-902734-1-6 And Then There Were None Agatha Christie  

TABLE
            ),
            array(
                array('ISBN', 'Title', 'Author'),
                $books,
                'borderless',
<<<TABLE
 =============== ========================== ================== 
  ISBN            Title                      Author            
 =============== ========================== ================== 
  99921-58-10-7   Divine Comedy              Dante Alighieri   
  9971-5-0210-0   A Tale of Two Cities       Charles Dickens   
  960-425-059-0   The Lord of the Rings      J. R. R. Tolkien  
  80-902734-1-6   And Then There Were None   Agatha Christie   
 =============== ========================== ================== 

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
                'default',
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
                'default',
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
                array('ISBN', 'Title', 'Author'),
                array(
                    array('99921-58-10-7', "Divine\nComedy", 'Dante Alighieri'),
                    array('9971-5-0210-2', "Harry Potter\nand the Chamber of Secrets", "Rowling\nJoanne K."),
                    array('9971-5-0210-2', "Harry Potter\nand the Chamber of Secrets", "Rowling\nJoanne K."),
                    array('960-425-059-0', 'The Lord of the Rings', "J. R. R.\nTolkien"),
                ),
                'default',
<<<TABLE
+---------------+----------------------------+-----------------+
| ISBN          | Title                      | Author          |
+---------------+----------------------------+-----------------+
| 99921-58-10-7 | Divine                     | Dante Alighieri |
|               | Comedy                     |                 |
| 9971-5-0210-2 | Harry Potter               | Rowling         |
|               | and the Chamber of Secrets | Joanne K.       |
| 9971-5-0210-2 | Harry Potter               | Rowling         |
|               | and the Chamber of Secrets | Joanne K.       |
| 960-425-059-0 | The Lord of the Rings      | J. R. R.        |
|               |                            | Tolkien         |
+---------------+----------------------------+-----------------+

TABLE
            ),
            array(
                array('ISBN', 'Title'),
                array(),
                'default',
<<<TABLE
+------+-------+
| ISBN | Title |
+------+-------+

TABLE
            ),
            array(
                array(),
                array(),
                'default',
                '',
            ),
            'Cell text with tags used for Output styling' => array(
                array('ISBN', 'Title', 'Author'),
                array(
                    array('<info>99921-58-10-7</info>', '<error>Divine Comedy</error>', '<fg=blue;bg=white>Dante Alighieri</fg=blue;bg=white>'),
                    array('9971-5-0210-0', 'A Tale of Two Cities', '<info>Charles Dickens</>'),
                ),
                'default',
<<<TABLE
+---------------+----------------------+-----------------+
| ISBN          | Title                | Author          |
+---------------+----------------------+-----------------+
| 99921-58-10-7 | Divine Comedy        | Dante Alighieri |
| 9971-5-0210-0 | A Tale of Two Cities | Charles Dickens |
+---------------+----------------------+-----------------+

TABLE
            ),
            'Cell text with tags not used for Output styling' => array(
                array('ISBN', 'Title', 'Author'),
                array(
                    array('<strong>99921-58-10-700</strong>', '<f>Divine Com</f>', 'Dante Alighieri'),
                    array('9971-5-0210-0', 'A Tale of Two Cities', 'Charles Dickens'),
                ),
                'default',
<<<TABLE
+----------------------------------+----------------------+-----------------+
| ISBN                             | Title                | Author          |
+----------------------------------+----------------------+-----------------+
| <strong>99921-58-10-700</strong> | <f>Divine Com</f>    | Dante Alighieri |
| 9971-5-0210-0                    | A Tale of Two Cities | Charles Dickens |
+----------------------------------+----------------------+-----------------+

TABLE
            ),
        );
    }

    public function testRenderMultiByte()
    {
        if (!function_exists('mb_strlen')) {
            $this->markTestSkipped('The "mbstring" extension is not available');
        }

        $table = new Table($output = $this->getOutputStream());
        $table
            ->setHeaders(array('■■'))
            ->setRows(array(array(1234)))
            ->setStyle('default')
        ;
        $table->render();

        $expected =
<<<TABLE
+------+
| ■■   |
+------+
| 1234 |
+------+

TABLE;

        $this->assertEquals($expected, $this->getOutputContent($output));
    }

    public function testStyle()
    {
        $style = new TableStyle();
        $style
            ->setHorizontalBorderChar('.')
            ->setVerticalBorderChar('.')
            ->setCrossingChar('.')
        ;

        Table::setStyleDefinition('dotfull', $style);
        $table = new Table($output = $this->getOutputStream());
        $table
            ->setHeaders(array('Foo'))
            ->setRows(array(array('Bar')))
            ->setStyle('dotfull');
        $table->render();

        $expected =
<<<TABLE
.......
. Foo .
.......
. Bar .
.......

TABLE;

        $this->assertEquals($expected, $this->getOutputContent($output));
    }

    public function testRowSeparator()
    {
        $table = new Table($output = $this->getOutputStream());
        $table
            ->setHeaders(array('Foo'))
            ->setRows(array(
                array('Bar1'),
                new TableSeparator(),
                array('Bar2'),
                new TableSeparator(),
                array('Bar3'),
            ));
        $table->render();

        $expected =
<<<TABLE
+------+
| Foo  |
+------+
| Bar1 |
+------+
| Bar2 |
+------+
| Bar3 |
+------+

TABLE;

        $this->assertEquals($expected, $this->getOutputContent($output));

        $this->assertEquals($table, $table->addRow(new TableSeparator()), 'fluent interface on addRow() with a single TableSeparator() works');
    }

    protected function getOutputStream()
    {
        return new StreamOutput($this->stream, StreamOutput::VERBOSITY_NORMAL, false);
    }

    protected function getOutputContent(StreamOutput $output)
    {
        rewind($output->getStream());

        return str_replace(PHP_EOL, "\n", stream_get_contents($output->getStream()));
    }
}
