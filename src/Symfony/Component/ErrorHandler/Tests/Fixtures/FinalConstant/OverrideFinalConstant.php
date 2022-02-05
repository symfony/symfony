<?php

namespace Symfony\Component\ErrorHandler\Tests\Fixtures\FinalConstant;

class OverrideFinalConstant extends FinalConstants
{
    public const FOO = 'FOO';

    protected const OVERRIDDEN_FINAL_PARENT_CLASS = 'O_OVERRIDDEN_FINAL_PARENT_CLASS';

    protected const OVERRIDDEN_NOT_FINAL_PARENT_CLASS = 'O_OVERRIDDEN_NOT_FINAL_PARENT_CLASS';

    public const OVERRIDDEN_FINAL_PARENT_PARENT_CLASS = 'O_OVERRIDDEN_FINAL_PARENT_PARENT_CLASS';

    public const OVERRIDDEN_NOT_FINAL_PARENT_PARENT_CLASS = 'O_OVERRIDDEN_NOT_FINAL_PARENT_PARENT_CLASS';

    private const CCC = 'CCC';
}
