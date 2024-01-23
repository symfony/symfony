<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Workflow\Dumper;

use Symfony\Component\Workflow\Definition;
use Symfony\Component\Workflow\Exception\InvalidArgumentException;
use Symfony\Component\Workflow\Marking;

class MermaidDumper implements DumperInterface
{
    public const DIRECTION_TOP_TO_BOTTOM = 'TB';
    public const DIRECTION_TOP_DOWN = 'TD';
    public const DIRECTION_BOTTOM_TO_TOP = 'BT';
    public const DIRECTION_RIGHT_TO_LEFT = 'RL';
    public const DIRECTION_LEFT_TO_RIGHT = 'LR';

    private const VALID_DIRECTIONS = [
        self::DIRECTION_TOP_TO_BOTTOM,
        self::DIRECTION_TOP_DOWN,
        self::DIRECTION_BOTTOM_TO_TOP,
        self::DIRECTION_RIGHT_TO_LEFT,
        self::DIRECTION_LEFT_TO_RIGHT,
    ];

    public const TRANSITION_TYPE_STATEMACHINE = 'statemachine';
    public const TRANSITION_TYPE_WORKFLOW = 'workflow';

    private const VALID_TRANSITION_TYPES = [
        self::TRANSITION_TYPE_STATEMACHINE,
        self::TRANSITION_TYPE_WORKFLOW,
    ];

    private string $direction;
    private string $transitionType;

    /**
     * Just tracking the transition id is in some cases inaccurate to
     * get the link's number for styling purposes.
     */
    private int $linkCount = 0;

    public function __construct(string $transitionType, string $direction = self::DIRECTION_LEFT_TO_RIGHT)
    {
        $this->validateDirection($direction);
        $this->validateTransitionType($transitionType);

        $this->direction = $direction;
        $this->transitionType = $transitionType;
    }

    public function dump(Definition $definition, ?Marking $marking = null, array $options = []): string
    {
        $this->linkCount = 0;
        $placeNameMap = [];
        $placeId = 0;

        $output = ['graph '.$this->direction];

        $meta = $definition->getMetadataStore();

        foreach ($definition->getPlaces() as $place) {
            [$placeNodeName, $placeNode, $placeStyle] = $this->preparePlace(
                $placeId,
                $place,
                $meta->getPlaceMetadata($place),
                \in_array($place, $definition->getInitialPlaces()),
                $marking?->has($place) ?? false
            );

            $output[] = $placeNode;

            if ('' !== $placeStyle) {
                $output[] = $placeStyle;
            }

            $placeNameMap[$place] = $placeNodeName;

            ++$placeId;
        }

        foreach ($definition->getTransitions() as $transitionId => $transition) {
            $transitionMeta = $meta->getTransitionMetadata($transition);

            $transitionLabel = $transition->getName();
            if (\array_key_exists('label', $transitionMeta)) {
                $transitionLabel = $transitionMeta['label'];
            }

            foreach ($transition->getFroms() as $from) {
                $from = $placeNameMap[$from];

                foreach ($transition->getTos() as $to) {
                    $to = $placeNameMap[$to];

                    if (self::TRANSITION_TYPE_STATEMACHINE === $this->transitionType) {
                        $transitionOutput = $this->styleStateMachineTransition($from, $to, $transitionLabel, $transitionMeta);
                    } else {
                        $transitionOutput = $this->styleWorkflowTransition($from, $to, $transitionId, $transitionLabel, $transitionMeta);
                    }

                    foreach ($transitionOutput as $line) {
                        if (\in_array($line, $output)) {
                            // additional links must be decremented again to align the styling
                            if (0 < strpos($line, '-->')) {
                                --$this->linkCount;
                            }

                            continue;
                        }

                        $output[] = $line;
                    }
                }
            }
        }

        return implode("\n", $output);
    }

