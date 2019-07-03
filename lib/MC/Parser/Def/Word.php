<?php

namespace MC\Parser\Def;

/**
 * Match a "word", with the allowed characters defined by the $first_chars and $rest_chars options.
 */
class Word extends Regex
{
    /**
     * @param string $first_chars the characters allowed as the first character in the word
     * @param string $rest_chars  the characters allwed as the rest of the word - defaults to same as $first_chars
     */
    public function __construct($first_chars, $rest_chars = null)
    {
        parent::__construct();

        if (null === $rest_chars) {
            $rest_chars = $first_chars;
        }

        if ($first_chars === $rest_chars) {
            $this->regex = '['.$first_chars.']+';
        } else {
            $this->regex = '['.$first_chars.']['.$rest_chars.']*';
        }
    }
}
