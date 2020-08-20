<?php

namespace MC\Parser\Def;

use MC\Parser\Def;
use MC\Parser\ParseError;

/**
 * Generic grammar rule for matching a regular expresion.
 */
class Regex extends Def
{
    /** @var string */
    const DELIMITER = '/';

    /*
     * Subclasses of this can just modify the $regex, $flags, and $errstr properties.
     */

    /** @var string|null  */
    public $regex;

    /** @var string|null */
    public $flags = 'u';

    /** @var string|null */
    public $errstr;

    /** @var int */
    public $retgroup = 0;

    /**
     * @param null|string $regex
     * @param null|string $flags
     * @param null|string $errstr
     */
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
     * @return mixed[]
     */
    public function _parse(string $str, int $loc): array
    {
        preg_match(self::DELIMITER.'^'.$this->regex.self::DELIMITER.$this->flags, substr($str, $loc), $matches, PREG_OFFSET_CAPTURE);
        $success = @$matches[$this->retgroup];
        if (empty($success) || 0 !== $success[1]) {
            throw new ParseError('Expected: '.($this->errstr ?: 'matching '.$this->regex), $str, $loc);
        }

        $loc += strlen($success[0]);

        return [$loc, $this->token($success[0])];
    }

    /**
     * @return string
     */
    public function _name(): string
    {
        if (null !== $this->errstr) {
            return $this->errstr;
        }

        return 'matches: '.$this->regex;
    }
}
