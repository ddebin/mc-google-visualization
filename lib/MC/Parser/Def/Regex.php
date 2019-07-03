<?php

namespace MC\Parser\Def;

use MC\Parser\Def;
use MC\Parser\ParseError;

/**
 * Generic grammar rule for matching a regular expresion.
 */
class Regex extends Def
{
    /**
     * Subclasses of this can just modify the $regex, $flags, and $errstr properties.
     */
    public $regex;
    public $flags = 'u';
    public $errstr;
    public $retgroup = 0;

    public function __construct($regex = null, $flags = null, $errstr = null)
    {
        if (null !== $regex) {
            $this->regex = $regex;
        }
        if (null !== $flags) {
            $this->flags = $flags;
        }
        if (null !== $errstr) {
            $this->errstr = $errstr;
        }
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
        preg_match('/^'.$this->regex.'/'.$this->flags, substr($str, $loc), $matches, PREG_OFFSET_CAPTURE);
        $success = @$matches[$this->retgroup];
        if (empty($success) || 0 !== $success[1]) {
            throw new ParseError('Expected: '.($this->errstr ?: 'matching '.$this->regex), $str, $loc);
        }

        $loc += strlen($success[0]);

        return [$loc, $this->token($success[0])];
    }

    public function _name()
    {
        if ($this->errstr) {
            return $this->errstr;
        }

        return 'matches: '.$this->regex;
    }
}
