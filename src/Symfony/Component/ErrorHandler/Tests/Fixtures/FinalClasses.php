<?php

namespace Symfony\Component\ErrorHandler\Tests\Fixtures;

/**
 * @final since version 3.3.
 */
class FinalClass1
{
    // simple comment
}

/**
 * @final
 */
class FinalClass2
{
    // no comment
}

/**
 * @final comment with @@@ and ***
 *

 */
class FinalClass3
{
    // with comment and a tag after
}

/**
 * @final
 *

 */
class FinalClass4
{
    // without comment and a tag after
}

/**

 *
 * @final multiline
 * comment
 */
class FinalClass5
{
    // with comment and a tag before
}

/**

 *
 * @final
 */
class FinalClass6
{
    // without comment and a tag before
}

/**

 *
 * @final another
 *        multiline comment...
 *
 * @return string
 */
class FinalClass7
{
    // with comment and a tag before and after
}

/**

 * @final
 *
 * @return string
 */
class FinalClass8
{
    // without comment and a tag before and after
}
