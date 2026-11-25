<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\TuiHtml\Internal;

/** @internal */
final class BorderJunctions
{
    public const ARMS = ['─' => 0b11, '│' => 0b1100, '┌' => 0b101, '┐' => 0b110, '└' => 0b1001, '┘' => 0b1010, '├' => 0b1101, '┤' => 0b1110, '┬' => 0b111, '┴' => 0b1011, '┼' => 0b1111, '╭' => 0b101, '╮' => 0b110, '╰' => 0b1001, '╯' => 0b1010];

    /**
     * Extend the sides of adjoining box rows without moving either row.
     *
     * @param array<int, string> $top
     * @param array<int, string> $bottom
     *
     * @return array{array<int, int>, array<int, int>}
     */
    public static function stackedArms(array $top, array $bottom): array
    {
        $sides = static fn (array $line, int $column, int $arms): int => $arms & (0 !== ((self::ARMS[$line[$column - 1] ?? ' '] ?? 0) & 0b1) ? 0b1111 : 0b1101) & (0 !== ((self::ARMS[$line[$column + 1] ?? ' '] ?? 0) & 0b10) ? 0b1111 : 0b1110);
        $upperArms = $lowerArms = [];
        foreach (array_intersect_key($top, $bottom) as $column => $glyph) {
            $under = $bottom[$column];
            if (0 === (self::ARMS[$glyph] & 0b100) && 0 !== (self::ARMS[$under] & 0b1000)) {
                $upperArms[$column] = $sides($top, $column, self::ARMS[$glyph] | 0b100);
            }
            if (0 === (self::ARMS[$under] & 0b1000) && 0 !== (self::ARMS[$glyph] & 0b100)) {
                $lowerArms[$column] = $sides($bottom, $column, self::ARMS[$under] | 0b1000);
            }
        }

        return [$upperArms, $lowerArms];
    }

    public static function glyphOf(int $arms, string $current): string
    {
        return (self::ARMS[$current] ?? null) === $arms ? $current : (array_search($arms, self::ARMS, true) ?: $current);
    }

    /** @return array<int|string, string> Junctions keyed by up, down, left and right arms */
    public static function fromCorner(string $corner): array
    {
        [$h, $v, $topLeft, $topJoin, $topRight, $midLeft, $cross, $midRight, $bottomLeft, $bottomJoin, $bottomRight] = match ($corner) {
            '╔' => ['═', '║', '╔', '╦', '╗', '╠', '╬', '╣', '╚', '╩', '╝'],
            '┏' => ['━', '┃', '┏', '┳', '┓', '┣', '╋', '┫', '┗', '┻', '┛'],
            '╭' => ['─', '│', '╭', '┬', '╮', '├', '┼', '┤', '╰', '┴', '╯'],
            default => ['─', '│', '┌', '┬', '┐', '├', '┼', '┤', '└', '┴', '┘'],
        };

        return ['1111' => $cross, '1110' => $midRight, '1101' => $midLeft, '1100' => $v, '0111' => $topJoin, '1011' => $bottomJoin, '0110' => $topRight, '0101' => $topLeft, '1010' => $bottomRight, '1001' => $bottomLeft, '0011' => $h, '1000' => $v, '0100' => $v, '0010' => $h, '0001' => $h, '0000' => ' '];
    }
}
