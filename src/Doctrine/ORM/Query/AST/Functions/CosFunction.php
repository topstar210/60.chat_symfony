<?php

namespace App\Doctrine\ORM\Query\AST\Functions;

use Doctrine\ORM\Query\Lexer;
use Doctrine\ORM\Query\AST\Functions\FunctionNode;

/**
 * COS(float)
 *
 * @link http://dev.mysql.com/doc/refman/5.5/en/mathematical-functions.html#function_cos
 */
class CosFunction extends FunctionNode
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
        return 'COS(' .
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
