<?php

namespace App\Doctrine\ORM\Query\AST\Functions;

use Doctrine\ORM\Query\Lexer;
use Doctrine\ORM\Query\AST\Functions\FunctionNode;

/**
 * SIN(float)
 *
 * @link http://dev.mysql.com/doc/refman/5.5/en/mathematical-functions.html#function_sin
 */
class SinFunction extends FunctionNode
{
    /*
     * @var mixed
     */
    protected $dateExpression;

    /**
     * @override
     */
    public function getSql(\Doctrine\ORM\Query\SqlWalker $sqlWalker)
    {
        return 'SIN(' .
            $sqlWalker->walkArithmeticExpression($this->dateExpression) .
        ')';
    }

    /**
     * @override
     */
    public function parse(\Doctrine\ORM\Query\Parser $parser)
    {
        $parser->match(Lexer::T_IDENTIFIER);
        $parser->match(Lexer::T_OPEN_PARENTHESIS);

        $this->dateExpression = $parser->ArithmeticExpression();

        $parser->match(Lexer::T_CLOSE_PARENTHESIS);
    }
}
