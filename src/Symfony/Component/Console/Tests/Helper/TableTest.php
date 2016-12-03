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
use Symfony\Component\Console\Helper\TableCell;
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
<<<'TABLE'
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
<<<'TABLE'
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
<<<'TABLE'
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
<<<'TABLE'
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
<<<'TABLE'
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
<<<'TABLE'
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
<<<'TABLE'
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
<<<'TABLE'
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
<<<'TABLE'
+----------------------------------+----------------------+-----------------+
| ISBN                             | Title                | Author          |
+----------------------------------+----------------------+-----------------+
| <strong>99921-58-10-700</strong> | <f>Divine Com</f>    | Dante Alighieri |
| 9971-5-0210-0                    | A Tale of Two Cities | Charles Dickens |
+----------------------------------+----------------------+-----------------+

TABLE
            ),
            'Cell with colspan' => array(
                array('ISBN', 'Title', 'Author'),
                array(
                    array('99921-58-10-7', 'Divine Comedy', 'Dante Alighieri'),
                    new TableSeparator(),
                    array(new TableCell('Divine Comedy(Dante Alighieri)', array('colspan' => 3))),
                    new TableSeparator(),
                    array(
                        new TableCell('Arduino: A Quick-Start Guide', array('colspan' => 2)),
                        'Mark Schmidt',
                    ),
                    new TableSeparator(),
                    array(
                        '9971-5-0210-0',
                        new TableCell("A Tale of \nTwo Cities", array('colspan' => 2)),
                    ),
                    new TableSeparator(),
                    array(
                        new TableCell('Cupiditate dicta atque porro, tempora exercitationem modi animi nulla nemo vel nihil!', array('colspan' => 3)),
                    ),
                ),
                'default',
<<<'TABLE'
+-------------------------------+-------------------------------+-----------------------------+
| ISBN                          | Title                         | Author                      |
+-------------------------------+-------------------------------+-----------------------------+
| 99921-58-10-7                 | Divine Comedy                 | Dante Alighieri             |
+-------------------------------+-------------------------------+-----------------------------+
| Divine Comedy(Dante Alighieri)                                                              |
+-------------------------------+-------------------------------+-----------------------------+
| Arduino: A Quick-Start Guide                                  | Mark Schmidt                |
+-------------------------------+-------------------------------+-----------------------------+
| 9971-5-0210-0                 | A Tale of                                                   |
|                               | Two Cities                                                  |
+-------------------------------+-------------------------------+-----------------------------+
| Cupiditate dicta atque porro, tempora exercitationem modi animi nulla nemo vel nihil!       |
+-------------------------------+-------------------------------+-----------------------------+

TABLE
            ),
            'Cell with rowspan' => array(
                array('ISBN', 'Title', 'Author'),
                array(
                    array(
                        new TableCell('9971-5-0210-0', array('rowspan' => 3)),
                        'Divine Comedy',
                        'Dante Alighieri',
                    ),
                    array('A Tale of Two Cities', 'Charles Dickens'),
                    array("The Lord of \nthe Rings", "J. R. \nR. Tolkien"),
                    new TableSeparator(),
                    array('80-902734-1-6', new TableCell("And Then \nThere \nWere None", array('rowspan' => 3)), 'Agatha Christie'),
                    array('80-902734-1-7', 'Test'),
                ),
                'default',
<<<'TABLE'
+---------------+----------------------+-----------------+
| ISBN          | Title                | Author          |
+---------------+----------------------+-----------------+
| 9971-5-0210-0 | Divine Comedy        | Dante Alighieri |
|               | A Tale of Two Cities | Charles Dickens |
|               | The Lord of          | J. R.           |
|               | the Rings            | R. Tolkien      |
+---------------+----------------------+-----------------+
| 80-902734-1-6 | And Then             | Agatha Christie |
| 80-902734-1-7 | There                | Test            |
|               | Were None            |                 |
+---------------+----------------------+-----------------+

TABLE
            ),
            'Cell with rowspan and colspan' => array(
                array('ISBN', 'Title', 'Author'),
                array(
                    array(
                        new TableCell('9971-5-0210-0', array('rowspan' => 2, 'colspan' => 2)),
                        'Dante Alighieri',
                    ),
                    array('Charles Dickens'),
                    new TableSeparator(),
                    array(
                        'Dante Alighieri',
                        new TableCell('9971-5-0210-0', array('rowspan' => 3, 'colspan' => 2)),
                    ),
                    array('J. R. R. Tolkien'),
                    array('J. R. R'),
                ),
                'default',
<<<'TABLE'
+------------------+---------+-----------------+
| ISBN             | Title   | Author          |
+------------------+---------+-----------------+
| 9971-5-0210-0              | Dante Alighieri |
|                            | Charles Dickens |
+------------------+---------+-----------------+
| Dante Alighieri  | 9971-5-0210-0             |
| J. R. R. Tolkien |                           |
| J. R. R          |                           |
+------------------+---------+-----------------+

TABLE
            ),
            'Cell with rowspan and colspan contains new line break' => array(
                array('ISBN', 'Title', 'Author'),
                array(
                    array(
                        new TableCell("9971\n-5-\n021\n0-0", array('rowspan' => 2, 'colspan' => 2)),
                        'Dante Alighieri',
                    ),
                    array('Charles Dickens'),
                    new TableSeparator(),
                    array(
                        'Dante Alighieri',
                        new TableCell("9971\n-5-\n021\n0-0", array('rowspan' => 2, 'colspan' => 2)),
                    ),
                    array('Charles Dickens'),
                    new TableSeparator(),
                    array(
                        new TableCell("9971\n-5-\n021\n0-0", array('rowspan' => 2, 'colspan' => 2)),
                        new TableCell("Dante \nAlighieri", array('rowspan' => 2, 'colspan' => 1)),
                    ),
                ),
                'default',
<<<'TABLE'
+-----------------+-------+-----------------+
| ISBN            | Title | Author          |
+-----------------+-------+-----------------+
| 9971                    | Dante Alighieri |
| -5-                     | Charles Dickens |
| 021                     |                 |
| 0-0                     |                 |
+-----------------+-------+-----------------+
| Dante Alighieri | 9971                    |
| Charles Dickens | -5-                     |
|                 | 021                     |
|                 | 0-0                     |
+-----------------+-------+-----------------+
| 9971                    | Dante           |
| -5-                     | Alighieri       |
| 021                     |                 |
| 0-0                     |                 |
+-----------------+-------+-----------------+

TABLE
            ),
            'Cell with rowspan and colspan without using TableSeparator' => array(
                array('ISBN', 'Title', 'Author'),
                array(
                    array(
                        new TableCell("9971\n-5-\n021\n0-0", array('rowspan' => 2, 'colspan' => 2)),
                        'Dante Alighieri',
                    ),
                    array('Charles Dickens'),
                    array(
                        'Dante Alighieri',
                        new TableCell("9971\n-5-\n021\n0-0", array('rowspan' => 2, 'colspan' => 2)),
                    ),
                    array('Charles Dickens'),
                ),
                'default',
<<<'TABLE'
+-----------------+-------+-----------------+
| ISBN            | Title | Author          |
+-----------------+-------+-----------------+
| 9971                    | Dante Alighieri |
| -5-                     | Charles Dickens |
| 021                     |                 |
| 0-0                     |                 |
| Dante Alighieri | 9971                    |
| Charles Dickens | -5-                     |
|                 | 021                     |
|                 | 0-0                     |
+-----------------+-------+-----------------+

TABLE
            ),
            'Cell with rowspan and colspan with separator inside a rowspan' => array(
                array('ISBN', 'Author'),
                array(
                    array(
                        new TableCell('9971-5-0210-0', array('rowspan' => 3, 'colspan' => 1)),
                        'Dante Alighieri',
                    ),
                    array(new TableSeparator()),
                    array('Charles Dickens'),
                ),
                'default',
<<<'TABLE'
+---------------+-----------------+
| ISBN          | Author          |
+---------------+-----------------+
| 9971-5-0210-0 | Dante Alighieri |
|               |-----------------|
|               | Charles Dickens |
+---------------+-----------------+

TABLE
            ),
            'Multiple header lines' => array(
                array(
                    array(new TableCell('Main title', array('colspan' => 3))),
                    array('ISBN', 'Title', 'Author'),
                ),
                array(),
                'default',
<<<'TABLE'
+------+-------+--------+
| Main title            |
+------+-------+--------+
| ISBN | Title | Author |
+------+-------+--------+

TABLE
            ),
            'Row with multiple cells' => array(
                array(),
                array(
                    array(
                        new TableCell('1', array('colspan' => 3)),
                        new TableCell('2', array('colspan' => 2)),
                        new TableCell('3', array('colspan' => 2)),
                        new TableCell('4', array('colspan' => 2)),
                    ),
        ),
                'default',
<<<'TABLE'
+---+--+--+---+--+---+--+---+--+
| 1       | 2    | 3    | 4    |
+---+--+--+---+--+---+--+---+--+

TABLE
            ),
        );
    }

    /**
     * @requires extension mbstring
     */
    public function testRenderMultiByte()
    {
        $table = new Table($output = $this->getOutputStream());
        $table
            ->setHeaders(array('■■'))
            ->setRows(array(array(1234)))
            ->setStyle('default')
        ;
        $table->render();

        $expected =
<<<'TABLE'
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
<<<'TABLE'
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
<<<'TABLE'
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

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Style "absent" is not defined.
     */
    public function testIsNotDefinedStyleException()
    {
        $table = new Table($this->getOutputStream());
        $table->setStyle('absent');
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Style "absent" is not defined.
     */
    public function testGetStyleDefinition()
    {
        Table::getStyleDefinition('absent');
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
