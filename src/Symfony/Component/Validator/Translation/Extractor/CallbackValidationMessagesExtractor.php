<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Translation\Extractor;

/**
 * Extractor of validation messages from callback constraints.
 *
 * For example, given the class `Foo` the extractor is able to extract the 'Bar is not valid' message:
 *
 * ```
 * use Symfony\Component\Validator\Constraints as Assert;
 *
 * class Foo
 * {
 *     /**
 *      * @Assert\Callback
 *      * /
 *     public function validate(ExecutionContextInterface $context, $payload)
 *     {
 *         $context->buildViolation('Bar is not valid')
 *             ->atPath('bar')
 *             ->addViolation();
 *      }
 * }
 * ```
 *
 * To achieve this create a new instance of extractor per file and callback :
 *
 * ```
 * $messages = (new CallbackValidationMessagesExtractor('/path-to-source-file.php', 'A', 'validate'))->extractMessages();
 * ```
 *
 * @author Webnet team <comptewebnet@webnet.fr>
 */
class CallbackValidationMessagesExtractor
{
    /**
     * The method to attach violation to validation context.
     */
    const BUILD_VIOLATION_METHOD_NAME = 'buildViolation';

    /**
     * Absolute path of source file.
     *
     * @var string
     */
    private $path;

    /**
     * Short class name.
     *
     * @var string
     */
    private $className;

    /**
     * Name of callback method usually annotated with `@Assert\Callback`.
     *
     * @var string
     */
    private $callbackName;

    /**
     * Previous token is T_ClASS.
     *
     * @var bool
     */
    private $isTClassLastToken = false;

    /**
     * Searched class is started.
     *
     * @var bool
     */
    private $classStarted = false;

    /**
     * Depth of opened parenthesis within a searched class.
     *
     * @var int
     */
    private $classParenthesisDepth = 0;

    /**
     * Previous token is T_FUNCTION.
     *
     * @var bool
     */
    private $isTFunctionLastToken = false;

    /**
     * Searched callback is started.
     *
     * @var bool
     */
    private $callbackStarted = false;

    /**
     * Depth of opened parenthesis within a searched callback.
     *
     * @var int
     */
    private $callbackParenthesisDepth = 0;

    /**
     * Previous token is =>.
     *
     * @var bool
     */
    private $isTObjectOperatorLastToken = false;

    /**
     * Previous token is searched build violation method call.
     *
     * @var bool
     */
    private $isTBuildViolationMethodLastToken = false;

    /**
     * Found messages.
     *
     * @var array
     */
    private $messages = array();

    /**
     * Constructor.
     *
     * @param $path
     * @param $className
     * @param $callbackName
     *
     * @throws \ReflectionException
     */
    public function __construct($path, $className, $callbackName)
    {
        $this->path = $path;
        $this->className = (new \ReflectionClass($className))->getShortName();
        $this->callbackName = $callbackName;
    }

    /**
     * Extract messages from static validation callback.
     *
     * @return array
     */
    public function extractMessages()
    {
        $tokens = token_get_all(file_get_contents($this->path));

        for ($i = 0; isset($tokens[$i]); ++$i) {
            $token = $tokens[$i];

            if (isset($token[0]) && isset($token[1])) {
                $this->nextToken($token[0], $token[1]);
            } elseif ('{' === $token) {
                $this->openParenthesis();
            } elseif ('}' === $token) {
                $this->closeParenthesis();
            }
        }

        return $this->messages;
    }

    /**
     * @param $tokenCode
     * @param $tokenValue
     */
    private function nextToken($tokenCode, $tokenValue)
    {
        // skip whitespaces
        if (T_WHITESPACE === $tokenCode) {
            return;
        }

        // mark `class` reserved word
        if (T_CLASS === $tokenCode) {
            $this->isTClassLastToken = true;

            return;
        }

        if ($this->isTClassLastToken) {
            $this->isTClassLastToken = false;

            // mark the needed class started
            if (T_STRING === $tokenCode && $tokenValue === $this->className) {
                $this->classStarted = true;

                return;
            }
        }

        if (!$this->classStarted) {
            return;
        }

        // in interested class

        if (T_FUNCTION === $tokenCode) {
            $this->isTFunctionLastToken = true;

            return;
        }

        if ($this->isTFunctionLastToken) {
            $this->isTFunctionLastToken = false;

            if (T_STRING === $tokenCode && $tokenValue === $this->callbackName) {
                $this->callbackStarted = true;

                return;
            }
        }

        if (!$this->callbackStarted) {
            return;
        }

        // in interested function

        if (T_OBJECT_OPERATOR === $tokenCode) {
            $this->isTObjectOperatorLastToken = true;

            return;
        }

        if ($this->isTObjectOperatorLastToken) {
            $this->isTObjectOperatorLastToken = false;

            if (T_STRING === $tokenCode && self::BUILD_VIOLATION_METHOD_NAME === $tokenValue) {
                $this->isTBuildViolationMethodLastToken = true;

                return;
            }
        }

        if (!$this->isTBuildViolationMethodLastToken) {
            return;
        }

        // build violation method is just called
        $this->isTBuildViolationMethodLastToken = false;
        // and will not any more

        if (T_CONSTANT_ENCAPSED_STRING === $tokenCode) {
            $this->messages[] = trim($tokenValue, '"\' ');
        }
    }

    /**
     * Open parenthesis encountered.
     */
    private function openParenthesis()
    {
        if ($this->classStarted) {
            ++$this->classParenthesisDepth;
        }

        if ($this->callbackStarted) {
            ++$this->callbackParenthesisDepth;
        }
    }

    /**
     * Close parenthesis encountered.
     */
    private function closeParenthesis()
    {
        if ($this->classStarted) {
            if (0 == --$this->classParenthesisDepth) {
                // class finished
                $this->classStarted = false;
            }
        }

        if ($this->callbackStarted) {
            if (0 == --$this->callbackParenthesisDepth) {
                // callback finished
                $this->callbackStarted = false;
            }
        }
    }
}
