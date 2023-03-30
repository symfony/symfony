<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Constraints;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

final class NoBannedWordsValidator extends ConstraintValidator
{
    private const LEET_MAP = [
        'a' => '(a|4|/\\|@|\^|aye|∂|/\-\\|\|\-\\|q)',
        'b' => '(b|8|6|13|\|3|ß|P\>|\|\:|\!3|\(3|/3|\)3)',
        'c' => '(c|\(|¢|\<|\[|©)',
        'd' => '(d|\[\)|\|o|\)|I\>|\|\>|\?|T\)|\|\)|0|\</)',
        'e' => '(e|3|&|€|£|є|ë|\[\-|\|\=\-)',
        'f' => '(f|\|\=|ƒ|\|\#|ph|/\=)',
        'g' => '(g|6|&|\(_\+|9|C\-|gee|\(γ,)',
        'h' => '(h|\#|/\-/|\[\-\]|\]\-\[|\)\-\(|\(\-\)|\:\-\:|\|~\||\|\-\||\]~\[|\}\{|\?|\}|\-\{|hèch)',
        'i' => '(i|1|\!|\||\]\[|eye|3y3|\]|\:)',
        'j' => '(j|_\||_/|¿|\</|\(/|ʝ| ;)',
        'k' => '(k|X|\|\<|\|\{|ɮ|\<|\|\\“)',
        'l' => '(l|1|£|1_|ℓ|\||\|_|\]\[_,)',
        'm' => '(m|\|v\||\[V\]|\{V\}|\|\\/\||/\\/\\|\(u\)|\(V\)|\(\\/\)|/\|\\|\^\^|/\|/\||//\.|\.\\|/\^\^\\|///|\|\^\^\|)',
        'n' => '(n|\^/|\|V|\|\\\||/\\/|\[\\\]|\<\\\>|\{\\\}|\]\\\[|//|\^|\[\]|/V|₪)',
        'o' => '(o|0|\(\)|oh|\[\]|¤|°|\(\[\]\))',
        'p' => '(p|\|\*|\|o|\|º|\|\^\(o\)|\|\>|\|"|9|\[\]D|\|̊|\|7|\?|/\*|¶|\|D)',
        'q' => '(q|\(_,\)|\(\)_|0_|°\||\<\||0\.)',
        'r' => '(r|2|\|\?|/2|\|\^|lz|®|\[z|12|Я|\|2|ʁ|\|²|\.\-|,\-|\|°\\)',
        's' => '(s|5|\$|z|§|ehs|es|_/¯)',
        't' => '(t|7|\+|\-\|\-|1|\'\]\[\'|†|\|²|¯\|¯)',
        'u' => '(u|\(_\)|\|_\||v|L\||µ|J)',
        'v' => '(v|\\/|1/|\|/|o\|o)',
        'w' => '(w|\\/\\/|vv|\'//|\\`|\\\^/|\(n\)|\\V/|\\X/|\\\|/|\\_\|_/|\\_\:_/|Ш|ɰ|`\^/|\\\./)',
        'x' => '(x|\>\<|Ж|\}\{|ecks|×|\)\(|8)',
        'y' => '(y|7|j|`/|Ψ|φ|λ|Ч|¥|\'/)',
        'z' => '(z|≥|2|\=/\=|7_|~/_| %|\>_|\-\\_|\'/_)',
    ];

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof NoBannedWords) {
            throw new UnexpectedTypeException($constraint, NoBannedWords::class);
        }

        if (null === $value || !$constraint->dictionary) {
            return;
        }

        if (!\is_string($value)) {
            throw new UnexpectedValueException($value, 'string');
        }

        $toL33tRegex = fn (string $data): string => implode('', array_map(fn (string $char): string => strtr($char, self::LEET_MAP), str_split($data)));
        $regex = sprintf('{%s}i', implode('|', array_map($toL33tRegex(...), $constraint->dictionary)));

        preg_match_all($regex, $value, $matches);

        if (!$matches = current($matches)) {
            $this->context->buildViolation($constraint->message, [
                '{{ matches }}' => implode(', ', $matches),
                '{{ dictionary }}' => implode(', ', $constraint->dictionary),
            ])
                ->setCode(NoBannedWords::BANNED_WORDS_ERROR)
                ->addViolation();
        }
    }
}