    private function preparePlace(int $placeId, string $placeName, array $meta, bool $isInitial, bool $hasMarking): array
    {
        $placeLabel = $placeName;
        if (\array_key_exists('label', $meta)) {
            $placeLabel = $meta['label'];
        }

        $placeLabel = $this->escape($placeLabel);

        $labelShape = '((%s))';
        if ($isInitial) {
            $labelShape = '([%s])';
        }

        $placeNodeName = 'place'.$placeId;
        $placeNodeFormat = '%s'.$labelShape;
        $placeNode = sprintf($placeNodeFormat, $placeNodeName, $placeLabel);

        $placeStyle = $this->styleNode($meta, $placeNodeName, $hasMarking);

        return [$placeNodeName, $placeNode, $placeStyle];
    }

    private function styleNode(array $meta, string $nodeName, bool $hasMarking = false): string
    {
        $nodeStyles = [];

        if (\array_key_exists('bg_color', $meta)) {
            $nodeStyles[] = sprintf(
                'fill:%s',
                $meta['bg_color']
            );
        }

        if ($hasMarking) {
            $nodeStyles[] = 'stroke-width:4px';
        }

        if (0 === \count($nodeStyles)) {
            return '';
        }

        return sprintf('style %s %s', $nodeName, implode(',', $nodeStyles));
    }

    /**
     * Replace double quotes with the mermaid escape syntax and
     * ensure all other characters are properly escaped.
     */
    private function escape(string $label): string
    {
        $label = str_replace('"', '#quot;', $label);

        return sprintf('"%s"', $label);
    }

    public function validateDirection(string $direction): void
    {
        if (!\in_array($direction, self::VALID_DIRECTIONS, true)) {
            throw new InvalidArgumentException(sprintf('Direction "%s" is not valid, valid directions are: "%s".', $direction, implode(', ', self::VALID_DIRECTIONS)));
        }
    }

    private function validateTransitionType(string $transitionType): void
    {
        if (!\in_array($transitionType, self::VALID_TRANSITION_TYPES, true)) {
            throw new InvalidArgumentException(sprintf('Transition type "%s" is not valid, valid types are: "%s".', $transitionType, implode(', ', self::VALID_TRANSITION_TYPES)));
        }
    }

    private function styleStateMachineTransition(string $from, string $to, string $transitionLabel, array $transitionMeta): array
    {
        $transitionOutput = [sprintf('%s-->|%s|%s', $from, str_replace("\n", ' ', $this->escape($transitionLabel)), $to)];

        $linkStyle = $this->styleLink($transitionMeta);
        if ('' !== $linkStyle) {
            $transitionOutput[] = $linkStyle;
        }

        ++$this->linkCount;

        return $transitionOutput;
    }

    private function styleWorkflowTransition(string $from, string $to, int $transitionId, string $transitionLabel, array $transitionMeta): array
    {
        $transitionOutput = [];

        $transitionLabel = $this->escape($transitionLabel);
        $transitionNodeName = 'transition'.$transitionId;

        $transitionOutput[] = sprintf('%s[%s]', $transitionNodeName, $transitionLabel);

        $transitionNodeStyle = $this->styleNode($transitionMeta, $transitionNodeName);
        if ('' !== $transitionNodeStyle) {
            $transitionOutput[] = $transitionNodeStyle;
        }

        $connectionStyle = '%s-->%s';
        $transitionOutput[] = sprintf($connectionStyle, $from, $transitionNodeName);

        $linkStyle = $this->styleLink($transitionMeta);
        if ('' !== $linkStyle) {
            $transitionOutput[] = $linkStyle;
        }

        ++$this->linkCount;

        $transitionOutput[] = sprintf($connectionStyle, $transitionNodeName, $to);

        $linkStyle = $this->styleLink($transitionMeta);
        if ('' !== $linkStyle) {
            $transitionOutput[] = $linkStyle;
        }

        ++$this->linkCount;

        return $transitionOutput;
    }

    private function styleLink(array $transitionMeta): string
    {
        if (\array_key_exists('color', $transitionMeta)) {
            return sprintf('linkStyle %d stroke:%s', $this->linkCount, $transitionMeta['color']);
        }

        return '';
    }
}
