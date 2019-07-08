<?php

namespace MC\Parser\Def;

use MC\Parser\Def;
use MC\Parser\ParseError;

class NOrMore extends Def
{
    /** @var Def */
    public $expr;

    public $min;

    /**
     * @param Def $expr
     * @param int $min
     */
    public function __construct(Def $expr, $min)
    {
        $this->expr = $expr;
        $this->min = (int) $min;
    }

    /**
     * @param string $str
     * @param int    $loc
     *
     * @throws ParseError
     *
     * @return array
     */
    public function _parse($str, $loc)
    {
        $toks = $this->tokenGroup();

        try {
            while (true) {
                list($loc, $tok) = $this->expr->parsePart($str, $loc);
                $toks->append($tok);
            }
        } catch (ParseError $e) {
            // Ignore parsing errors - that just means we're done
        }

        if ($toks->count() < $this->min) {
            throw new ParseError('Expected: '.$this->min.' or more '.$this->expr->name, $str, $loc);
        }

        if (0 === $toks->count()) {
            //If this token is empty, remove it from the result group
            $toks = null;
        }

        return [$loc, $toks];
    }

    /**
     * @return string
     */
    public function _name()
    {
        return $this->min.' or more: '.$this->expr->getName();
    }
}
