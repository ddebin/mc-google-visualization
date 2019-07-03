<?php

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
     *
     * @throws Parser\DefError
     *
     * @return Set
     */
    public function set()
    {
        return new Set(func_get_args());
    }

    /**
     * Return a OneOf with the function arguments as the possible expressions.
     *
     * @throws Parser\DefError
     *
     * @return OneOf
     */
    public function oneOf()
    {
        return new OneOf(func_get_args());
    }

    /**
     * Return a Word that matches a set of possible characters not separated by whitespace.
     *
     * @param string      $first_chars
     * @param null|string $rest_chars
     *
     * @return Word
     */
    public function word($first_chars, $rest_chars = null)
    {
        return new Word($first_chars, $rest_chars);
    }

    /**
     * Return a Regex that matches a typical optionally-escaped quoted string.
     *
     * @param string $quote_chars
     * @param string $esc_char
     *
     * @throws DefError
     *
     * @return Regex
     */
    public function quotedString($quote_chars = '\'"', $esc_char = '\\')
    {
        $quote_chars = trim($quote_chars);
        if (!$quote_chars) {
            throw new DefError('$quote_chars cannot be an empty string');
        }
        if (strlen($esc_char) > 1) {
            throw new DefError('Only one $esc_char can be defined');
        }

        $quote_chars = str_split($quote_chars);
        if ($esc_char) {
            $esc_char = preg_quote($esc_char);
        }

        $tpl = '(?:Q(?:[^Q\n\rE]|(?:QQ)|(?:Ex[0-9a-fA-F]+)|(?:E.))*Q)';
        $pats = [];
        foreach ($quote_chars as $quote) {
            $quote = preg_quote($quote);
            $pats[] = str_replace(['Q', 'E'], [$quote, $esc_char], $tpl);
        }

        $regex = implode('|', $pats);

        return new Regex($regex, 'mus', 'quoted string');
    }

    /**
     * wrapper around Regex that matches numerical values (like 7, 3.5, and -42).
     *
     * @return Regex
     */
    public function number()
    {
        return new Regex('[+\-]?\d+(\.\d+)?', null, 'number');
    }

    public function hexNumber()
    {
        return new Regex('0x[0-9,a-f,A-F]+', null, 'number');
    }

    /**
     * wrapper around OneOf that matches true and false, depending on case requirements.
     *
     * @param string $case which case is supported, one of "upper", "lower", "first", or "mixed"
     *
     * @throws DefError
     *
     * @return OneOf
     */
    public function boolean($case = 'mixed')
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
     *
     * @return Literal
     */
    public function literal($str, $caseless = false, $fullword = false)
    {
        return new Literal($str, $caseless, $fullword);
    }

    /**
     * Returns a Literal that matches a literal word (but with non-whitespace following characters turned off).
     *
     * @param string $str      the exact string to match
     * @param bool   $caseless flag for triggering case-insensitive searching
     * @param bool   $fullword for triggering literals that can be followed by non-whitespace characters
     *
     * @return Literal
     */
    public function keyword($str, $caseless = false, $fullword = true)
    {
        return new Literal($str, $caseless, $fullword);
    }

    /**
     * Returns a Set that matches a set of expressions delimited by a literal and optional whitespace.
     *
     * @param Def    $expr  the expression that is delimited
     * @param string $delim the delimiting literal - defaults to ,
     *
     * @throws Parser\DefError
     *
     * @return Set
     */
    public function delimitedList(Def $expr, $delim = ',')
    {
        return $this->set($expr, $this->zeroOrMore($this->set($this->literal($delim, false, false)->suppress(), $expr)));
    }

    /**
     * Returns a NOrMore that matches zero or more expressions.
     *
     * @param Def $expr the expression to match zero or more of
     *
     * @return NOrMore
     */
    public function zeroOrMore(Def $expr)
    {
        return new NOrMore($expr, 0);
    }

    /**
     * Returns a NOrMore that matches one or more expressions.
     *
     * @param Def $expr the expression to match one or more of
     *
     * @return NOrMore
     */
    public function oneOrMore(Def $expr)
    {
        return new NOrMore($expr, 1);
    }

    /**
     * Returns a Recursive placeholder def that can be used to create recursive grammars.
     *
     * @return Recursive
     */
    public function recursive()
    {
        return new Recursive();
    }

    /**
     * Returns a OneOf that matches zero or one expressions.
     *
     * @param Def $expr
     *
     * @throws Parser\DefError
     *
     * @return OneOf
     */
    public function optional(Def $expr)
    {
        $empty = new IsEmpty();

        return $this->oneOf($expr, $empty);
    }

    /**
     * Helper function returning a regex range of all the characters in the english alphabet.
     *
     * @return string
     */
    public function alphas()
    {
        return 'a-zA-Z';
    }

    /**
     * Helper function returning a string of all alphabet and digit characters.
     *
     * @return string
     */
    public function alphanums()
    {
        return $this->alphas().$this->nums();
    }

    /**
     * Helper function returning a regex range of all digit characters.
     *
     * @return string
     */
    public function nums()
    {
        return '0-9';
    }

    /**
     * Simple test for whether a character is a whitespace character.
     *
     * @param mixed $test
     *
     * @return bool
     */
    public static function isWhitespace($test)
    {
        return false !== strpos(self::$whitespace, $test);
    }
}
