<?php

namespace Symfony\Component\Console\Tests\Style;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Style\WordWrapper;

class WordWrapperTest extends TestCase
{
    /**
     * @param int    $width
     * @param string $break
     * @param string $exceptionClass
     *
     * @dataProvider dpTestConstructorExceptions
     */
    public function testConstructorExceptions($width, $break, $exceptionClass)
    {
        $this->expectException($exceptionClass);
        $wordWrapper = new WordWrapper($width, $break);
    }

    public function dpTestConstructorExceptions()
    {
        return array(
            array(0, PHP_EOL, \InvalidArgumentException::class),
            array(-1, PHP_EOL, \InvalidArgumentException::class),
            array(0, '', \InvalidArgumentException::class),
            array(1, '', \InvalidArgumentException::class),
        );
    }

    /**
     * @param string $input
     * @param int    $width
     * @param string $break
     * @param bool   $cut
     * @param string $output
     *
     * @dataProvider dpFormattedStringWordwrap
     */
    public function testFormattedStringWordwrap($input, $width, $break, $cut, $output)
    {
        $wordWrapper = new WordWrapper($width, $break);
        $response = $wordWrapper->formattedStringWordwrap($this->getInputContents($input), $cut);

        $this->assertEquals($this->getOutputContents($output), $response);
    }

    public function dpFormattedStringWordwrap()
    {
        $baseBreak = "\n";
        $customBreak = "__break__\n";

        return array(
            // Check empty
            array('', 120, $baseBreak, true, ''),
            array($baseBreak, 120, $baseBreak, true, $baseBreak),
            // Check limit and UTF-8
            array(
                'utf120.txt',
                120,
                $baseBreak,
                true,
                'utf120.txt',
            ),
            // Check simple text
            array(
                'lipsum.txt',
                120,
                $baseBreak,
                true,
                'lipsum.txt',
            ),
            // Check colored text
            array(
                'lipsum_with_tags.txt',
                120,
                $baseBreak,
                true,
                'lipsum_with_tags.txt',
            ),
            // Check custom break
            array(
                'lipsum_with_tags_and_custom_break.txt',
                120,
                $customBreak,
                true,
                'lipsum_with_tags_and_custom_break.txt',
            ),
            // Check long words
            array(
                'with_long_words.txt',
                30,
                $baseBreak,
                true,
                'with_long_words_with_cut.txt',
            ),
            array(
                'with_long_words.txt',
                30,
                $baseBreak,
                false,
                'with_long_words_without_cut.txt',
            ),
        );
    }

    protected function getInputContents($filenameOrText)
    {
        $file = __DIR__.'/../Fixtures/Style/WordWrapper/input/'.$filenameOrText;

        return file_exists($file)
            ? file_get_contents($file)
            : $filenameOrText;
    }

    protected function getOutputContents($filenameOrText)
    {
        $file = __DIR__.'/../Fixtures/Style/WordWrapper/output/'.$filenameOrText;

        return file_exists($file)
            ? file_get_contents($file)
            : $filenameOrText;
    }
}
