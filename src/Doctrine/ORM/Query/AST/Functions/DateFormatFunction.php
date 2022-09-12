<?php

namespace App\Doctrine\ORM\Query\AST\Functions;

use Doctrine\ORM\Query\Lexer;
use Doctrine\ORM\Query\AST\Functions\FunctionNode;

/**
 * DATE_FORMAT(TIMESTAMP, '%format')
 *
 * @link http://dev.mysql.com/doc/refman/5.5/en/date-and-time-functions.html#function_date-format
 */
class DateFormatFunction extends FunctionNode
{
    /*
     * @var mixed
     */
    protected $dateExpression;

    /**
     * @var string
     */
    protected $formatChar;

    /**
     * @override
     */
    public function getSql(\Doctrine\ORM\Query\SqlWalker $sqlWalker)
    {
        return 'DATE_FORMAT(' .
            $sqlWalker->walkArithmeticExpression($this->dateExpression) .
            ','.
            $sqlWalker->walkStringPrimary($this->formatChar) .
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
        $parser->match(Lexer::T_COMMA);

        $this->formatChar = $parser->StringPrimary();
        $parser->match(Lexer::T_CLOSE_PARENTHESIS);
    }
}
