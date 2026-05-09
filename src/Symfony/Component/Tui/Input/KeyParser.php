<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Input;

/**
 * Parses raw terminal input into key identifiers.
 *
 * Supports both legacy terminal sequences and Kitty keyboard protocol.
 *
 * @experimental
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class KeyParser
{
    private const MOD_SHIFT = 1;
    private const MOD_ALT = 2;
    private const MOD_CTRL = 4;
    private const LOCK_MASK = 192; // Caps Lock + Num Lock

    private const EVENT_PRESS = 1;
    private const EVENT_REPEAT = 2;
    private const EVENT_RELEASE = 3;

    private const CODEPOINTS = [
        'escape' => 27,
        'tab' => 9,
        'enter' => 13,
        'space' => 32,
        'backspace' => 127,
        'kp_enter' => 57414,
    ];

    private const ARROW_CODEPOINTS = [
        'up' => -1,
        'down' => -2,
        'right' => -3,
        'left' => -4,
    ];

    private const FUNCTIONAL_CODEPOINTS = [
        'delete' => -10,
        'insert' => -11,
        'page_up' => -12,
        'page_down' => -13,
        'home' => -14,
        'end' => -15,
    ];

    private const LEGACY_KEY_SEQUENCES = [
        'up' => ["\x1b[A", "\x1bOA"],
        'down' => ["\x1b[B", "\x1bOB"],
        'right' => ["\x1b[C", "\x1bOC"],
        'left' => ["\x1b[D", "\x1bOD"],
        'home' => ["\x1b[H", "\x1bOH", "\x1b[1~", "\x1b[7~"],
        'end' => ["\x1b[F", "\x1bOF", "\x1b[4~", "\x1b[8~"],
        'insert' => ["\x1b[2~"],
        'delete' => ["\x1b[3~"],
        'page_up' => ["\x1b[5~", "\x1b[[5~"],
        'page_down' => ["\x1b[6~", "\x1b[[6~"],
        'clear' => ["\x1b[E", "\x1bOE"],
        'f1' => ["\x1bOP", "\x1b[11~", "\x1b[[A"],
        'f2' => ["\x1bOQ", "\x1b[12~", "\x1b[[B"],
        'f3' => ["\x1bOR", "\x1b[13~", "\x1b[[C"],
        'f4' => ["\x1bOS", "\x1b[14~", "\x1b[[D"],
        'f5' => ["\x1b[15~", "\x1b[[E"],
        'f6' => ["\x1b[17~"],
        'f7' => ["\x1b[18~"],
        'f8' => ["\x1b[19~"],
        'f9' => ["\x1b[20~"],
        'f10' => ["\x1b[21~"],
        'f11' => ["\x1b[23~"],
        'f12' => ["\x1b[24~"],
    ];

    private const LEGACY_FUNCTION_KEY_CODES = [
        'f1' => 11,
        'f2' => 12,
        'f3' => 13,
        'f4' => 14,
        'f5' => 15,
        'f6' => 17,
        'f7' => 18,
        'f8' => 19,
        'f9' => 20,
        'f10' => 21,
        'f11' => 23,
        'f12' => 24,
    ];

    private const LEGACY_FUNCTION_KEY_LETTERS = [
        'f1' => 'P',
        'f2' => 'Q',
        'f3' => 'R',
        'f4' => 'S',
    ];

    private const LEGACY_SHIFT_SEQUENCES = [
        'up' => ["\x1b[a"],
        'down' => ["\x1b[b"],
        'right' => ["\x1b[c"],
        'left' => ["\x1b[d"],
        'clear' => ["\x1b[e"],
        'insert' => ["\x1b[2$"],
        'delete' => ["\x1b[3$"],
        'page_up' => ["\x1b[5$"],
        'page_down' => ["\x1b[6$"],
        'home' => ["\x1b[7$"],
        'end' => ["\x1b[8$"],
    ];

    private const LEGACY_CTRL_SEQUENCES = [
        'up' => ["\x1bOa"],
        'down' => ["\x1bOb"],
        'right' => ["\x1bOc"],
        'left' => ["\x1bOd"],
        'clear' => ["\x1bOe"],
        'insert' => ["\x1b[2^"],
        'delete' => ["\x1b[3^"],
        'page_up' => ["\x1b[5^"],
        'page_down' => ["\x1b[6^"],
        'home' => ["\x1b[7^"],
        'end' => ["\x1b[8^"],
    ];

    private const LEGACY_SEQUENCE_KEY_IDS = [
        "\x1bOA" => 'up',
        "\x1bOB" => 'down',
        "\x1bOC" => 'right',
        "\x1bOD" => 'left',
        "\x1bOH" => 'home',
        "\x1bOF" => 'end',
        "\x1b[E" => 'clear',
        "\x1bOE" => 'clear',
        "\x1bOe" => 'ctrl+clear',
        "\x1b[e" => 'shift+clear',
        "\x1b[2~" => 'insert',
        "\x1b[2$" => 'shift+insert',
        "\x1b[2^" => 'ctrl+insert',
        "\x1b[3$" => 'shift+delete',
        "\x1b[3^" => 'ctrl+delete',
        "\x1b[[5~" => 'page_up',
        "\x1b[[6~" => 'page_down',
        "\x1b[a" => 'shift+up',
        "\x1b[b" => 'shift+down',
        "\x1b[c" => 'shift+right',
        "\x1b[d" => 'shift+left',
        "\x1bOa" => 'ctrl+up',
        "\x1bOb" => 'ctrl+down',
        "\x1bOc" => 'ctrl+right',
        "\x1bOd" => 'ctrl+left',
        "\x1b[5$" => 'shift+page_up',
        "\x1b[6$" => 'shift+page_down',
        "\x1b[7$" => 'shift+home',
        "\x1b[8$" => 'shift+end',
        "\x1b[5^" => 'ctrl+page_up',
        "\x1b[6^" => 'ctrl+page_down',
        "\x1b[7^" => 'ctrl+home',
        "\x1b[8^" => 'ctrl+end',
        "\x1bOP" => 'f1',
        "\x1bOQ" => 'f2',
        "\x1bOR" => 'f3',
        "\x1bOS" => 'f4',
        "\x1b[11~" => 'f1',
        "\x1b[12~" => 'f2',
        "\x1b[13~" => 'f3',
        "\x1b[14~" => 'f4',
        "\x1b[[A" => 'f1',
        "\x1b[[B" => 'f2',
        "\x1b[[C" => 'f3',
        "\x1b[[D" => 'f4',
        "\x1b[[E" => 'f5',
        "\x1b[15~" => 'f5',
        "\x1b[17~" => 'f6',
        "\x1b[18~" => 'f7',
        "\x1b[19~" => 'f8',
        "\x1b[20~" => 'f9',
        "\x1b[21~" => 'f10',
        "\x1b[23~" => 'f11',
        "\x1b[24~" => 'f12',
        "\x1bb" => 'alt+left',
        "\x1bf" => 'alt+right',
        "\x1bp" => 'alt+up',
        "\x1bn" => 'alt+down',
    ];

    private const SYMBOL_KEYS = [
        '`', '-', '=', '[', ']', '\\', ';', "'", ',', '.', '/',
        '!', '@', '#', '$', '%', '^', '&', '*', '(', ')', '_', '+',
        '|', '~', '{', '}', ':', '<', '>', '?',
    ];

    private bool $kittyProtocolActive = false;

    public function setKittyProtocolActive(bool $active): void
    {
        $this->kittyProtocolActive = $active;
    }

    public function isKittyProtocolActive(): bool
    {
        return $this->kittyProtocolActive;
    }

    /**
     * Parse raw input and return the key identifier.
     *
     * @return array{key: string, modifiers: array<string>, event_type: int}|null
     */
    public function parse(string $data): ?array
    {
        $parsed = $this->parseKey($data);
        if (null === $parsed) {
            return null;
        }

        $key = $parsed['key'];
        $modifiers = [];

        if (str_contains($key, '+')) {
            $parts = explode('+', $key);
            $keyPart = array_pop($parts);
            $modifiers = $parts;
            $key = $parts ? implode('+', $parts).'+'.$keyPart : $keyPart;
        }

        return [
            'key' => $key,
            'modifiers' => $modifiers,
            'event_type' => $parsed['event_type'],
        ];
    }

    /**
     * Check if input matches a key identifier.
     */
    public function matches(string $data, string $keyId): bool
    {
        if ($this->isKeyRelease($data)) {
            return false;
        }

        return $this->matchesKey($data, $keyId);
    }

    public function isKeyRelease(string $data): bool
    {
        if (str_contains($data, "\x1b[200~")) {
            return false;
        }

        return preg_match('/:3[u~ABCDHF]$/', $data);
    }

    public function isKeyRepeat(string $data): bool
    {
        if (str_contains($data, "\x1b[200~")) {
            return false;
        }

        return preg_match('/:2[u~ABCDHF]$/', $data);
    }

    /**
     * Parse input data into a key identifier and event type.
     *
     * @return array{key: string, event_type: int}|null
     */
    private function parseKey(string $data): ?array
    {
        if ('' === $data) {
            return null;
        }

        if (
            $this->kittyProtocolActive
            || (str_starts_with($data, "\x1b[") && (str_ends_with($data, 'u') || str_contains($data, ':')))
        ) {
            $kitty = $this->parseKittySequence($data);
            if (null !== $kitty && null !== $keyName = $this->keyNameFromCodepoint($kitty['codepoint'])) {
                $mods = $this->modsFromFlags($kitty['modifier']);
                $key = $mods ? implode('+', $mods).'+'.$keyName : $keyName;

                return ['key' => $key, 'event_type' => $kitty['event_type']];
            }
        }

        if ($this->kittyProtocolActive && ("\x1b\r" === $data || "\n" === $data)) {
            return ['key' => 'shift+enter', 'event_type' => self::EVENT_PRESS];
        }

        if (isset(self::LEGACY_SEQUENCE_KEY_IDS[$data])) {
            return ['key' => self::LEGACY_SEQUENCE_KEY_IDS[$data], 'event_type' => self::EVENT_PRESS];
        }

        $press = static fn ($key) => ['key' => $key, 'event_type' => self::EVENT_PRESS];

        $matched = match ($data) {
            "\x1b" => $press('escape'),
            "\x1c" => $press('ctrl+\\'),
            "\x1d" => $press('ctrl+]'),
            "\x1f" => $press('ctrl+-'),
            "\x1b\x1b" => $press('ctrl+alt+['),
            "\x1b\x1c" => $press('ctrl+alt+\\'),
            "\x1b\x1d" => $press('ctrl+alt+]'),
            "\x1b\x1f" => $press('ctrl+alt+-'),
            "\t" => $press('tab'),
            "\r", "\x1bOM" => $press('enter'),
            "\x00" => $press('ctrl+space'),
            ' ' => $press('space'),
            "\x7f", "\x08" => $press('backspace'),
            "\x1b[Z" => $press('shift+tab'),
            "\x1b\x7f", "\x1b\x08" => $press('alt+backspace'),
            "\x1b[A" => $press('up'),
            "\x1b[B" => $press('down'),
            "\x1b[C" => $press('right'),
            "\x1b[D" => $press('left'),
            "\x1b[H" => $press('home'),
            "\x1b[F" => $press('end'),
            "\x1b[3~" => $press('delete'),
            "\x1b[5~" => $press('page_up'),
            "\x1b[6~" => $press('page_down'),
            default => null,
        };
        if (null !== $matched) {
            return $matched;
        }

        if (!$this->kittyProtocolActive && "\n" === $data) {
            return $press('enter');
        }
        if (!$this->kittyProtocolActive) {
            $matched = match ($data) {
                "\x1b\r" => $press('alt+enter'),
                "\x1b " => $press('alt+space'),
                "\x1bB" => $press('alt+left'),
                "\x1bF" => $press('alt+right'),
                default => null,
            };
            if (null !== $matched) {
                return $matched;
            }

            if (2 === \strlen($data) && "\x1b" === $data[0]) {
                $code = \ord($data[1]);
                if ($code >= 1 && $code <= 26) {
                    return $press('ctrl+alt+'.\chr($code + 96));
                }
                if (($code >= 48 && $code <= 57) || ($code >= 97 && $code <= 122)) {
                    return $press('alt+'.\chr($code));
                }
            }
        }

        if (1 === \strlen($data)) {
            $code = \ord($data);
            if ($code >= 1 && $code <= 26) {
                return $press('ctrl+'.\chr($code + 96));
            }
            if ($code >= 32 && $code <= 126) {
                return $press($data);
            }
        }

        if (\strlen($data) > 1 && !str_starts_with($data, "\x1b")) {
            return $press($data);
        }

        return null;
    }

    /**
     * @return array{codepoint: int, modifier: int, event_type: int}|null
     */
    private function parseKittySequence(string $data): ?array
    {
        // Format: ESC [ codepoint[:shifted_key[:base_layout_key]] [;modifiers[:event_type]] u
        // We parse the full syntax but only use the codepoint (logical key)
        // for key resolution. The base_layout_key (US QWERTY physical position)
        // is intentionally ignored; keybindings must follow the logical layout
        // so that e.g. Ctrl+W means Ctrl+W on every keyboard layout.
        if (preg_match('/^\x1b\[(\d+)(?::(\d*))?(?::(\d+))?(?:;(\d+))?(?::(\d+))?u$/', $data, $match)) {
            $codepoint = (int) $match[1];
            $modifierValue = isset($match[4]) && '' !== $match[4] ? (int) $match[4] : 1;
            $eventType = $this->parseEventType($match[5] ?? null);

            return [
                'codepoint' => $codepoint,
                'modifier' => $modifierValue - 1,
                'event_type' => $eventType,
            ];
        }

        if (preg_match('/^\x1b\[1;(\d+)(?::(\d+))?([ABCD])$/', $data, $match)) {
            $modifierValue = (int) $match[1];
            $eventType = $this->parseEventType('' !== $match[2] ? $match[2] : null);
            $arrowCodes = [
                'A' => self::ARROW_CODEPOINTS['up'],
                'B' => self::ARROW_CODEPOINTS['down'],
                'C' => self::ARROW_CODEPOINTS['right'],
                'D' => self::ARROW_CODEPOINTS['left'],
            ];

            return [
                'codepoint' => $arrowCodes[$match[3]],
                'modifier' => $modifierValue - 1,
                'event_type' => $eventType,
            ];
        }

        if (preg_match('/^\x1b\[(\d+)(?:;(\d+))?(?::(\d+))?~$/', $data, $match)) {
            $keyNum = (int) $match[1];
            $modifierValue = isset($match[2]) && '' !== $match[2] ? (int) $match[2] : 1;
            $eventType = $this->parseEventType($match[3] ?? null);
            $funcCodes = [
                2 => self::FUNCTIONAL_CODEPOINTS['insert'],
                3 => self::FUNCTIONAL_CODEPOINTS['delete'],
                5 => self::FUNCTIONAL_CODEPOINTS['page_up'],
                6 => self::FUNCTIONAL_CODEPOINTS['page_down'],
                7 => self::FUNCTIONAL_CODEPOINTS['home'],
                8 => self::FUNCTIONAL_CODEPOINTS['end'],
            ];

            if (isset($funcCodes[$keyNum])) {
                return [
                    'codepoint' => $funcCodes[$keyNum],
                    'modifier' => $modifierValue - 1,
                    'event_type' => $eventType,
                ];
            }
        }

        if (!preg_match('/^\x1b\[1;(\d+)(?::(\d+))?([HF])$/', $data, $match)) {
            return null;
        }
        $modifierValue = (int) $match[1];
        $eventType = $this->parseEventType('' !== $match[2] ? $match[2] : null);
        $codepoint = self::FUNCTIONAL_CODEPOINTS['H' === $match[3] ? 'home' : 'end'];

        return [
            'codepoint' => $codepoint,
            'modifier' => $modifierValue - 1,
            'event_type' => $eventType,
        ];
    }

    private function parseEventType(?string $eventTypeStr): int
    {
        if (null === $eventTypeStr || '' === $eventTypeStr) {
            return self::EVENT_PRESS;
        }

        return match ((int) $eventTypeStr) {
            self::EVENT_REPEAT => self::EVENT_REPEAT,
            self::EVENT_RELEASE => self::EVENT_RELEASE,
            default => self::EVENT_PRESS,
        };
    }

    private function keyNameFromCodepoint(int $codepoint): ?string
    {
        return match ($codepoint) {
            self::CODEPOINTS['escape'] => 'escape',
            self::CODEPOINTS['tab'] => 'tab',
            self::CODEPOINTS['enter'], self::CODEPOINTS['kp_enter'] => 'enter',
            self::CODEPOINTS['space'] => 'space',
            self::CODEPOINTS['backspace'] => 'backspace',
            self::FUNCTIONAL_CODEPOINTS['delete'] => 'delete',
            self::FUNCTIONAL_CODEPOINTS['insert'] => 'insert',
            self::FUNCTIONAL_CODEPOINTS['home'] => 'home',
            self::FUNCTIONAL_CODEPOINTS['end'] => 'end',
            self::FUNCTIONAL_CODEPOINTS['page_up'] => 'page_up',
            self::FUNCTIONAL_CODEPOINTS['page_down'] => 'page_down',
            self::ARROW_CODEPOINTS['up'] => 'up',
            self::ARROW_CODEPOINTS['down'] => 'down',
            self::ARROW_CODEPOINTS['left'] => 'left',
            self::ARROW_CODEPOINTS['right'] => 'right',
            default => $this->keyNameFromChar($codepoint),
        };
    }

    private function keyNameFromChar(int $codepoint): ?string
    {
        if (($codepoint >= 48 && $codepoint <= 57) || ($codepoint >= 97 && $codepoint <= 122)) {
            return \chr($codepoint);
        }

        if ($codepoint < 0 || $codepoint > 255) {
            return null;
        }

        $char = \chr($codepoint);

        return \in_array($char, self::SYMBOL_KEYS, true) ? $char : null;
    }

    /**
     * @return string[]
     */
    private function modsFromFlags(int $modifier): array
    {
        $mods = [];
        $effective = $modifier & ~self::LOCK_MASK;
        if ($effective & self::MOD_SHIFT) {
            $mods[] = 'shift';
        }
        if ($effective & self::MOD_CTRL) {
            $mods[] = 'ctrl';
        }
        if ($effective & self::MOD_ALT) {
            $mods[] = 'alt';
        }

        return $mods;
    }

    private function matchesKey(string $data, string $keyId): bool
    {
        $parsed = $this->parseKeyId($keyId);
        if (null === $parsed) {
            return false;
        }

        $key = $parsed['key'];
        $ctrl = $parsed['ctrl'];
        $shift = $parsed['shift'];
        $alt = $parsed['alt'];

        $modifier = 0;
        if ($shift) {
            $modifier |= self::MOD_SHIFT;
        }
        if ($alt) {
            $modifier |= self::MOD_ALT;
        }
        if ($ctrl) {
            $modifier |= self::MOD_CTRL;
        }

        switch ($key) {
            case 'escape':
            case 'esc':
                if (0 !== $modifier) {
                    return false;
                }

                return "\x1b" === $data || $this->matchesKittySequence($data, self::CODEPOINTS['escape'], 0);

            case 'space':
                if (!$this->kittyProtocolActive) {
                    if ($ctrl && !$alt && !$shift && "\x00" === $data) {
                        return true;
                    }
                    if ($alt && !$ctrl && !$shift && "\x1b " === $data) {
                        return true;
                    }
                }
                if (0 === $modifier) {
                    return ' ' === $data || $this->matchesKittySequence($data, self::CODEPOINTS['space'], 0);
                }

                return $this->matchesKittySequence($data, self::CODEPOINTS['space'], $modifier);

            case 'tab':
                if ($shift && !$ctrl && !$alt) {
                    return "\x1b[Z" === $data || $this->matchesKittySequence($data, self::CODEPOINTS['tab'], self::MOD_SHIFT);
                }
                if (0 === $modifier) {
                    return "\t" === $data || $this->matchesKittySequence($data, self::CODEPOINTS['tab'], 0);
                }

                return $this->matchesKittySequence($data, self::CODEPOINTS['tab'], $modifier);

            case 'enter':
            case 'return':
                if ($shift && !$ctrl && !$alt) {
                    if (
                        $this->matchesKittySequence($data, self::CODEPOINTS['enter'], self::MOD_SHIFT)
                        || $this->matchesKittySequence($data, self::CODEPOINTS['kp_enter'], self::MOD_SHIFT)
                    ) {
                        return true;
                    }
                    if ($this->matchesModifyOtherKeys($data, self::CODEPOINTS['enter'], self::MOD_SHIFT)) {
                        return true;
                    }
                    if ($this->kittyProtocolActive) {
                        return "\x1b\r" === $data || "\n" === $data;
                    }

                    return false;
                }
                if ($alt && !$ctrl && !$shift) {
                    if (
                        $this->matchesKittySequence($data, self::CODEPOINTS['enter'], self::MOD_ALT)
                        || $this->matchesKittySequence($data, self::CODEPOINTS['kp_enter'], self::MOD_ALT)
                    ) {
                        return true;
                    }
                    if ($this->matchesModifyOtherKeys($data, self::CODEPOINTS['enter'], self::MOD_ALT)) {
                        return true;
                    }
                    if (!$this->kittyProtocolActive) {
                        return "\x1b\r" === $data;
                    }

                    return false;
                }
                if (0 === $modifier) {
                    return "\r" === $data
                        || (!$this->kittyProtocolActive && "\n" === $data)
                        || "\x1bOM" === $data
                        || $this->matchesKittySequence($data, self::CODEPOINTS['enter'], 0)
                        || $this->matchesKittySequence($data, self::CODEPOINTS['kp_enter'], 0);
                }

                return $this->matchesKittySequence($data, self::CODEPOINTS['enter'], $modifier)
                    || $this->matchesKittySequence($data, self::CODEPOINTS['kp_enter'], $modifier);

            case 'backspace':
                if ($alt && !$ctrl && !$shift) {
                    if ("\x1b\x7f" === $data || "\x1b\x08" === $data) {
                        return true;
                    }

                    return $this->matchesKittySequence($data, self::CODEPOINTS['backspace'], self::MOD_ALT);
                }
                if (0 === $modifier) {
                    return "\x7f" === $data || "\x08" === $data || $this->matchesKittySequence($data, self::CODEPOINTS['backspace'], 0);
                }

                return $this->matchesKittySequence($data, self::CODEPOINTS['backspace'], $modifier);

            case 'insert':
                if (0 === $modifier) {
                    return $this->matchesLegacySequence($data, self::LEGACY_KEY_SEQUENCES['insert'])
                        || $this->matchesKittySequence($data, self::FUNCTIONAL_CODEPOINTS['insert'], 0);
                }
                if ($this->matchesLegacyModifierSequence($data, 'insert', $modifier)) {
                    return true;
                }

                return $this->matchesKittySequence($data, self::FUNCTIONAL_CODEPOINTS['insert'], $modifier);

            case 'delete':
                if (0 === $modifier) {
                    return $this->matchesLegacySequence($data, self::LEGACY_KEY_SEQUENCES['delete'])
                        || $this->matchesKittySequence($data, self::FUNCTIONAL_CODEPOINTS['delete'], 0);
                }
                if ($this->matchesLegacyModifierSequence($data, 'delete', $modifier)) {
                    return true;
                }

                return $this->matchesKittySequence($data, self::FUNCTIONAL_CODEPOINTS['delete'], $modifier);

            case 'clear':
                if (0 === $modifier) {
                    return $this->matchesLegacySequence($data, self::LEGACY_KEY_SEQUENCES['clear']);
                }

                return $this->matchesLegacyModifierSequence($data, 'clear', $modifier);

            case 'home':
                if (0 === $modifier) {
                    return $this->matchesLegacySequence($data, self::LEGACY_KEY_SEQUENCES['home'])
                        || $this->matchesKittySequence($data, self::FUNCTIONAL_CODEPOINTS['home'], 0);
                }
                if ($this->matchesLegacyModifierSequence($data, 'home', $modifier)) {
                    return true;
                }

                return $this->matchesKittySequence($data, self::FUNCTIONAL_CODEPOINTS['home'], $modifier);

            case 'end':
                if (0 === $modifier) {
                    return $this->matchesLegacySequence($data, self::LEGACY_KEY_SEQUENCES['end'])
                        || $this->matchesKittySequence($data, self::FUNCTIONAL_CODEPOINTS['end'], 0);
                }
                if ($this->matchesLegacyModifierSequence($data, 'end', $modifier)) {
                    return true;
                }

                return $this->matchesKittySequence($data, self::FUNCTIONAL_CODEPOINTS['end'], $modifier);

            case 'page_up':
                if (0 === $modifier) {
                    return $this->matchesLegacySequence($data, self::LEGACY_KEY_SEQUENCES['page_up'])
                        || $this->matchesKittySequence($data, self::FUNCTIONAL_CODEPOINTS['page_up'], 0);
                }
                if ($this->matchesLegacyModifierSequence($data, 'page_up', $modifier)) {
                    return true;
                }

                return $this->matchesKittySequence($data, self::FUNCTIONAL_CODEPOINTS['page_up'], $modifier);

            case 'page_down':
                if (0 === $modifier) {
                    return $this->matchesLegacySequence($data, self::LEGACY_KEY_SEQUENCES['page_down'])
                        || $this->matchesKittySequence($data, self::FUNCTIONAL_CODEPOINTS['page_down'], 0);
                }
                if ($this->matchesLegacyModifierSequence($data, 'page_down', $modifier)) {
                    return true;
                }

                return $this->matchesKittySequence($data, self::FUNCTIONAL_CODEPOINTS['page_down'], $modifier);

            case 'up':
                if ($alt && !$ctrl && !$shift) {
                    return "\x1bp" === $data || $this->matchesKittySequence($data, self::ARROW_CODEPOINTS['up'], self::MOD_ALT);
                }
                if (0 === $modifier) {
                    return $this->matchesLegacySequence($data, self::LEGACY_KEY_SEQUENCES['up'])
                        || $this->matchesKittySequence($data, self::ARROW_CODEPOINTS['up'], 0);
                }
                if ($this->matchesLegacyModifierSequence($data, 'up', $modifier)) {
                    return true;
                }

                return $this->matchesKittySequence($data, self::ARROW_CODEPOINTS['up'], $modifier);

            case 'down':
                if ($alt && !$ctrl && !$shift) {
                    return "\x1bn" === $data || $this->matchesKittySequence($data, self::ARROW_CODEPOINTS['down'], self::MOD_ALT);
                }
                if (0 === $modifier) {
                    return $this->matchesLegacySequence($data, self::LEGACY_KEY_SEQUENCES['down'])
                        || $this->matchesKittySequence($data, self::ARROW_CODEPOINTS['down'], 0);
                }
                if ($this->matchesLegacyModifierSequence($data, 'down', $modifier)) {
                    return true;
                }

                return $this->matchesKittySequence($data, self::ARROW_CODEPOINTS['down'], $modifier);

            case 'left':
                if ($alt && !$ctrl && !$shift) {
                    return "\x1b[1;3D" === $data
                        || (!$this->kittyProtocolActive && "\x1bB" === $data)
                        || "\x1bb" === $data
                        || $this->matchesKittySequence($data, self::ARROW_CODEPOINTS['left'], self::MOD_ALT);
                }
                if ($ctrl && !$alt && !$shift) {
                    return "\x1b[1;5D" === $data
                        || $this->matchesLegacyModifierSequence($data, 'left', self::MOD_CTRL)
                        || $this->matchesKittySequence($data, self::ARROW_CODEPOINTS['left'], self::MOD_CTRL);
                }
                if (0 === $modifier) {
                    return $this->matchesLegacySequence($data, self::LEGACY_KEY_SEQUENCES['left'])
                        || $this->matchesKittySequence($data, self::ARROW_CODEPOINTS['left'], 0);
                }
                if ($this->matchesLegacyModifierSequence($data, 'left', $modifier)) {
                    return true;
                }

                return $this->matchesKittySequence($data, self::ARROW_CODEPOINTS['left'], $modifier);

            case 'right':
                if ($alt && !$ctrl && !$shift) {
                    return "\x1b[1;3C" === $data
                        || (!$this->kittyProtocolActive && "\x1bF" === $data)
                        || "\x1bf" === $data
                        || $this->matchesKittySequence($data, self::ARROW_CODEPOINTS['right'], self::MOD_ALT);
                }
                if ($ctrl && !$alt && !$shift) {
                    return "\x1b[1;5C" === $data
                        || $this->matchesLegacyModifierSequence($data, 'right', self::MOD_CTRL)
                        || $this->matchesKittySequence($data, self::ARROW_CODEPOINTS['right'], self::MOD_CTRL);
                }
                if (0 === $modifier) {
                    return $this->matchesLegacySequence($data, self::LEGACY_KEY_SEQUENCES['right'])
                        || $this->matchesKittySequence($data, self::ARROW_CODEPOINTS['right'], 0);
                }
                if ($this->matchesLegacyModifierSequence($data, 'right', $modifier)) {
                    return true;
                }

                return $this->matchesKittySequence($data, self::ARROW_CODEPOINTS['right'], $modifier);

            case 'f1':
            case 'f2':
            case 'f3':
            case 'f4':
            case 'f5':
            case 'f6':
            case 'f7':
            case 'f8':
            case 'f9':
            case 'f10':
            case 'f11':
            case 'f12':
                if (0 !== $modifier) {
                    return $this->matchesLegacyFunctionKeyModifierSequence($data, $key, $modifier);
                }

                return $this->matchesLegacySequence($data, self::LEGACY_KEY_SEQUENCES[$key]);
        }

        $isDigit = 1 === \strlen($key) && $key >= '0' && $key <= '9';
        if (1 === \strlen($key) && (($key >= 'a' && $key <= 'z') || $isDigit || \in_array($key, self::SYMBOL_KEYS, true))) {
            $codepoint = \ord($key);
            $rawCtrl = $this->rawCtrlChar($key);

            if ($ctrl && $alt && !$shift && !$this->kittyProtocolActive && null !== $rawCtrl) {
                return "\x1b".$rawCtrl === $data;
            }

            if ($alt && !$ctrl && !$shift && !$this->kittyProtocolActive && (($key >= 'a' && $key <= 'z') || $isDigit)) {
                if ("\x1b".$key === $data) {
                    return true;
                }
            }

            if ($ctrl && !$shift && !$alt) {
                if (null !== $rawCtrl && $rawCtrl === $data) {
                    return true;
                }

                return $this->matchesKittySequence($data, $codepoint, self::MOD_CTRL);
            }

            if ($ctrl && $shift && !$alt) {
                return $this->matchesKittySequence($data, $codepoint, self::MOD_SHIFT + self::MOD_CTRL);
            }

            if ($shift && !$ctrl && !$alt) {
                if (strtoupper($key) === $data) {
                    return true;
                }

                return $this->matchesKittySequence($data, $codepoint, self::MOD_SHIFT);
            }

            if (0 !== $modifier) {
                return $this->matchesKittySequence($data, $codepoint, $modifier);
            }

            return $data === $key || $this->matchesKittySequence($data, $codepoint, 0);
        }

        return false;
    }

    /**
     * @param string[] $sequences
     */
    private function matchesLegacySequence(string $data, array $sequences): bool
    {
        return \in_array($data, $sequences, true);
    }

    private function matchesLegacyModifierSequence(string $data, string $key, int $modifier): bool
    {
        return match ($modifier) {
            self::MOD_SHIFT => $this->matchesLegacySequence($data, self::LEGACY_SHIFT_SEQUENCES[$key] ?? []),
            self::MOD_CTRL => $this->matchesLegacySequence($data, self::LEGACY_CTRL_SEQUENCES[$key] ?? []),
            default => false,
        };
    }

    private function matchesLegacyFunctionKeyModifierSequence(string $data, string $key, int $modifier): bool
    {
        $modValue = $modifier + 1;

        if (isset(self::LEGACY_FUNCTION_KEY_LETTERS[$key])) {
            $letter = self::LEGACY_FUNCTION_KEY_LETTERS[$key];
            if ("\x1b[1;{$modValue}{$letter}" === $data) {
                return true;
            }
        }

        if (isset(self::LEGACY_FUNCTION_KEY_CODES[$key])) {
            $code = self::LEGACY_FUNCTION_KEY_CODES[$key];
            if ("\x1b[{$code};{$modValue}~" === $data) {
                return true;
            }
        }

        return false;
    }

    private function matchesKittySequence(string $data, int $expectedCodepoint, int $expectedModifier): bool
    {
        $parsed = $this->parseKittySequence($data);
        if (null === $parsed) {
            return false;
        }

        $actualMod = $parsed['modifier'] & ~self::LOCK_MASK;
        $expectedMod = $expectedModifier & ~self::LOCK_MASK;

        if ($actualMod !== $expectedMod) {
            return false;
        }

        return $parsed['codepoint'] === $expectedCodepoint;
    }

    private function matchesModifyOtherKeys(string $data, int $expectedKeycode, int $expectedModifier): bool
    {
        if (!preg_match('/^\x1b\[27;(\d+);(\d+)~$/', $data, $match)) {
            return false;
        }

        $modValue = (int) $match[1];
        $keycode = (int) $match[2];
        $actualMod = $modValue - 1;

        return $keycode === $expectedKeycode && $actualMod === $expectedModifier;
    }

    private function rawCtrlChar(string $key): ?string
    {
        $char = strtolower($key);
        $code = \ord($char);

        if (($code >= 97 && $code <= 122) || '[' === $char || '\\' === $char || ']' === $char || '_' === $char) {
            return \chr($code & 0x1F);
        }

        if ('-' === $char) {
            return \chr(31);
        }

        return null;
    }

    /**
     * @return array{key: string, ctrl: bool, shift: bool, alt: bool}|null
     */
    private function parseKeyId(string $keyId): ?array
    {
        // Special case: the '+' key itself
        if ('+' === $keyId) {
            return ['key' => '+', 'ctrl' => false, 'shift' => false, 'alt' => false];
        }

        $parts = explode('+', strtolower($keyId));
        $key = $parts[\count($parts) - 1] ?? '';
        if ('' === $key) {
            return null;
        }

        return [
            'key' => $key,
            'ctrl' => \in_array('ctrl', $parts, true),
            'shift' => \in_array('shift', $parts, true),
            'alt' => \in_array('alt', $parts, true),
        ];
    }
}
