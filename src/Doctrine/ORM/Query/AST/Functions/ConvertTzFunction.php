<?php

namespace App\Doctrine\ORM\Query\AST\Functions;

use Doctrine\ORM\Query\Lexer;
use Doctrine\ORM\Query\AST\Functions\FunctionNode;

/**
 * CONVERT_TZ(TIMESTAMP, 'from_tz', 'to_tz')
 *
 * @link http://dev.mysql.com/doc/refman/5.5/en/date-and-time-functions.html#function_convert-tz
 */
class ConvertTzFunction extends FunctionNode
{
    /*
     * @var mixed
     */
    protected $dateExpression;

    /**
     * @var string
     */
    protected $fromTz;

    /**
     * @var string
     */
    protected $toTz;

    /**
     * @override
     */
    public function getSql(\Doctrine\ORM\Query\SqlWalker $sqlWalker)
    {
        return 'CONVERT_TZ(' .
            $sqlWalker->walkArithmeticExpression($this->dateExpression) .
            ','.
            $sqlWalker->walkStringPrimary($this->fromTz) .
            ','.
            $sqlWalker->walkStringPrimary($this->toTz) .
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

        $this->fromTz = $parser->StringPrimary();
        $parser->match(Lexer::T_COMMA);

        $this->toTz = $parser->StringPrimary();
        $parser->match(Lexer::T_CLOSE_PARENTHESIS);
    }
}
