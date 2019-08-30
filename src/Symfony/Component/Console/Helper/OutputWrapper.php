<?php declare(strict_types=1);

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Helper;

/**
 * Simple output wrapper for "tagged outputs" instead of wordwrap(). This solution is based on a StackOverflow
 * answer: https://stackoverflow.com/a/20434776/1476819 from SLN.
 *
 *  (?:
 *       # -- Words/Characters
 *       (                       # (1 start)
 *            (?>                     # Atomic Group - Match words with valid breaks
 *                 .{1,16}                 #  1-N characters
 *                                         #  Followed by one of 4 prioritized, non-linebreak whitespace
 *                 (?:                     #  break types:
 *                      (?<= [^\S\r\n] )        # 1. - Behind a non-linebreak whitespace
 *                      [^\S\r\n]?              #      ( optionally accept an extra non-linebreak whitespace )
 *                   |  (?= \r? \n )            # 2. - Ahead a linebreak
 *                   |  $                       # 3. - EOS
 *                   |  [^\S\r\n]               # 4. - Accept an extra non-linebreak whitespace
 *                 )
 *            )                       # End atomic group
 *         |
 *            .{1,16}                 # No valid word breaks, just break on the N'th character
 *       )                       # (1 end)
 *       (?: \r? \n )?           # Optional linebreak after Words/Characters
 *    |
 *       # -- Or, Linebreak
 *       (?: \r? \n | $ )        # Stand alone linebreak or at EOS
 *  )
 *
 * @author Krisztián Ferenczi <ferenczi.krisztian@gmail.com>
 *
 * @see https://stackoverflow.com/a/20434776/1476819
 */
class OutputWrapper implements OutputWrapperInterface
{
    const URL_PATTERN = 'https?://[^ ]+';

    private $keepUrlsTogether = true;

    /**
     * @return bool
     */
    public function isKeepUrlsTogether(): bool
    {
        return $this->keepUrlsTogether;
    }

    /**
     * @param bool $keepUrlsTogether
     *
     * @return $this
     */
    public function setKeepUrlsTogether(bool $keepUrlsTogether)
    {
        $this->keepUrlsTogether = $keepUrlsTogether;

        return $this;
    }

    public function wrap(string $text, int $width, string $break = "\n"): string
    {
        if (0 === $width) {
            return $text;
        }

        $tagPattern = sprintf('<(?:(?:%1$s)|/(?:%1$s)?)>', '[a-z][^<>]*+');
        $limitPattern = "{1,$width}";
        $patternBlocks = [$tagPattern];
        if ($this->keepUrlsTogether) {
            $patternBlocks[] = self::URL_PATTERN;
        }
        $patternBlocks[] = '.';
        $blocks = implode('|', $patternBlocks);
        $rowPattern = "(?:$blocks)$limitPattern";
        $pattern = sprintf('#(?:((?>(%1$s)((?<=[^\S\r\n])[^\S\r\n]?|(?=\r?\n)|$|[^\S\r\n]))|(%1$s))(?:\r?\n)?|(?:\r?\n|$))#imux', $rowPattern);
        $output = rtrim(preg_replace($pattern, '\\1' . $break, $text), $break);

        return str_replace(' ' . $break, $break, $output);
    }
}
