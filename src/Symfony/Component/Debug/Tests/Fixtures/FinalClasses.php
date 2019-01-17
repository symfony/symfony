<?php

namespace Symfony\Component\Debug\Tests\Fixtures;

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
 * @author John Doe
 */
class FinalClass3
{
    // with comment and a tag after
}

/**
 * @final
 * @author John Doe
 */
class FinalClass4
{
    // without comment and a tag after
}

/**
 * @author John Doe
 *
 *
 * @final multiline
 * comment
 */
class FinalClass5
{
    // with comment and a tag before
}

/**
 * @author John Doe
 *
 * @final
 */
class FinalClass6
{
    // without comment and a tag before
}

/**
 * @author John Doe
 *
 * @final another
 *
 *        multiline comment...
 *
 * @return string
 */
class FinalClass7
{
    // with comment and a tag before and after
}

/**
 * @author John Doe
 * @final
 * @return string
 */
class FinalClass8
{
    // without comment and a tag before and after
}
