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

use InvalidArgumentException;
use Symfony\Component\Workflow\Definition;
use Symfony\Component\Workflow\Marking;
use Symfony\Component\Workflow\Metadata\MetadataStoreInterface;
use Symfony\Component\Workflow\Transition;

/**
 * PlantUmlDumper dumps a workflow as a PlantUML file.
 *
 * You can convert the generated puml file with the plantuml.jar utility (http://plantuml.com/):
 *
 * php bin/console workflow:dump pull_request travis --dump-format=puml | java -jar plantuml.jar -p  > workflow.png
 *
 * @author Sébastien Morel <morel.seb@gmail.com>
 */
class PlantUmlDumper implements DumperInterface
{
    private const INITIAL = '<<initial>>';
    private const MARKED = '<<marked>>';

    public const STATEMACHINE_TRANSITION = 'arrow';
    public const WORKFLOW_TRANSITION = 'square';
    public const TRANSITION_TYPES = [self::STATEMACHINE_TRANSITION, self::WORKFLOW_TRANSITION];
    public const DEFAULT_OPTIONS = [
        'skinparams' => [
            'titleBorderRoundCorner' => 15,
            'titleBorderThickness' => 2,
            'state' => [
                'BackgroundColor'.self::INITIAL => '#87b741',
                'BackgroundColor'.self::MARKED => '#3887C6',
                'BorderColor' => '#3887C6',
                'BorderColor'.self::MARKED => 'Black',
                'FontColor'.self::MARKED => 'White',
            ],
            'agent' => [
                'BackgroundColor' => '#ffffff',
                'BorderColor' => '#3887C6',
            ],
        ],
    ];

    private $transitionType = self::STATEMACHINE_TRANSITION;

    public function __construct(string $transitionType = null)
    {
        if (!\in_array($transitionType, self::TRANSITION_TYPES, true)) {
            throw new InvalidArgumentException("Transition type '$transitionType' does not exist.");
        }
        $this->transitionType = $transitionType;
    }

    public function dump(Definition $definition, Marking $marking = null, array $options = []): string
    {
        $options = array_replace_recursive(self::DEFAULT_OPTIONS, $options);

        $workflowMetadata = $definition->getMetadataStore();

        $code = $this->initialize($options, $definition);

        foreach ($definition->getPlaces() as $place) {
            $code[] = $this->getState($place, $definition, $marking);
        }
        if ($this->isWorkflowTransitionType()) {
            foreach ($definition->getTransitions() as $transition) {
                $transitionEscaped = $this->escape($transition->getName());
                $code[] = "agent $transitionEscaped";
            }
        }
        foreach ($definition->getTransitions() as $transition) {
            $transitionEscaped = $this->escape($transition->getName());
            foreach ($transition->getFroms() as $from) {
                $fromEscaped = $this->escape($from);
                foreach ($transition->getTos() as $to) {
                    $toEscaped = $this->escape($to);

                    $transitionEscapedWithStyle = $this->getTransitionEscapedWithStyle($workflowMetadata, $transition, $transitionEscaped);

                    $arrowColor = $workflowMetadata->getMetadata('arrow_color', $transition);

                    $transitionColor = '';
                    if (null !== $arrowColor) {
                        $transitionColor = $this->getTransitionColor($arrowColor) ?? '';
                    }

                    if ($this->isWorkflowTransitionType()) {
                        $transitionLabel = '';
                        // Add label only if it has a style
                        if ($transitionEscapedWithStyle != $transitionEscaped) {
                            $transitionLabel = ": $transitionEscapedWithStyle";
                        }

                        $lines = [
                            "{$fromEscaped} -{$transitionColor}-> {$transitionEscaped}{$transitionLabel}",
                            "{$transitionEscaped} -{$transitionColor}-> {$toEscaped}{$transitionLabel}",
                        ];
                        foreach ($lines as $line) {
                            if (!\in_array($line, $code)) {
                                $code[] = $line;
                            }
                        }
                    } else {
                        $code[] = "{$fromEscaped} -{$transitionColor}-> {$toEscaped}: {$transitionEscapedWithStyle}";
                    }
                }
            }
        }

        return $this->startPuml($options).$this->getLines($code).$this->endPuml($options);
    }

