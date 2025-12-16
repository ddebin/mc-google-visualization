<?php

declare(strict_types = 1);

namespace MC\Parser\Def;

use MC\Parser\Def;
use MC\Parser\ParseError;

/**
 * Generic grammar rule for matching a regular expression.
 */
class Regex extends Def
{
    /** @var string */
    public const DELIMITER = '/';

    // Subclasses of this can just modify the $regex, $flags, and $errstr properties.

    public ?string $regex;

    public ?string $flags = 'u';

    public ?string $errstr;

    public int $retgroup = 0;

    public function __construct(?string $regex = null, ?string $flags = null, ?string $errstr = null)
    {
        $this->regex = $regex;
        $this->flags = $flags;
        $this->errstr = $errstr;
    }

    /**
     * @throws ParseError
     */
    public function _parse(string $str, int $loc): array
    {
        preg_match(self::DELIMITER.'^'.$this->regex.self::DELIMITER.$this->flags, substr($str, $loc), $matches, PREG_OFFSET_CAPTURE);
        $success = $matches[$this->retgroup] ?? null;
        if ((null === $success) || 0 !== $success[1]) {
            throw new ParseError('Expected: '.($this->errstr ?? 'matching '.$this->regex), $str, $loc);
        }

        $loc += strlen($success[0]);

        return [$loc, $this->token($success[0])];
    }

    public function _name(): string
    {
        return $this->errstr ?? ('matches: '.$this->regex);
    }
}
