<?php

namespace Symfony\Component\ErrorHandler\Tests\Fixtures\FinalConstant;

class FinalConstants extends FinalConstants2
{
    /**
     * @final
     */
    protected const OVERRIDDEN_FINAL_PARENT_CLASS = 'OVERRIDDEN_FINAL_PARENT_CLASS';

    protected const OVERRIDDEN_NOT_FINAL_PARENT_CLASS = 'OVERRIDDEN_NOT_FINAL_PARENT_CLASS';

    /**
     * @final
     */
    public const NOT_OVERRIDDEN_FINAL_PARENT_CLASS = 'NOT_OVERRIDDEN_FINAL_PARENT_CLASS';

    public const NOT_OVERRIDDEN_NOT_FINAL_PARENT_CLASS = 'NOT_OVERRIDDEN_NOT_FINAL_PARENT_CLASS';

    private const FOO = 'FOO';
}
