<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Extension\Validator\ViolationMapper;

use Symfony\Component\Form\Exception\ErrorMappingException;
use Symfony\Component\Form\FormConfigBuilder;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\PropertyAccess\PropertyPath;

/**
 * @internal
 */
class DynamicMappingRule extends AbstractMappingRule
{
    private $propertyPathLength;

    private $matchRules;

    private $isPrefixRules;

    public function __construct(FormInterface $origin, string $propertyPath, string $targetPath)
    {
        if (substr_count($propertyPath, '*') !== substr_count($targetPath, '*')) {
            throw new ErrorMappingException(sprintf('The number of "*" must be equals on both sides for the dynamic mapping rule "%s => %s" in "%s".', $propertyPath, $targetPath, $origin->getName()));
        }

        parent::__construct($origin, $targetPath);

        $propertyPath = new PropertyPath($propertyPath);

        $this->propertyPathLength = $propertyPath->getLength();

        foreach ($propertyPath->getElements() as $ruleElement) {
            if ('*' === $ruleElement) {
                $this->matchRules[] = function (string $element, &$dynamicValues): bool {
                    $dynamicValues[] = $element;

                    return true;
                };

                $this->isPrefixRules[] =  function (): bool {
                    return true;
                };
            } elseif (false === strpos($ruleElement, '*')) {
                $this->matchRules[] = $this->isPrefixRules[] = function (string $element) use ($ruleElement): bool {
                    return $element === $ruleElement;
                };
            } else {
                $ruleElementPattern = preg_quote($ruleElement, '/');

                $matchPattern = '/'.str_replace('\\*', '('.FormConfigBuilder::VALID_NAME_PATTERN.')', $ruleElementPattern).'/';
                $this->matchRules[] = function (string $element, array &$dynamicValues) use ($matchPattern): bool {
                    if (1 === preg_match($matchPattern, $element, $matches)) {
                        array_push($dynamicValues, ...array_slice($matches, 1));

                        return true;
                    }

                    return false;
                };

                $isPrefixPattern = '/'.str_replace('\\*', FormConfigBuilder::VALID_NAME_PATTERN, $ruleElementPattern).'/';
                $this->isPrefixRules[] = function (string $element) use ($isPrefixPattern): bool {
                    return 1 === preg_match($isPrefixPattern, $element);
                };
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function match($propertyPath)
    {
        $propertyPath = new PropertyPath($propertyPath);

        if ($propertyPath->getLength() !== $this->propertyPathLength) {
            return null;
        }

        $dynamicValues = [];
        foreach ($this->matchRules as $index => $matchingRule) {
            if (!$matchingRule($propertyPath->getElement($index), $dynamicValues)) {
                return null;
            }
        }

        $realTargetPath = $this->targetPath;
        while (false !== $pos = strpos($realTargetPath, '*')) {
            $realTargetPath = substr_replace($realTargetPath, array_shift($dynamicValues), $pos, 1);
        }

        return $this->doGetTarget($realTargetPath);
    }

    /**
     * {@inheritdoc}
     */
    public function isPrefix($propertyPath)
    {
        $propertyPath = new PropertyPath($propertyPath);

        if ($propertyPath->getLength() >= $this->propertyPathLength) {
            return false;
        }

        foreach ($propertyPath->getElements() as $index => $element) {
            if (!$this->isPrefixRules[$index]($element)) {
                return false;
            }
        }

        return true;
    }
}
