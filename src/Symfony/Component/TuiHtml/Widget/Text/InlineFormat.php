<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\TuiHtml\Widget\Text;

use Symfony\Component\Tui\Widget\Util\StringUtils;
use Symfony\Component\TuiHtml\Style\Color;
use Symfony\Component\TuiHtml\Style\Style;
use Symfony\Component\TuiHtml\Style\TextAlign;

/**
 * How an inline element, or a text block as a whole, is formatted.
 *
 * Computed by a style provider (CSS) and pushed to the widget, because it
 * goes beyond a {@see Style}: generated content, hidden runs, text
 * transforms and whitespace handling.
 *
 * @experimental
 */
final class InlineFormat
{
    public readonly int $indent;
    public readonly float $indentPercent;
    public readonly int $lineGap;
    public readonly int $letterSpacing;
    public readonly int $lineClamp;
    public readonly int $tabSize;
    public readonly int $wordSpacing;
    public readonly ?string $marker;
    /** @var list<array{?array{int, int, int}, ?array{int, int, int}}>|null The cells of an image drawn as the marker, two pixels each, its room the marker */
    public readonly ?array $markerImage;
    public readonly string $hyphen;
    public readonly int $orphans;
    public readonly int $widows;

    /**
     * @param string|null                                                                     $textTransform   The transforms joined by +, in the order they apply: a case (uppercase, lowercase, capitalize), full-width, full-size-kana, small-caps for small capitals
     * @param 'normal'|'nowrap'|'pre'|'pre-wrap'|'pre-line'                                   $whiteSpace
     * @param string|null                                                                     $ellipsis        What ends a line cut by nowrap, null for the default
     * @param int                                                                             $indent          Columns before the first line of a text block, like text-indent: negative, the other lines are the ones further in
     * @param int                                                                             $lineGap         Blank rows between the lines of a text block, like line-height
     * @param int                                                                             $letterSpacing   Columns between the characters of a run, like letter-spacing
     * @param 'super'|'sub'|null                                                              $script          Superscript or subscript characters, like vertical-align: super and sub
     * @param Style|null                                                                      $firstLetter     The style of the first letter of a text block, like ::first-letter
     * @param int                                                                             $lineClamp       The most lines of a text block, the last one ending with an ellipsis, like line-clamp (0 = no limit)
     * @param bool                                                                            $breakAll        Whether lines can break between any two characters, like word-break: break-all
     * @param string|null                                                                     $emphasis        The mark drawn over each character, like text-emphasis-style (null = none)
     * @param bool                                                                            $emphasisUnder   Whether the marks are under the characters, like text-emphasis-position: under
     * @param Style|null                                                                      $emphasisStyle   The color of the marks, like text-emphasis-color (null = that of the text)
     * @param bool                                                                            $keepAll         Whether the letters of Chinese, Japanese and Korean words stay together, lines breaking at spaces and punctuation only, like word-break: keep-all
     * @param bool                                                                            $hyphens         Whether soft hyphens are where words can break, like hyphens: manual
     * @param bool                                                                            $balance         Whether the lines are made as even as possible, like text-wrap: balance
     * @param int                                                                             $tabSize         The columns between tab stops in preserved whitespace, like tab-size
     * @param array{int, int, int, int}                                                       $box             Columns of an inline element: left margin, left padding, right padding, right margin
     * @param string|null                                                                     $borderLeft      The styled line on the left of an inline element with a border there
     * @param string|null                                                                     $borderRight     The styled line on its right
     * @param int                                                                             $wordSpacing     Columns added to each space between words of a text block, like word-spacing
     * @param TextAlign|null                                                                  $alignLast       How the last line of each paragraph of a text block is aligned, like text-align-last (null = auto)
     * @param 'auto'|'none'|'inter-word'|'inter-character'                                    $justify         How justified lines are filled, like text-justify: the spaces between words, or the room between characters too (auto: that of a line of wide characters without spaces), or not justified
     * @param string|null                                                                     $marker          The marker of a list item before its first line, its other lines aligned after it, like list-style-position: outside
     * @param float                                                                           $indentPercent   What the indent adds, in percent of the width of the text block
     * @param bool                                                                            $hanging         Whether the indent is of the lines but the first, like text-indent: hanging
     * @param bool                                                                            $eachLine        Whether the lines after a forced line break are indented like the first, like text-indent: each-line
     * @param Style|null                                                                      $firstLine       The style of the first line of a text block, under the styles of its inline elements, like ::first-line
     * @param bool                                                                            $pretty          Whether the last line of a paragraph is kept from holding a single word, like text-wrap: pretty
     * @param bool                                                                            $cloneDecoration Whether each line of an inline element that wraps has its padding and border on both sides, like box-decoration-break: clone
     * @param string                                                                          $hyphen          What ends a line broken at a soft hyphen, like hyphenate-character
     * @param list<'alpha'|'numeric'>                                                         $autospace       What a space is put between ideographs and, like text-autospace: ideograph-alpha and ideograph-numeric
     * @param array{float, int}|null                                                          $initialLetter   The size in lines and the sink of the first letter of a text block, a drop cap, like initial-letter (null = normal)
     * @param bool                                                                            $rtl             Whether the text block runs right to left, like direction: rtl: its marker after its lines, on their right
     * @param array{int, int, int}|null                                                       $hyphenLimits    The fewest characters of a word broken at a soft hyphen, before the break and after it, like hyphenate-limit-chars (null = no limit)
     * @param Style|null                                                                      $beforeStyle     The style of the ::before content, over that of the text around it
     * @param Style|null                                                                      $afterStyle      The style of the ::after content, over that of the text around it
     * @param Style|null                                                                      $markerStyle     The style of the marker drawn outside the text, like ::marker
     * @param array{int, int}                                                                 $hang            The columns of the left and right padding an opening punctuation at the start and a closing one at the end of the text block can go over, like hanging-punctuation: first and last
     * @param 'ruby'|'ruby-base'|'ruby-text'|'ruby-base-container'|'ruby-text-container'|null $ruby            The part of a ruby an inline element is, like display: ruby-text for an annotation drawn over its base
     * @param bool                                                                            $rubyUnder       Whether the annotation is drawn under its base, like ruby-position: under
     * @param 'start'|'center'|'space-around'|'space-between'                                 $rubyAlign       How an annotation narrower than its base is placed over it, and a base narrower than its annotation under it, like ruby-align
     * @param Hyphenator|null                                                                 $hyphenator      The patterns that break its words where they have no soft hyphens, like hyphens: auto in the language of the text (null = none)
     * @param 'normal'|'embed'|'isolate'|'bidi-override'|'isolate-override'|'plaintext'       $unicodeBidi     How the text of an inline element is set apart for the bidirectional algorithm, in its direction (rtl), like unicode-bidi; plaintext on a text block: its direction from its first strong character
     * @param bool                                                                            $hangEnd         Whether a stop or a comma ending a line can go over the right columns of hang, like hanging-punctuation: allow-end
     * @param int                                                                             $orphans         The fewest lines of a text block left before a column break, like orphans
     * @param int                                                                             $widows          The fewest lines of a text block after a column break, like widows
     * @param list<array{0: string, 1: Style, 2?: int}>                                       $highlights      The custom highlights of a text block: a pattern matching their text across its inline elements (its first group when it has one), their style over that of the text, like ::highlight(), and how many matches at most
     * @param list<array{int, int, ?Color}>|null                                              $textShadow      The shadows of the text of a block, the first one over the others: each offset in columns and rows, from -2 to 2, and its color (null = dim), showing around the text, like text-shadow
     * @param bool                                                                            $atomic          Whether an inline element is one box in the line, like display: inline-block: its words kept together on a line when they fit, like white-space: nowrap
     */
    public function __construct(public readonly ?Style $style = null, public readonly bool $hidden = false, public readonly ?string $before = null, public readonly ?string $after = null, public readonly ?string $textTransform = null, public readonly string $whiteSpace = 'normal', public readonly ?string $ellipsis = null, int $indent = 0, int $lineGap = 0, int $letterSpacing = 0, public readonly ?string $script = null, public readonly ?Style $firstLetter = null, int $lineClamp = 0, public readonly bool $breakAll = false, public readonly bool $hyphens = true, public readonly bool $balance = false, int $tabSize = 8, public readonly array $box = [0, 0, 0, 0], public readonly ?string $borderLeft = null, public readonly ?string $borderRight = null, int $wordSpacing = 0, public readonly ?TextAlign $alignLast = null, public readonly string $justify = 'auto', ?string $marker = null, float $indentPercent = 0.0, public readonly bool $hanging = false, public readonly bool $eachLine = false, public readonly ?Style $firstLine = null, public readonly bool $cloneDecoration = false, public readonly bool $pretty = false, string $hyphen = '-', public readonly array $autospace = [], public readonly array $hang = [0, 0], public readonly ?array $initialLetter = null, public readonly ?array $hyphenLimits = null, public readonly bool $rtl = false, public readonly ?Style $beforeStyle = null, public readonly ?Style $afterStyle = null, public readonly ?Style $markerStyle = null, public readonly ?string $ruby = null, public readonly bool $rubyUnder = false, public readonly string $rubyAlign = 'space-around', int $orphans = 2, int $widows = 2, public readonly ?Hyphenator $hyphenator = null, public readonly string $unicodeBidi = 'normal', public readonly bool $hangEnd = false, public readonly bool $keepAll = false, public readonly ?string $emphasis = null, public readonly bool $emphasisUnder = false, public readonly ?Style $emphasisStyle = null, public readonly array $highlights = [], public readonly ?array $textShadow = null, ?array $markerImage = null, public readonly bool $atomic = false)
    {
        // Colors of pixels, whatever is given
        $this->markerImage = null === $markerImage ? null : array_values(array_map(static fn (array $cell): array => array_map(static fn (?array $pixel): ?array => null === $pixel ? null : array_map(static fn (mixed $channel): int => max(0, min(255, (int) $channel)), array_values(\array_slice($pixel, 0, 3))), [$cell[0] ?? null, $cell[1] ?? null]), \array_slice($markerImage, 0, 4)));
        $this->orphans = max(1, min(1000, $orphans));
        $this->widows = max(1, min(1000, $widows));
        $this->hyphen = StringUtils::stripControlBytes(StringUtils::sanitizeUtf8(mb_substr($hyphen, 0, 10)));
        // Drawn as is, outside the text runs that are sanitized: no control bytes nor invalid UTF-8
        $this->marker = null !== $marker ? StringUtils::stripControlBytes(StringUtils::sanitizeUtf8($marker)) : null;
        $this->wordSpacing = max(0, min(10, $wordSpacing));
        $this->lineClamp = max(0, min(10000, $lineClamp));
        $this->tabSize = max(1, min(64, $tabSize));
        $this->lineGap = max(0, min(10, $lineGap));
        $this->letterSpacing = max(0, min(10, $letterSpacing));
        // A column count: no line is wider than this, nor should a malformed value allocate without bounds
        $this->indent = max(-10000, min(10000, $indent));
        $this->indentPercent = is_finite($indentPercent) ? max(-100.0, min(100.0, $indentPercent)) : 0.0;
    }

    /**
     * Whether another format has the same values: its styles compared by their values (not the codes computed from
     * them), its other objects being the same ones, like its hyphenator.
     *
     * @internal
     */
    public function equals(self $other): bool
    {
        if ($this === $other) {
            return true;
        }
        $theirs = get_object_vars($other);
        foreach (get_object_vars($this) as $name => $value) {
            $same = match (true) {
                $value instanceof Style => $value->equals($theirs[$name]),
                \is_object($value) && !$value instanceof \UnitEnum => $value === $theirs[$name],
                default => $value === $theirs[$name],
            };
            if (!$same) {
                return false;
            }
        }

        return true;
    }

    /**
     * The columns before the first line of the text block and before the others, for its width.
     *
     * A line cannot start before the block: when text-indent pulls a line
     * out, the others are pushed in as far, which keeps them apart the same.
     *
     * @return array{int, int}
     */
    public function indents(int $columns): array
    {
        $indent = $this->indent + (int) round($this->indentPercent * max(0, $columns) / 100);
        [$first, $others] = $this->hanging ? [0, $indent] : [$indent, 0];
        $shift = max(0, -min($first, $others));

        return [min(10000, $first + $shift), min(10000, $others + $shift)];
    }
}
