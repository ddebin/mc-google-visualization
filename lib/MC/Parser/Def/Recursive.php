<?php

namespace MC\Parser\Def;

use MC\Parser\Def;
use MC\Parser\DefError;
use MC\Parser\ParseError;

class Recursive extends Def
{
    /** @var null|Def */
    public $replacement;

    /**
     * @param string $str
     * @param int    $loc
     *
     * @throws DefError
     * @throws ParseError
     *
     * @return array|mixed
     */
    public function _parse($str, $loc)
    {
        if (null === $this->replacement) {
            throw new DefError('You must replace the recursive placeholder before parsing a grammar');
        }

        return $this->replacement->_parse($str, $loc);
    }

    /**
     * When actually parsing the grammar, use this rule to validate the recursive rule - this must be called before parsing begins.
     *
     * @param Def $expr
     *
     * @return Recursive chainable method - returns $this
     */
    public function replace(Def $expr)
    {
        $this->replacement = $expr;

        return $this;
    }

    /**
     * @return string
     */
    public function _name()
    {
        if (null === $this->replacement) {
            return 'placeholder';
        }

        return $this->replacement->getName();
    }
}
