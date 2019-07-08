<?php

namespace MC\Parser\Def;

use MC\Parser\Def;
use MC\Parser\DefError;
use MC\Parser\ParseError;

/**
 * Successfully match one of a set of potential expressions - the longest match wins if there are multiples.
 */
class OneOf extends Def
{
    /** @var Def[] */
    public $exprs = [];

    /**
     * OneOf constructor.
     *
     * @param array $exprs
     *
     * @throws DefError
     */
    public function __construct($exprs = [])
    {
        if (!is_array($exprs)) {
            throw new DefError('alternative sub-expressions must be an array');
        }

        $this->exprs = $exprs;
    }

    /**
     * @param string $str the string to parse
     * @param int    $loc the index to start parsing
     *
     * @throws ParseError
     *
     * @return array
     */
    public function _parse($str, $loc)
    {
        $max_match = -1;
        $res = null;
        foreach ($this->exprs as $expr) {
            try {
                list($loc2, $toks) = $expr->parsePart($str, $loc);
                if ($loc2 > $max_match) {
                    $max_match = $loc2;
                    $res = $toks;
                }
            } catch (ParseError $e) {
                //Ignore any non-matching conditions
            }
        }

        if (-1 === $max_match) {
            throw new ParseError('No alternative options match', $str, $loc);
        }
        if ($this->name && !$res->name) {
            $res->name = $this->name;
        }

        return [$max_match, $res];
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

        return 'one of ('.implode(', ', $names).')';
    }
}
