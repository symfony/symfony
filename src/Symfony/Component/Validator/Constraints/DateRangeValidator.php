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

use Symfony\Component\PropertyAccess\Exception\NoSuchIndexException;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * Class DateRange
 *
 * @Annotation
 * @Target({"CLASS"})
 *
 * @author Bez Hermoso <bezalelhermoso@gmail.com>
 */
class DateRangeValidator extends ConstraintValidator
{

    /**
     * Checks if the passed value is valid.
     *
     * @param mixed      $value      The value that should be validated
     * @param Constraint $constraint The constraint for the validation
     *
     * @throws \Symfony\Component\Validator\Exception\UnexpectedTypeException
     * @throws \Symfony\Component\Validator\Exception\ConstraintDefinitionException
     * @api
     */
    public function validate($value, Constraint $constraint)
    {
        if (!$constraint instanceof DateRange) {
            throw new UnexpectedTypeException($value, __NAMESPACE__ . '\\DateRange');
        }

        $accessor = PropertyAccess::createPropertyAccessor();

        try {
            $start = $accessor->getValue($value, $constraint->start);
        } catch (NoSuchIndexException $e) {
            throw new ConstraintDefinitionException(
                sprintf(
                    'The object "%s" does not have a "%s" property.',
                    get_class($value),
                    $constraint->start
                )
            );
        }

        try {
            $end = $accessor->getValue($value, $constraint->end);
        } catch (NoSuchIndexException $e) {
            throw new ConstraintDefinitionException(
                sprintf(
                    'The object "%s" does not have a "%s" property.',
                    get_class($value),
                    $constraint->end
                )
            );
        }

        $validPropertyPaths = array($constraint->start, $constraint->end);

        if ($constraint->errorPath && !in_array($constraint->errorPath, $validPropertyPaths)) {
            throw new ConstraintDefinitionException(
                sprintf(
                    'Cannot set error message on "%s". Choose from: %s',
                    $constraint->errorPath,
                    json_encode($validPropertyPaths)
                )
            );
        }

        if (empty($start) || empty($end)) {
            return;
        }

        if (!$start instanceof \DateTime) {
            $this->addViolation($value, $constraint->invalidMessage, array());
            return;
        }

        if (!$end instanceof \DateTime) {
            $this->addViolation($value, $constraint->invalidMessage, array());
            return;
        }

        $diff = $start->diff($end);

        if ($diff->invert === 0) {
            $this->checkIntervals($value, clone $start, clone $end, $constraint, $diff);
            return;
        }

        if ($constraint->errorPath) {
            $message =
                $constraint->errorPath === $constraint->end ? $constraint->endMessage : $constraint->startMessage;
            $limit =
                $constraint->errorPath === $constraint->end ?
                    $start->format($constraint->limitFormat) : $end->format($constraint->limitFormat);

            $parameters = array(
                '{{ limit }}' => $limit,
            );

            $this->addViolation($value, $message, $parameters, $constraint->errorPath);

        } else {
            $parameters = array(
                '{{ start }}' => $start->format($constraint->limitFormat),
                '{{ end }}' => $end->format($constraint->limitFormat),
            );
            $this->addViolation($value, $constraint->invalidMessage, $parameters);
        }
    }

    /**
     * Abstract violation handling between Validator APIs
     *
     * @param       $message
     * @param array $parameters
     * @param null  $path
     */
    private function addViolation($value, $message, array $parameters, $path = null)
    {
        $context = $this->context;
        if ($context instanceof ExecutionContextInterface) {
            $violation = $context->buildViolation($message, $parameters);
            if ($path !== null) {
                $violation->atPath($path);
            }
            $violation->setInvalidValue($value);
            $violation->addViolation();
        } else {
            if ($path === null) {
                $context->addViolation($message, $parameters, $value);
            } else {
                $context->addViolationAt($path, $message, $parameters, $value);
            }
        }
    }

    /**
     * @param \DateInterval $interval
     *
     * @return int
     */
    private function convertIntervalToSeconds(\DateInterval $interval)
    {
        return $interval->d * 60 * 60 * 24
             + $interval->h * 60 * 60
             + $interval->i * 60
             + $interval->s;
    }

    private function checkIntervals(
        $value,
        \DateTime $start,
        \DateTime $end,
        DateRange $constraint,
        \DateInterval $diff
    ) {

        $diffSeconds = $this->convertIntervalToSeconds($diff);

        if ($constraint->min) {
            $minInterval = \DateInterval::createFromDateString('+' . $constraint->min);
            $start->add($minInterval);
            $diff = $start->diff($end);
            if ($diff->invert === 1) {
                $this->addViolation($value, $constraint->invalidIntervalMessage, array(
                        '{{ interval }}' => $constraint->min,
                    ), $constraint->errorPath);
            }
        }

        if ($constraint->max) {
            $maxInterval = \DateInterval::createFromDateString('+' . $constraint->max);
            $start->add($maxInterval);
            $diff = $end->diff($start);
            if ($diff->invert === 1) {
                $this->addViolation($value, $constraint->invalidIntervalMessage, array(
                        '{{ interval }}' => $constraint->max,
                    ), $constraint->errorPath);
            }
        }
    }
}
