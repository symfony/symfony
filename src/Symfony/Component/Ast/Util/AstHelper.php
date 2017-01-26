<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Ast\Util;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;

/**
 * @internal
 */
final class AstHelper
{
    private static $parser;

    /**
     * @param Node[] $stmts
     *
     * @return string the php code
     */
    public static function dump(array $stmts)
    {
        $printer = new Standard();

        return $printer->prettyPrintFile($stmts);
    }

    /**
     * Transforms php code into an ast node.
     *
     * @param string $code the php code
     *
     * @return Node[]
     */
    public static function raw($code)
    {
        $code = "<?php \n".$code;
        if (null === self::$parser) {
            $parserFactory = new ParserFactory();
            self::$parser = $parserFactory->create(ParserFactory::ONLY_PHP5);
        }

        return self::$parser->parse($code);
    }

    /**
     * Transforms a php value into an AST node.
     *
     * @param null|bool|int|float|string|array $value
     *
     * @return Expr
     */
    public static function value($value)
    {
        if (is_null($value)) {
            return new Expr\ConstFetch(
                new Name('null')
            );
        } elseif (is_bool($value)) {
            return new Expr\ConstFetch(
                new Name($value ? 'true' : 'false')
            );
        } elseif (is_int($value)) {
            return new Scalar\LNumber($value);
        } elseif (is_float($value)) {
            return new Scalar\DNumber($value);
        } elseif (is_string($value)) {
            return new Scalar\String_($value);
        } elseif (is_array($value)) {
            $items = array();
            $lastKey = -1;
            foreach ($value as $itemKey => $itemValue) {
                // for consecutive, numeric keys don't generate keys
                if (null !== $lastKey && ++$lastKey === $itemKey) {
                    $items[] = new Expr\ArrayItem(
                        self::value($itemValue)
                    );
                } else {
                    $lastKey = null;
                    $items[] = new Expr\ArrayItem(
                        self::value($itemValue),
                        self::value($itemKey)
                    );
                }
            }

            return new Expr\Array_($items);
        } else {
            throw new \LogicException('Invalid value');
        }
    }

    private function __construct()
    {
    }
}
