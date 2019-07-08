<?php

namespace MC\Parser\Def;

use MC\Parser\Def;
use MC\Parser\DefError;

/**
 * Verify that the string matches a series of subexpressions in the specified order.
 */
class Set extends Def
{
    public $exprs = [];

    /**
     * Set constructor.
     *
     * @param array $exprs
     *
     * @throws DefError
     */
    public function __construct(array $exprs = [])
    {
        if (!is_array($exprs)) {
            throw new DefError('Set sub-expressions must be an array');
        }

        $this->exprs = $exprs;
    }

    /**
     * @param string $str the string to parse
     * @param int    $loc the index to start parsing
     *
     * @return array
     */
    public function _parse($str, $loc)
    {
        $res = $this->tokenGroup();
        foreach ($this->exprs as $expr) {
            list($loc, $toks) = $expr->parsePart($str, $loc);
            if (!empty($toks)) {
                $res->append($toks);
            }
        }

        return [$loc, $res];
    }

    /**
     * @return string
     */
    public function _name()
    {
        $names = [];
        foreach ($this->exprs as $expr) {
            $names[] = $expr->getName();
        }

        return '['.implode(', ', $names).']';
    }
}
