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

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Helper\TreeHelper;
use Symfony\Component\Console\Helper\TreeNode;
use Symfony\Component\Console\Helper\TreeStyle;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;

class TreeStyleTest extends TestCase
{
    public function testDefaultStyle()
    {
        $output = new BufferedOutput();
        $tree = self::createTree($output);

        $tree->render();

        $this->assertSame(<<<TREE
root
├── A
│   ├── A1
│   └── A2
│      └── A2.1
│         ├── A2.1.1
│         └── A2.1.2
├── B
│   ├── B1
│   │   ├── B11
│   │   └── B12
│   └── B2
└── C
TREE, self::normalizeLineBreaks(trim($output->fetch())));
    }

    public function testBoxStyle()
    {
        $output = new BufferedOutput();
        $this->createTree($output, TreeStyle::box())->render();

        $this->assertSame(<<<TREE
root
┃╸ A
┃  ┃╸ A1
┃  ┗╸ A2
┃     ┗╸ A2.1
┃        ┃╸ A2.1.1
┃        ┗╸ A2.1.2
┃╸ B
┃  ┃╸ B1
┃  ┃  ┃╸ B11
┃  ┃  ┗╸ B12
┃  ┗╸ B2
┗╸ C
TREE, self::normalizeLineBreaks(trim($output->fetch())));
    }

    public function testBoxDoubleStyle()
    {
        $output = new BufferedOutput();
        $this->createTree($output, TreeStyle::boxDouble())->render();

        $this->assertSame(<<<TREE
root
╠═ A
║  ╠═ A1
║  ╚═ A2
║    ╚═ A2.1
║      ╠═ A2.1.1
║      ╚═ A2.1.2
╠═ B
║  ╠═ B1
║  ║  ╠═ B11
║  ║  ╚═ B12
║  ╚═ B2
╚═ C
TREE, self::normalizeLineBreaks(trim($output->fetch())));
    }

    public function testCompactStyle()
    {
        $output = new BufferedOutput();
        $this->createTree($output, TreeStyle::compact())->render();

        $this->assertSame(<<<'TREE'
root
├ A
│ ├ A1
│ └ A2
│   └ A2.1
│     ├ A2.1.1
│     └ A2.1.2
├ B
│ ├ B1
│ │ ├ B11
│ │ └ B12
│ └ B2
└ C
TREE, self::normalizeLineBreaks(trim($output->fetch())));
    }

    public function testLightStyle()
    {
        $output = new BufferedOutput();
        $this->createTree($output, TreeStyle::light())->render();

        $this->assertSame(<<<'TREE'
root
|-- A
|   |-- A1
|   `-- A2
|       `-- A2.1
|           |-- A2.1.1
|           `-- A2.1.2
|-- B
|   |-- B1
|   |   |-- B11
|   |   `-- B12
|   `-- B2
`-- C
TREE, self::normalizeLineBreaks(trim($output->fetch())));
    }

    public function testMinimalStyle()
    {
        $output = new BufferedOutput();
        $this->createTree($output, TreeStyle::minimal())->render();

        $this->assertSame(<<<'TREE'
root
. A
. . A1
. . A2
.   . A2.1
.     . A2.1.1
.     . A2.1.2
. B
. . B1
. . . B11
. . . B12
. . B2
. C
TREE, self::normalizeLineBreaks(trim($output->fetch())));
    }

    public function testRoundedStyle()
    {
        $output = new BufferedOutput();
        $this->createTree($output, TreeStyle::rounded())->render();

        $this->assertSame(<<<'TREE'
root
├─ A
│  ├─ A1
│  ╰─ A2
│     ╰─ A2.1
│        ├─ A2.1.1
│        ╰─ A2.1.2
├─ B
│  ├─ B1
│  │  ├─ B11
│  │  ╰─ B12
│  ╰─ B2
╰─ C
TREE, self::normalizeLineBreaks(trim($output->fetch())));
    }

    public function testCustomPrefix()
    {
        $style = new TreeStyle('A ', 'B ', 'C ', 'D ', 'E ', 'F ');
        $output = new BufferedOutput();
        self::createTree($output, $style)->render();

        $this->assertSame(<<<'TREE'
root
C A F A
C D A F A1
C D B F A2
C D E B F A2.1
C D E E A F A2.1.1
C D E E B F A2.1.2
C A F B
C D A F B1
C D D A F B11
C D D B F B12
C D B F B2
C B F C
TREE, self::normalizeLineBreaks(trim($output->fetch())));
    }

    private static function createTree(OutputInterface $output, ?TreeStyle $style = null): TreeHelper
    {
        $root = new TreeNode('root');
        $root
            ->addChild((new TreeNode('A'))
                ->addChild(new TreeNode('A1'))
                ->addChild((new TreeNode('A2'))
                    ->addChild((new TreeNode('A2.1'))
                        ->addChild(new TreeNode('A2.1.1'))
                        ->addChild(new TreeNode('A2.1.2'))
                    )
                )
            )
            ->addChild((new TreeNode('B'))
                ->addChild((new TreeNode('B1'))
                    ->addChild(new TreeNode('B11'))
                    ->addChild(new TreeNode('B12'))
                )
                ->addChild(new TreeNode('B2'))
            )
            ->addChild(new TreeNode('C'));

        return TreeHelper::createTree($output, $root, [], $style);
    }

    private static function normalizeLineBreaks($text)
    {
        return str_replace(\PHP_EOL, "\n", $text);
    }
}
