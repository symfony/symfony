<?php

namespace Symfony\Component\Console\Tests\Helper;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Helper\PrettyWordWrapper;

/**
 * @author Krisztián Ferenczi <ferenczi.krisztian@gmail.com>
 */
class PrettyWordWrapperTest extends TestCase
{
    /**
     * @param int    $width
     * @param string $break
     *
     * @dataProvider dpTestConstructorExceptions
     */
    public function testConstructorExceptions($width, $break)
    {
        $wordWrapper = new PrettyWordWrapper();
        $this->expectException(\InvalidArgumentException::class);
        $wordWrapper->wordwrap('test', $width, PrettyWordWrapper::DEFAULT_CUT, $break);
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
    public function testWordwrap($input, $width, $cutOptions, $break, $output)
    {
        $wordWrapper = new PrettyWordWrapper();
        $response = $wordWrapper->wordwrap($this->getInputContents($input), $width, $cutOptions, $break);

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
    public function testStaticWrap($input, $width, $cutOptions, $break, $output)
    {
        $response = PrettyWordWrapper::wrap($this->getInputContents($input), $width, $cutOptions, $break);

        $this->assertEquals($this->getOutputContents($output), $response);
    }

    public function dpWordwrap()
    {
        $baseBreak = "\n";
        $customBreak = "__break__\n";

        return [
            // Check empty
            ['', 120, PrettyWordWrapper::DEFAULT_CUT, $baseBreak, ''],
            [$baseBreak, 120, PrettyWordWrapper::DEFAULT_CUT, $baseBreak, $baseBreak],
            // Check limit and UTF-8
            [
                'utf120.txt',
                120,
                PrettyWordWrapper::DEFAULT_CUT,
                $baseBreak,
                'utf120.txt',
            ],
            // Check simple text
            [
                'lipsum.txt',
                120,
                PrettyWordWrapper::DEFAULT_CUT,
                $baseBreak,
                'lipsum.txt',
            ],
            // Check colored text
            [
                'lipsum_with_tags.txt',
                120,
                PrettyWordWrapper::DEFAULT_CUT,
                $baseBreak,
                'lipsum_with_tags.txt',
            ],
            // Check custom break
            [
                'lipsum_with_tags_and_custom_break.txt',
                120,
                PrettyWordWrapper::DEFAULT_CUT,
                $customBreak,
                'lipsum_with_tags_and_custom_break.txt',
            ],
            // Check long words
            [
                'with_long_words.txt',
                30,
                PrettyWordWrapper::DEFAULT_CUT,
                $baseBreak,
                'with_long_words_with_default_cut.txt',
            ],
            [
                'with_long_words.txt',
                30,
                PrettyWordWrapper::CUT_DISABLE,
                $baseBreak,
                'with_long_words_without_cut.txt',
            ],
            [
                'with_long_words.txt',
                30,
                PrettyWordWrapper::CUT_ALL,
                $baseBreak,
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
