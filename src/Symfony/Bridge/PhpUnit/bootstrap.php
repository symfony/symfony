<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Doctrine\Common\Annotations\AnnotationRegistry;
use Symfony\Bridge\PhpUnit\DeprecationErrorHandler;

if (class_exists(\PHPUnit_Runner_Version::class) && version_compare(\PHPUnit_Runner_Version::id(), '6.0.0', '<')) {
    $classes = [
        'PHPUnit_Framework_Assert', // override PhpUnit's ForwardCompat child class
        'PHPUnit_Framework_AssertionFailedError', // override PhpUnit's ForwardCompat child class
        'PHPUnit_Framework_BaseTestListener', // override PhpUnit's ForwardCompat child class

        'PHPUnit_Framework_Constraint',
        'PHPUnit_Framework_Constraint_ArrayHasKey',
        'PHPUnit_Framework_Constraint_ArraySubset',
        'PHPUnit_Framework_Constraint_Attribute',
        'PHPUnit_Framework_Constraint_Callback',
        'PHPUnit_Framework_Constraint_ClassHasAttribute',
        'PHPUnit_Framework_Constraint_ClassHasStaticAttribute',
        'PHPUnit_Framework_Constraint_Composite',
        'PHPUnit_Framework_Constraint_Count',
        'PHPUnit_Framework_Constraint_Exception',
        'PHPUnit_Framework_Constraint_ExceptionCode',
        'PHPUnit_Framework_Constraint_ExceptionMessage',
        'PHPUnit_Framework_Constraint_ExceptionMessageRegExp',
        'PHPUnit_Framework_Constraint_FileExists',
        'PHPUnit_Framework_Constraint_GreaterThan',
        'PHPUnit_Framework_Constraint_IsAnything',
        'PHPUnit_Framework_Constraint_IsEmpty',
        'PHPUnit_Framework_Constraint_IsEqual',
        'PHPUnit_Framework_Constraint_IsFalse',
        'PHPUnit_Framework_Constraint_IsIdentical',
        'PHPUnit_Framework_Constraint_IsInstanceOf',
        'PHPUnit_Framework_Constraint_IsJson',
        'PHPUnit_Framework_Constraint_IsNull',
        'PHPUnit_Framework_Constraint_IsTrue',
        'PHPUnit_Framework_Constraint_IsType',
        'PHPUnit_Framework_Constraint_JsonMatches',
        'PHPUnit_Framework_Constraint_JsonMatches_ErrorMessageProvider',
        'PHPUnit_Framework_Constraint_LessThan',
        'PHPUnit_Framework_Constraint_ObjectHasAttribute',
        'PHPUnit_Framework_Constraint_PCREMatch',
        'PHPUnit_Framework_Constraint_SameSize',
        'PHPUnit_Framework_Constraint_StringContains',
        'PHPUnit_Framework_Constraint_StringEndsWith',
        'PHPUnit_Framework_Constraint_StringMatches',
        'PHPUnit_Framework_Constraint_StringStartsWith',
        'PHPUnit_Framework_Constraint_TraversableContains',
        'PHPUnit_Framework_Constraint_TraversableContainsOnly',

        'PHPUnit_Framework_Error_Deprecated',
        'PHPUnit_Framework_Error_Notice',
        'PHPUnit_Framework_Error_Warning',
        'PHPUnit_Framework_Exception',
        'PHPUnit_Framework_ExpectationFailedException',

        'PHPUnit_Framework_MockObject_MockObject',

        'PHPUnit_Framework_IncompleteTest',
        'PHPUnit_Framework_IncompleteTestCase',
        'PHPUnit_Framework_IncompleteTestError',
        'PHPUnit_Framework_RiskyTest',
        'PHPUnit_Framework_RiskyTestError',
        'PHPUnit_Framework_SkippedTest',
        'PHPUnit_Framework_SkippedTestCase',
        'PHPUnit_Framework_SkippedTestError',
        'PHPUnit_Framework_SkippedTestSuiteError',

        'PHPUnit_Framework_SyntheticError',

        'PHPUnit_Framework_Test',
        'PHPUnit_Framework_TestCase', // override PhpUnit's ForwardCompat child class
        'PHPUnit_Framework_TestFailure',
        'PHPUnit_Framework_TestListener',
        'PHPUnit_Framework_TestResult',
        'PHPUnit_Framework_TestSuite', // override PhpUnit's ForwardCompat child class

        'PHPUnit_Runner_BaseTestRunner',
        'PHPUnit_Runner_Version',

        'PHPUnit_Util_Blacklist',
        'PHPUnit_Util_ErrorHandler',
        'PHPUnit_Util_Test',
        'PHPUnit_Util_XML',
    ];
    foreach ($classes as $class) {
        class_alias($class, '\\'.strtr($class, '_', '\\'));
    }

    class_alias('PHPUnit_Framework_Constraint_And', 'PHPUnit\Framework\Constraint\LogicalAnd');
    class_alias('PHPUnit_Framework_Constraint_Not', 'PHPUnit\Framework\Constraint\LogicalNot');
    class_alias('PHPUnit_Framework_Constraint_Or', 'PHPUnit\Framework\Constraint\LogicalOr');
    class_alias('PHPUnit_Framework_Constraint_Xor', 'PHPUnit\Framework\Constraint\LogicalXor');
    class_alias('PHPUnit_Framework_Error', 'PHPUnit\Framework\Error\Error');
}

// Detect if we need to serialize deprecations to a file.
if ($file = getenv('SYMFONY_DEPRECATIONS_SERIALIZE')) {
    DeprecationErrorHandler::collectDeprecations($file);

    return;
}

// Detect if we're loaded by an actual run of phpunit
if (!defined('PHPUNIT_COMPOSER_INSTALL') && !class_exists(\PHPUnit_TextUI_Command::class, false) && !class_exists(\PHPUnit\TextUI\Command::class, false)) {
    return;
}

// Enforce a consistent locale
setlocale(\LC_ALL, 'C');

if (!class_exists(AnnotationRegistry::class, false) && class_exists(AnnotationRegistry::class)) {
    if (method_exists(AnnotationRegistry::class, 'registerUniqueLoader')) {
        AnnotationRegistry::registerUniqueLoader('class_exists');
    } else {
        AnnotationRegistry::registerLoader('class_exists');
    }
}

if ('disabled' !== getenv('SYMFONY_DEPRECATIONS_HELPER')) {
    DeprecationErrorHandler::register(getenv('SYMFONY_DEPRECATIONS_HELPER'));
}
