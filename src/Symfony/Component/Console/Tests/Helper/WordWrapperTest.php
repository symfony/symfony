<?php

namespace Symfony\Component\Console\Tests\Helper;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Helper\WordWrapper;

/**
 * @author Krisztián Ferenczi <ferenczi.krisztian@gmail.com>
 */
class WordWrapperTest extends TestCase
{
    /**
     * @param int    $width
     * @param string $break
     *
     * @dataProvider dpTestConstructorExceptions
     *
     * @expectedException \InvalidArgumentException
     */
    public function testConstructorExceptions($width, $break)
    {
        $wordWrapper = new WordWrapper();
        $wordWrapper->wordwrap('test', $width, $break);
    }

    public function dpTestConstructorExceptions()
    {
        return [
            [0, PHP_EOL],
            [-1, PHP_EOL],
            [0, ''],
            [1, ''],
        ];
    }

    /**
     * @param string $input
     * @param int    $width
     * @param string $break
     * @param int    $cutOptions
     * @param string $output
     *
     * @dataProvider dpWordwrap
     */
    public function testWordwrap($input, $width, $break, $cutOptions, $output)
    {
        $wordWrapper = new WordWrapper();
        $response = $wordWrapper->wordwrap($this->getInputContents($input), $width, $break, $cutOptions);

        $this->assertEquals($this->getOutputContents($output), $response);
    }

    /**
     * Maybe in the future it should behave differently from wordwrap() function that is why it same the other now.
     *
     * @param string $input
     * @param int    $width
     * @param string $break
     * @param int    $cutOptions
     * @param string $output
     *
     * @dataProvider dpWordwrap
     */
    public function testStaticWrap($input, $width, $break, $cutOptions, $output)
    {
        $response = WordWrapper::wrap($this->getInputContents($input), $width, $break, $cutOptions);

        $this->assertEquals($this->getOutputContents($output), $response);
    }

    public function dpWordwrap()
    {
        $baseBreak = "\n";
        $customBreak = "__break__\n";

        return [
            // Check empty
            ['', 120, $baseBreak, true, ''],
            [$baseBreak, 120, $baseBreak, true, $baseBreak],
            // Check limit and UTF-8
            [
                'utf120.txt',
                120,
                $baseBreak,
                WordWrapper::DEFAULT_CUT,
                'utf120.txt',
            ],
            // Check simple text
            [
                'lipsum.txt',
                120,
                $baseBreak,
                WordWrapper::DEFAULT_CUT,
                'lipsum.txt',
            ],
            // Check colored text
            [
                'lipsum_with_tags.txt',
                120,
                $baseBreak,
                WordWrapper::DEFAULT_CUT,
                'lipsum_with_tags.txt',
            ],
            // Check custom break
            [
                'lipsum_with_tags_and_custom_break.txt',
                120,
                $customBreak,
                WordWrapper::DEFAULT_CUT,
                'lipsum_with_tags_and_custom_break.txt',
            ],
            // Check long words
            [
                'with_long_words.txt',
                30,
                $baseBreak,
                WordWrapper::DEFAULT_CUT,
                'with_long_words_with_default_cut.txt',
            ],
            [
                'with_long_words.txt',
                30,
                $baseBreak,
                WordWrapper::CUT_DISABLE,
                'with_long_words_without_cut.txt',
            ],
            [
                'with_long_words.txt',
                30,
                $baseBreak,
                WordWrapper::CUT_ALL,
                'with_long_words_with_cut_all.txt',
            ],
        ];
    }

    protected function getInputContents($filenameOrText)
    {
        $file = __DIR__.'/../Fixtures/Helper/WordWrapper/input/'.$filenameOrText;

        return file_exists($file) && is_file($file)
            ? file_get_contents($file)
            : $filenameOrText;
    }

    protected function getOutputContents($filenameOrText)
    {
        $file = __DIR__.'/../Fixtures/Helper/WordWrapper/output/'.$filenameOrText;

        return file_exists($file) && is_file($file)
            ? file_get_contents($file)
            : $filenameOrText;
    }
}
