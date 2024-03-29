<?php

declare(strict_types = 1);

namespace MC;

use MC\Parser\Def;
use MC\Parser\Def\IsEmpty;
use MC\Parser\Def\Literal;
use MC\Parser\Def\NOrMore;
use MC\Parser\Def\OneOf;
use MC\Parser\Def\Recursive;
use MC\Parser\Def\Regex;
use MC\Parser\Def\Set;
use MC\Parser\Def\Word;
use MC\Parser\DefError;

/**
 * Parser-generator class with an easy PHP-based API, similar to the pyparsing module in philosophy.
 *
 * @see \Tests\ParserTest
 */
class Parser
{
    /**
     * By default, the parser ignores these characters when they occur between tokens.
     *
     * @var string
     */
    public static $whitespace = " \t\n\r";

    /**
     * Return a Set with the function arguments as the subexpressions.
     */
    public function set(Def ...$args): Set
    {
        return new Set($args);
    }

    /**
     * Return a OneOf with the function arguments as the possible expressions.
     */
    public function oneOf(Def ...$args): OneOf
    {
        return new OneOf($args);
    }

    /**
     * Return a Word that matches a set of possible characters not separated by whitespace.
     */
    public function word(string $firstChars, string $restChars = null): Word
    {
        return new Word($firstChars, $restChars);
    }

    /**
     * Return a Regex that matches a typical optionally-escaped quoted string.
     *
     * @throws DefError
     */
    public function quotedString(string $quoteChars = '\'"', string $escChar = '\\'): Regex
    {
        $quoteChars = trim($quoteChars);
        if (strlen($escChar) > 1) {
            throw new DefError('Only one $escChar can be defined');
        }
        if ('' !== $escChar) {
            $escChar = preg_quote($escChar, Regex::DELIMITER);
        }

        if ('' === $quoteChars) {
            throw new DefError('$quoteChars cannot be an empty string');
        }
        $quoteCharsArray = str_split($quoteChars);

        $tpl = '(?:Q(?:[^Q\n\rE]|(?:QQ)|(?:Ex[0-9a-fA-F]+)|(?:E.))*Q)';
        $pats = [];
        foreach ($quoteCharsArray as $quote) {
            $quote = preg_quote($quote, Regex::DELIMITER);
            $pats[] = str_replace(['Q', 'E'], [$quote, $escChar], $tpl);
        }

        $regex = implode('|', $pats);

        return new Regex($regex, 'mus', 'quoted string');
    }

    /**
     * wrapper around Regex that matches numerical values (like 7, 3.5, and -42).
     */
    public function number(): Regex
    {
        return new Regex('[+\-]?\d+(\.\d+)?', null, 'number');
    }

    public function hexNumber(): Regex
    {
        return new Regex('0x[0-9,a-f,A-F]+', null, 'number');
    }

    /**
     * wrapper around OneOf that matches true and false, depending on case requirements.
     *
     * @param string $case which case is supported, one of "upper", "lower", "first", or "mixed"
     *
     * @throws DefError
     */
    public function boolean(string $case = 'mixed'): OneOf
    {
        switch ($case) {
            case 'lower':
                return $this->oneOf($this->keyword('true'), $this->keyword('false'));

            case 'upper':
                return $this->oneOf($this->keyword('TRUE'), $this->keyword('FALSE'));

            case 'first':
                return $this->oneOf($this->keyword('True'), $this->keyword('False'));

            case 'mixed':
                return $this->oneOf($this->keyword('true', true), $this->keyword('false', true));
            default:
                throw new DefError('Boolean case must be one of "upper", "lower", "first" or "mixed" - got "'.$case.'"');
        }
    }

    /**
     * Returns a Literal that matches a literal word.
     *
     * @param string $str      the exact string to match
     * @param bool   $caseless flag for triggering case-insensitive searching
     * @param bool   $fullword for triggering literals that can be followed by non-whitespace characters
     */
    public function literal(string $str, bool $caseless = false, bool $fullword = false): Literal
    {
        return new Literal($str, $caseless, $fullword);
    }

    /**
     * Returns a Literal that matches a literal word (but with non-whitespace following characters turned off).
     *
     * @param string $str      the exact string to match
     * @param bool   $caseless flag for triggering case-insensitive searching
     * @param bool   $fullword for triggering literals that can be followed by non-whitespace characters
     */
    public function keyword(string $str, bool $caseless = false, bool $fullword = true): Literal
    {
        return new Literal($str, $caseless, $fullword);
    }

    /**
     * Returns a Set that matches a set of expressions delimited by a literal and optional whitespace.
     *
     * @param Def    $expr  the expression that is delimited
     * @param string $delim the delimiting literal - defaults to ,
     */
    public function delimitedList(Def $expr, string $delim = ','): Set
    {
        return $this->set($expr, $this->zeroOrMore($this->set($this->literal($delim)->suppress(), $expr)));
    }

    /**
     * Returns a NOrMore that matches zero or more expressions.
     *
     * @param Def $expr the expression to match zero or more of
     */
    public function zeroOrMore(Def $expr): NOrMore
    {
        return new NOrMore($expr, 0);
    }

    /**
     * Returns a NOrMore that matches one or more expressions.
     *
     * @param Def $expr the expression to match one or more of
     */
    public function oneOrMore(Def $expr): NOrMore
    {
        return new NOrMore($expr, 1);
    }

    /**
     * Returns a Recursive placeholder def that can be used to create recursive grammars.
     */
    public function recursive(): Recursive
    {
        return new Recursive();
    }

    /**
     * Returns a OneOf that matches zero or one expressions.
     */
    public function optional(Def $expr): OneOf
    {
        $empty = new IsEmpty();

        return $this->oneOf($expr, $empty);
    }

    /**
     * Helper function returning a regex range of all the characters in the english alphabet.
     */
    public function alphas(): string
    {
        return 'a-zA-Z';
    }

    /**
     * Helper function returning a string of all alphabet and digit characters.
     */
    public function alphanums(): string
    {
        return $this->alphas().$this->nums();
    }

    /**
     * Helper function returning a regex range of all digit characters.
     */
    public function nums(): string
    {
        return '0-9';
    }

    /**
     * Simple test for whether a character is a whitespace character.
     */
    public static function isWhitespace(string $test): bool
    {
        return false !== strpos(self::$whitespace, $test);
    }
}
