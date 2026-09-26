<?php

// Documents how messages passed as variables are resolved, including the patterns that are not supported yet.

$overwritten = 'overwritten-value';
$overwritten = 'overwriting-value';
echo $translator->trans($overwritten);

$conditionallySuffixed = 'conditionally-suffixed';
if ($condition) {
    $conditionallySuffixed .= '-value';
}
echo $translator->trans($conditionallySuffixed);

$loopLabel = 'loop-initial-value';
foreach ($items as $item) {
    echo $translator->trans($loopLabel);
    $loopLabel = 'loop-next-iteration-value';
}

$referenced = 'referenced-value';
$reference = &$referenced;
echo $translator->trans($reference);

$byReference = null;
$callback = function () use (&$byReference) {
    $byReference = 'closure-by-reference-value';
};
$callback();
echo $translator->trans($byReference);

$labels = ['draft' => 'array-item-draft', 'published' => 'array-item-published'];
echo $translator->trans($labels[$status]);

foreach (['foreach-item-first', 'foreach-item-second'] as $foreachLabel) {
    echo $translator->trans($foreachLabel);
}

[$destructured] = ['destructured-value'];
echo $translator->trans($destructured);

function staticVariable($translator)
{
    static $static = 'static-variable-value';

    return $translator->trans($static);
}

function functionReturnValue(): string
{
    return 'function-return-value';
}
echo $translator->trans(functionReturnValue());

// this enum cannot be autoloaded, so its methods cannot be found
enum Status: string
{
    case Draft = 'draft';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'not-autoloadable-method-return-value',
        };
    }
}
echo $translator->trans(Status::Draft->label());

class Limitations
{
    private const LABELS = ['draft' => 'constant-array-item'];

    private string $property = 'property-value';

    public function __construct(
        private $translator,
    ) {
    }

    public function parameterDefault(string $label = 'parameter-default-value'): string
    {
        return $this->translator->trans($label);
    }

    public function property(): string
    {
        return $this->translator->trans($this->property);
    }

    public function constantArray(string $status): string
    {
        return $this->translator->trans(self::LABELS[$status]);
    }

    public function untypedParameter($article): string
    {
        return $this->translator->trans($article->getTitle());
    }
}
