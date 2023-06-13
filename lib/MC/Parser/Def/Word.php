<?php

declare(strict_types = 1);

namespace MC\Parser\Def;

/**
 * Match a "word", with the allowed characters defined by the $first_chars and $rest_chars options.
 */
class Word extends Regex
{
    /**
     * @param string      $firstChars the characters allowed as the first character in the word
     * @param null|string $restChars  the characters allowed as the rest of the word - defaults to same as $first_chars
     */
    public function __construct(string $firstChars, string $restChars = null)
    {
        parent::__construct();

        if (null === $restChars) {
            $restChars = $firstChars;
        }

        $this->regex = $firstChars === $restChars ? '['.$firstChars.']+' : '['.$firstChars.']['.$restChars.']*';
    }
}
