<?php

namespace App\Doctrine\ORM\Query\AST\Functions;

use Doctrine\ORM\Query\Lexer;
use Doctrine\ORM\Query\AST\Functions\FunctionNode;

/**
 * REPLACE('str', 'from_str', 'to_str')
 *
 * @link http://dev.mysql.com/doc/refman/5.5/en/string-functions.html#function_replace
 */
class ReplaceFunction extends FunctionNode
{
    /**
     * @var mixed
     */
    protected $stringExpression;

    /**
     * @var string
     */
    protected $search;

    /**
     * @var string
     */
    protected $replace;

    /**
     * @override
     */
    public function getSql(\Doctrine\ORM\Query\SqlWalker $sqlWalker)
    {
        return 'REPLACE(' .
            $sqlWalker->walkArithmeticExpression($this->stringExpression) .
            ','.
            $sqlWalker->walkStringPrimary($this->search) .
            ','.
            $sqlWalker->walkStringPrimary($this->replace) .
        ')';
    }

    /**
     * @override
     */
    public function parse(\Doctrine\ORM\Query\Parser $parser)
    {
        $parser->match(Lexer::T_IDENTIFIER);
        $parser->match(Lexer::T_OPEN_PARENTHESIS);

        $this->stringExpression = $parser->ArithmeticExpression();
        $parser->match(Lexer::T_COMMA);

        $this->search = $parser->StringPrimary();
        $parser->match(Lexer::T_COMMA);

        $this->replace = $parser->StringPrimary();
        $parser->match(Lexer::T_CLOSE_PARENTHESIS);
    }
}
