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
            ['', 2, PrettyWordWrapper::CUT_ALL, $baseBreak, ''],
            ['', 2, PrettyWordWrapper::CUT_ALL | PrettyWordWrapper::CUT_FILL_UP_MISSING, $baseBreak, '  '],
            [$baseBreak, 2, PrettyWordWrapper::CUT_ALL, $baseBreak, $baseBreak],
            [$baseBreak, 2, PrettyWordWrapper::CUT_ALL | PrettyWordWrapper::CUT_FILL_UP_MISSING, $baseBreak, '  ' . $baseBreak . '  '],
            // Check limit and UTF-8
            [
                'öüóőúéáű',
                8,
                PrettyWordWrapper::CUT_LONG_WORDS,
                $baseBreak,
                'öüóőúéáű',
            ],
            [
                'öüóőúéáű',
                4,
                PrettyWordWrapper::CUT_LONG_WORDS | PrettyWordWrapper::CUT_FILL_UP_MISSING,
                $baseBreak,
                'öüóő' . $baseBreak . 'úéáű',
            ],
            [
                'öüóőúéáű',
                6,
                PrettyWordWrapper::CUT_LONG_WORDS | PrettyWordWrapper::CUT_FILL_UP_MISSING,
                $baseBreak,
                'öüóőúé' . $baseBreak . 'áű    ',
            ],
            // UTF-8 + tags
            [
                '<error>öüóőúéáű</error>',
                8,
                PrettyWordWrapper::CUT_LONG_WORDS | PrettyWordWrapper::CUT_FILL_UP_MISSING,
                $baseBreak,
                '<error>öüóőúéáű</error>',
            ],
            [
                'öüó<error>őú</error>éáű',
                8,
                PrettyWordWrapper::CUT_LONG_WORDS | PrettyWordWrapper::CUT_FILL_UP_MISSING,
                $baseBreak,
                'öüó<error>őú</error>éáű',
            ],
            [
                'foo <error>bar</error> baz',
                3,
                PrettyWordWrapper::CUT_LONG_WORDS | PrettyWordWrapper::CUT_FILL_UP_MISSING,
                $baseBreak,
                implode($baseBreak, ['foo', '<error>bar</error>', 'baz']),
            ],
            [
                'foo <error>bar</error> baz',
                2,
                PrettyWordWrapper::CUT_LONG_WORDS | PrettyWordWrapper::CUT_FILL_UP_MISSING,
                $baseBreak,
                implode($baseBreak, ['fo', 'o ','<error>ba', 'r</error> ', 'ba', 'z ']),
            ],
            // Escaped tags
            [
                'foo \<error>bar\</error> baz',
                3,
                PrettyWordWrapper::CUT_LONG_WORDS | PrettyWordWrapper::CUT_FILL_UP_MISSING,
                $baseBreak,
                implode($baseBreak, ['foo', '\<e', 'rro', 'r>b', 'ar\\', '</e', 'rro', 'r> ', 'baz']),
            ],
            [
                'foo<error>bar</error>baz foo',
                3,
                PrettyWordWrapper::CUT_LONG_WORDS | PrettyWordWrapper::CUT_FILL_UP_MISSING,
                $baseBreak,
                implode($baseBreak, ['foo', '<error>bar</error>', 'baz', 'foo']),
            ],
            [
                'foo<error>bar</error>baz foo',
                2,
                PrettyWordWrapper::CUT_LONG_WORDS | PrettyWordWrapper::CUT_FILL_UP_MISSING,
                $baseBreak,
                implode($baseBreak, ['fo', 'o<error>b', 'ar</error>', 'ba', 'z ', 'fo', 'o ']),
            ],
            // Check simple text
            [
                'lipsum.txt',
                120,
                PrettyWordWrapper::CUT_LONG_WORDS,
                $baseBreak,
                'lipsum.txt',
            ],
            // Check colored text
            [
                'lipsum_with_tags.txt',
                120,
                PrettyWordWrapper::CUT_LONG_WORDS,
                $baseBreak,
                'lipsum_with_tags.txt',
            ],
            // Check custom break
            [
                'lipsum_with_tags_and_custom_break.txt',
                120,
                PrettyWordWrapper::CUT_LONG_WORDS,
                $customBreak,
                'lipsum_with_tags_and_custom_break.txt',
            ],
            // Check long words
            [
                'with_long_words.txt',
                30,
                PrettyWordWrapper::CUT_LONG_WORDS,
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
