<?php

declare(strict_types = 1);

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

    // Subclasses of this can just modify the $regex, $flags, and $errstr properties.

    /** @var null|string */
    public $regex;

    /** @var null|string */
    public $flags = 'u';

    /** @var null|string */
    public $errstr;

    /** @var int */
    public $retgroup = 0;

    public function __construct(string $regex = null, string $flags = null, string $errstr = null)
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
     * @throws ParseError
     *
     * @return mixed[]
     */
    public function _parse(string $str, int $loc): array
    {
        preg_match(self::DELIMITER.'^'.$this->regex.self::DELIMITER.$this->flags, substr($str, $loc), $matches, PREG_OFFSET_CAPTURE);
        $success = $matches[$this->retgroup] ?? null;
        if ((null === $success) || 0 !== $success[1]) {
            throw new ParseError('Expected: '.($this->errstr ?: 'matching '.$this->regex), $str, $loc);
        }

        $loc += strlen($success[0]);

        return [$loc, $this->token($success[0])];
    }

    public function _name(): string
    {
        if (null !== $this->errstr) {
            return $this->errstr;
        }

        return 'matches: '.$this->regex;
    }
}
