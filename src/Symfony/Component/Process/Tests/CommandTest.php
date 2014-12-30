<?php

namespace Symfony\Component\Process\Tests;

use Symfony\Component\Process\Command;

class CommandTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider provideCommandParts
     */
    public function testCommands($expected, $parts, $escaped = true, $appendedParts = array(), $prependedParts = array(), $unescapedAppendedParts = array(), $unescapePrependedParts = array(), $redirects = array(), $appended = array())
    {
        $command = new Command($parts, $escaped);
        foreach ($appended as $toAppend) {
            $command->append($toAppend);
        }
        foreach ($appendedParts as $appendedPart) {
            $command->add($appendedPart);
        }
        foreach ($prependedParts as $prependedPart) {
            $command->add($prependedPart, true, true);
        }
        foreach ($unescapedAppendedParts as $appendedPart) {
            $command->add($appendedPart, false);
        }
        foreach ($unescapePrependedParts as $prependedPart) {
            $command->add($prependedPart, false, true);
        }
        foreach ($redirects as $fd => $target) {
            $command->redirect($fd, $target);
        }

        $this->assertEquals($expected, (string) $command);
    }

    public function provideCommandParts()
    {
        return array(
            array("'php' 'app/console'", array('php', 'app/console'))
        );
    }
}