    private function isWorkflowTransitionType(): bool
    {
        return self::WORKFLOW_TRANSITION === $this->transitionType;
    }

    private function startPuml(array $options): string
    {
        $start = '@startuml'.\PHP_EOL;
        $start .= 'allow_mixing'.\PHP_EOL;

        return $start;
    }

    private function endPuml(array $options): string
    {
        return \PHP_EOL.'@enduml';
    }

    private function getLines(array $code): string
    {
        return implode(\PHP_EOL, $code);
    }

    private function initialize(array $options, Definition $definition): array
    {
        $workflowMetadata = $definition->getMetadataStore();

        $code = [];
        if (isset($options['title'])) {
            $code[] = "title {$options['title']}";
        }
        if (isset($options['name'])) {
            $code[] = "title {$options['name']}";
        }

        // Add style from nodes
        foreach ($definition->getPlaces() as $place) {
            $backgroundColor = $workflowMetadata->getMetadata('bg_color', $place);
            if (null !== $backgroundColor) {
                $key = 'BackgroundColor<<'.$this->getColorId($backgroundColor).'>>';

                $options['skinparams']['state'][$key] = $backgroundColor;
            }
        }

        if (isset($options['skinparams']) && \is_array($options['skinparams'])) {
            foreach ($options['skinparams'] as $skinparamKey => $skinparamValue) {
                if (!$this->isWorkflowTransitionType() && 'agent' === $skinparamKey) {
                    continue;
                }
                if (!\is_array($skinparamValue)) {
                    $code[] = "skinparam {$skinparamKey} $skinparamValue";
                    continue;
                }
                $code[] = "skinparam {$skinparamKey} {";
                foreach ($skinparamValue as $key => $value) {
                    $code[] = "    {$key} $value";
                }
                $code[] = '}';
            }
        }

        return $code;
    }

    private function escape(string $string): string
    {
        // It's not possible to escape property double quote, so let's remove it
        return '"'.str_replace('"', '', $string).'"';
    }

    private function getState(string $place, Definition $definition, Marking $marking = null): string
    {
        $workflowMetadata = $definition->getMetadataStore();

        $placeEscaped = $this->escape($place);

        $output = "state $placeEscaped".
            (\in_array($place, $definition->getInitialPlaces(), true) ? ' '.self::INITIAL : '').
            ($marking && $marking->has($place) ? ' '.self::MARKED : '');

        $backgroundColor = $workflowMetadata->getMetadata('bg_color', $place);
        if (null !== $backgroundColor) {
            $output .= ' <<'.$this->getColorId($backgroundColor).'>>';
        }

        $description = $workflowMetadata->getMetadata('description', $place);
        if (null !== $description) {
            $output .= ' as '.$place.
                \PHP_EOL.
                $place.' : '.$description;
        }

        return $output;
    }

    private function getTransitionEscapedWithStyle(MetadataStoreInterface $workflowMetadata, Transition $transition, string $to): string
    {
        $to = $workflowMetadata->getMetadata('label', $transition) ?? $to;

        $color = $workflowMetadata->getMetadata('color', $transition) ?? null;

        if (null !== $color) {
            $to = sprintf(
                '<font color=%1$s>%2$s</font>',
                $color,
                $to
            );
        }

        return $this->escape($to);
    }

    private function getTransitionColor(string $color): string
    {
        // PUML format requires that color in transition have to be prefixed with “#”.
        if ('#' !== substr($color, 0, 1)) {
            $color = '#'.$color;
        }

        return sprintf('[%s]', $color);
    }

    private function getColorId(string $color): string
    {
        // Remove “#“ from start of the color name so it can be used as an identifier.
        return ltrim($color, '#');
    }
}
