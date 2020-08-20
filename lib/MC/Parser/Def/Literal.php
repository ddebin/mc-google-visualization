<?php

namespace MC\Parser\Def;

use MC\Parser;
use MC\Parser\Def;
use MC\Parser\ParseError;

class Literal extends Def
{
    /** @var string */
    public $search;

    /** @var bool */
    public $caseless = false;

    /** @var bool */
    public $fullword = true;

    /**
     * Match against an exact set of characters in the string.
     *
     * @param string $str      the search string
     * @param bool   $caseless set to true to ignore case
     * @param bool   $fullword set to false to allow a literal followed by a non-whitespace character
     */
    public function __construct(string $str, bool $caseless = false, bool $fullword = true)
    {
        $this->search = $str;
        $this->caseless = $caseless;
        $this->fullword = $fullword;
    }

    /**
     * @param string $str
     * @param int $loc
     *
     * @throws ParseError
     *
     * @return mixed[]
     */
    public function _parse(string $str, int $loc): array
    {
        $match = !$this->caseless ? strpos($str, $this->search, $loc) : stripos($str, $this->search, $loc);

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
    public function _name(): string
    {
        return $this->search;
    }
}
