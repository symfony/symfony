<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Tests\Html\Element;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Html\Element\PathCompleter;
use Symfony\Component\Tui\Html\HtmlBuilder;
use Symfony\Component\Tui\Terminal\VirtualTerminal;
use Symfony\Component\Tui\Tui;
use Symfony\Component\Tui\Widget\InputWidget;

final class PathCompleterTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir().'/tui-paths-'.bin2hex(random_bytes(4));
        mkdir($this->dir.'/photos', 0o777, true);
        foreach (['photo.png', 'Photo.JPG', 'notes.txt', 'report.pdf', '.hidden.png'] as $file) {
            touch($this->dir.'/'.$file);
        }
        if ('\\' !== \DIRECTORY_SEPARATOR) {
            symlink($this->dir.'/missing', $this->dir.'/notes-link');
            touch($this->dir."/notes\nbreak.txt");
        }
    }

    protected function tearDown(): void
    {
        foreach (['photo.png', 'Photo.JPG', 'notes.txt', 'report.pdf', '.hidden.png', 'notes-link', "notes\nbreak.txt"] as $file) {
            if (file_exists($this->dir.'/'.$file) || is_link($this->dir.'/'.$file)) {
                unlink($this->dir.'/'.$file);
            }
        }
        rmdir($this->dir.'/photos');
        rmdir($this->dir);
    }

    public function testTheEntriesOfTheDirectoryStartingWithWhatIsTyped()
    {
        $completer = new PathCompleter();
        $this->assertSame([$this->dir.'/photo.png', $this->dir.'/photos/'], $completer->complete($this->dir.'/pho'), 'In its case, directories ending with a slash.');
        $this->assertSame([$this->dir.'/Photo.JPG'], $completer->complete($this->dir.'/Pho'));
        $this->assertSame([$this->dir.'/notes.txt'], $completer->complete($this->dir.'/no'), 'Not a link to nothing, nor a name with a line break.');
        $this->assertSame([$this->dir.'/.hidden.png'], $completer->complete($this->dir.'/.'), 'Hidden ones when their dot is typed.');
        $this->assertSame([], $completer->complete($this->dir.'/missing/x'));
        $this->assertSame(['~/'], $completer->complete('~'));
    }

    public function testTheFilesOfTheTypesAcceptNames()
    {
        $this->assertSame([$this->dir.'/Photo.JPG', $this->dir.'/photo.png', $this->dir.'/photos/'], (new PathCompleter('image/*'))->complete($this->dir.'/'), 'Directories still, in order.');
        $this->assertSame([$this->dir.'/photos/', $this->dir.'/report.pdf'], (new PathCompleter('.pdf, application/zip'))->complete($this->dir.'/'));
        $this->assertCount(5, (new PathCompleter('.pdf, application/x-unknown'))->complete($this->dir.'/'), 'A type not known: any file.');
        $this->assertSame(['a.txt, '.$this->dir.'/report.pdf'], (new PathCompleter('.pdf', multiple: true))->complete('a.txt, '.$this->dir.'/r'), 'The last path of a multiple field.');
        $this->assertSame(['a.txt,'.$this->dir.'/report.pdf'], (new PathCompleter('.pdf', multiple: true))->complete('a.txt,'.$this->dir.'/r'), 'The spaces after the comma as typed.');
    }

    public function testTabCompletesTheFieldAsItIsTyped()
    {
        $view = (new HtmlBuilder())->build('<input type="file" id="f" accept=".pdf">');
        $tui = new Tui(terminal: new VirtualTerminal(60, 4));
        $view->mount($tui);
        $tui->start();
        $input = $view->getById('f');
        $this->assertInstanceOf(InputWidget::class, $input);
        $tui->setFocus($input);
        $tui->handleInput($this->dir.'/re');
        $tui->handleInput("\t");
        $this->assertSame($this->dir.'/report.pdf', $input->getValue());
    }
}
