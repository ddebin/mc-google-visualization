<?php

namespace MC\Parser\Def;

use MC\Parser;
use MC\Parser\Def;
use MC\Parser\ParseError;

class Literal extends Def
{
    public $search;
    public $caseless = false;
    public $fullword = true;

    /**
     * Match against an exact set of characters in the string.
     *
     * @param string $str      the search string
     * @param bool   $caseless set to true to ignore case
     * @param bool   $fullword set to false to allow a literal followed by a non-whitespace character
     */
    public function __construct($str, $caseless = false, $fullword = true)
    {
        $this->search = $str;
        $this->caseless = $caseless;
        $this->fullword = $fullword;
    }

    /**
     * @param mixed $str
     * @param mixed $loc
     *
     * @throws ParseError
     *
     * @return array
     */
    public function _parse($str, $loc)
    {
        if (!$this->caseless) {
            $match = strpos($str, $this->search, $loc);
        } else {
            $match = stripos($str, $this->search, $loc);
        }

        if ($match !== $loc) {
            throw new ParseError('Expected: '.$this->search, $str, $loc);
        }

        $loc += strlen($this->search);

        if ($this->fullword && $loc < strlen($str) && !Parser::isWhitespace($str[$loc])) {
            throw new ParseError('Expected: '.$this->search, $str, $loc);
        }

        return [$loc, $this->token($this->search)];
    }

    /**
     * @return string
     */
    public function _name()
    {
        return $this->search;
    }
}
