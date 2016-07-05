<?php
/**
 * Base class for building parser suites
 * 
 * The Base class implements simple parsers and combinators often used in
 * other parsers.
 * 
 * @author Fábián Tamás László <giganetom@gmail.com>
 * @license https://opensource.org/licenses/MIT MIT
 */

namespace PHParsec;

/**
 * Base class
 * 
 * Instances of this class store the parser state. The methods are either
 * parsers, or combinators. Use these to build your parsers.
 */
class Base {
    protected $_str;
    protected $_i;

    /**
     * Instantiate a parser suite
     * 
     * Store the string to be parsed, and set the internal pointer
     * to the beginning of the string
     * 
     * @param string $str The string to be parsed
     */
    public function __construct($str = null)
    {
        $this->_str = $str;
        $this->_i = 0;
    }

    /**
     * Reset parser state
     * 
     * Resets the internal pointer, and replace the string with a new string
     * if it is given. If the string parameter is not given or null, the stored
     * string is left untouched.
     * 
     * @param string|null $str The new string to be parsed or null if no change necessary
     * @return \PHParsec\Base
     * @throws \PHParsec\ParseException
     */
    public function reset($str = null)
    {
        if (null !== $str) {
            $this->_str = $str;
        }
        $this->_i = 0;
        return $this;
    }

    /**
     * Parse the end of the sring
     * 
     * If at the end of the string, return an empty string. Throw a
     * ParseException otherwise.
     * 
     * @return callable
     * @throws \PHParsec\ParseException
     */
    public function end()
    {
        return function () {
            if ($this->_i < strlen($this->_str)) {
                $this->pe("Not at the end of string");
            }
            return '';
        };
    }

    /**
     * Parse a character
     * 
     * If the next character in the string matches the parameter, the pointer
     * is advanced, and that character is returned. It throws a ParseException
     * otherwise.
     * 
     * @param string $c A single character to be parsed
     * @return callable
     * @throws \PHParsec\ParseException
     */
    public function char($c)
    {
        return function () use ($c) {
            if ($this->_i >= strlen($this->_str)) {
                $this->pe("unexpected end of string");
            }
            if ($c === $myc = $this->_str[$this->_i]) {
                $this->_i++;
                return $c;
            }
            $this->pe("got character $myc instead of $c");
        };
    }

    /**
     * Parse a string
     * 
     * Tries to match the given string with the one stored at the position
     * given by the internal pointer. It either returns the string on success,
     * or throws a ParseException.
     * 
     * @param string $str The expected string
     * @return callable
     * @throws \PHParsec\ParseException
     */
    public function str($str)
    {
        return function () use ($str) {
            if (substr($this->_str, $this->_i, $_ = strlen($str)) !== $str)  {
                $this->pe("could not parse string: $str");
            }
            $this->_i += $_;
            return $str;
        };
    }

    /**
     * Parse a regular expression
     * 
     * Tries to match a regular expression with the stored string at the
     * current position. Returns the matched string on success, throws an
     * exception on failure.
     * 
     * @param string  $rx         Perl-style regular expression without delimiters
     * @param boolean $ignoreCase Should the regex ignore case?
     * @return callable
     * @throws \PHParsec\ParseException
     */
    public function preg($rx, $ignoreCase = false)
    {
        return function () use ($rx, $ignoreCase) {
            if ($this->_i >= strlen($this->_str)) {
                $this->pe("unexpected end of string");
            }
            $result = preg_match(
                '/^' . str_replace("/", "\\/", $rx) . '/' . ($ignoreCase ? 'i' : ''),
                substr($this->_str, $this->_i), $matches, 0, 0
            );
            if ($result === FALSE) {
                $this->pe("preg_match reported too large string offset");
            }
            if ($result === 0) {
                $this->pe("could not match expression $rx");
            }
            $this->_i += strlen($matches[0]);
            return $matches[0];
        };
    }

    /**
     * Sequence combinator
     * 
     * Combines a sequence of parsers into a single parser that applies each
     * parser in the list. It returns the array of parsed results, or throws
     * a ParseException.
     * 
     * @param array|\Traversable $list An array or traversable of parsers
     * @return callable
     * @throws \PHParsec\ParseException
     */
    public function seq($list)
    {
        if (!is_array($list) && ! $list instanceof \Traversable) {
            throw new \InvalidArgumentException("The method seq only accepts arrays or instances of \\Traversable");
        }
        return function () use ($list) {
            $ret = [];
            foreach ($list as $k => $v) {
                $ret[$k] = $v();
            }
            return $ret;
        };
    }

    /**
     * Parse something zero or more times
     * 
     * Tries to apply the given parser as many times it can. Since zero success
     * is still a success, it never fails. It sets the pointer after the last
     * successful match. If there are no successful matches, it leaves the
     * pointer in the state it was before the call.
     * 
     * It returns an array of zero or more elements containing the results of
     * successful matches.
     * 
     * @param callable $parser The parser to apply
     * @return callable
     */
    public function many($parser)
    {
        return function () use ($parser) {
            $ret = [];
            while (true) {
                $i = $this->_i;
                try {
                    $ret[] = $parser();
                } catch (ParseException $e) {
                    // This is expected, we'll just finish parsing, nothing bad happened, restore the position
                    $this->_i = $i;
                    return $ret;
                }
            }
        };
    }

    /**
     * Parse someting one or more times.
     * 
     * Tries to apply the given parser at least one times, and as many
     * times possible. Effects are similar to calling the $parser directly
     * and then calling many().
     * 
     * @see \PHParsec\Base::many()
     * 
     * @param callable The parser to be applied
     * @return callable
     * @throws \PHParsec\ParseException
     */
    public function many1($parser)
    {
        return function () use ($parser) {
            $r = $parser();
            $f = $this->many($parser);
            return array_merge([$r], $f());
        };
    }

    /**
     * LEFTMOST-choice combinator
     * 
     * Combine a $list of parsers into a parser that returns the result of the
     * FIRST parser that succeeds. If no parser succeeds, it throws an ParseException.
     * An empty parser list always results in an exception.
     * 
     * @param callable[] $list List of parsers to choose from
     * @return callable
     * @throws \PHParsec\ParseException
     */
    public function choice($list)
    {
        if (!is_array($list) && ! $list instanceof \Traversable) {
            throw new \InvalidArgumentException("The method choice only accepts arrays or instances of \\Traversable");
        }
        return function () use ($list) {
            foreach ($list as $parser) {
                $i = $this->_i;
                try {
                    return $parser();
                } catch (ParseException $e) {
                    // Never mind, keep trying, restore the position
                    $this->_i = $i;
                }
            }
            throw new ParseException("Run out of choices", $this->_i);
        };
    }

    /**
     * GREEDY-choice combinator
     * 
     * Combine a $list of parsers into a parser that returns the result of the
     * parser the parses the LONGEST STRING. The parser itself doesn't need to
     * return a string, the choice_() method always inspect the internal pointer
     * to decide wich parser could parse the most.
     * 
     * Because of the excercise modesty in applying this function. Overuse could
     * easily result in lengthy backtracking. In many cases using choice()
     * instead of choice_() is possible with ordering and/or modifying the
     * parsers, or maybe the grammar itself.
     * 
     * Try to use choice() wherever possible, and order the $list so the
     * parsers that parse prefixes of other parsers in the list are put towards
     * the tail end of the $list.
     *
     * One example is parsing numbers. Imageine you have to parse integers and
     * floating point numbers. The string "23.4" looks like an integer ot the
     * front, until you encounter the dot. If you try to parse it like
     * (pseudocode follows) choice([integer(), float()]) (where integer() and
     * float() your parsers for the types in question, you can end up parsing
     * only "23", and leaving a mess behind. You can fix this by using choice_()
     * or by reordering the parsers, because most floating-point number look
     * like an integer first. In this case, it is better to reorder your list of
     * parsers, and use someting like this: choice([float(), integer()].
     * 
     * @param callable[] $list List of parsers to choose from
     * @return callable
     * @throws \PHParsec\ParseException
     */
    public function choice_($list)
    {
        if (!is_array($list) && ! $list instanceof \Traversable) {
            throw new \InvalidArgumentException("The method choice only accepts arrays or instances of \\Traversable");
        }
        return function () use ($list) {
            $maxLength = -1;
            $maxValue  = null;
            foreach ($list as $parser) {
                $i = $this->_i;
                try {
                    $value  = $parser();
                    $length = $this->_i - $i;
                    if ($length > $maxLength) {
                        $maxLength = $length;
                        $maxValue  = $value;
                    }
                } catch (ParseException $e) {
                    // Never mind, keep trying
                } finally {
                    // Restore the position
                    $this->_i = $i;
                }
            }
            if ($maxLength == -1) {
                throw new ParseException("Run out of choices", $this->_i);
            } else {
                $this->_i += $maxLength;
                return $maxValue;
            }
        };
    }

    /**
     * Tries to parse a non-empty list with parsers supplied for values and separators.
     * 
     * Applies $parser and $sepParser one after the other repeatedly. Parsing
     * finishes after the first time $sepParser fails. $parser should not ever
     * fail, so the list MUST NOT contain a dangling separator at the end, and
     * the list MUST contain at least one values (that can be parsed with
     * $parser).
     * 
     * @param callable $parser    The for the values
     * @param callable $sepParser The parser for the separators
     * @return callable
     * @throws \PHParsec\ParseException
     */
    public function sepBy1($parser, $sepParser)
    {
        return function () use ($parser, $sepParser) {
            $ret = [];
            while (true) {
                $ret[] = $parser();
                try {
                    $sepParser();
                } catch (ParseException $e) {
                    return $ret;
                }
            }
        };
    }

    /**
     * Tries to parse a (possibly empty) list with parsers supplied for values and separators.
     * 
     * Applies $parser and $sepParser one after the other repeatedly. Parsing
     * finishes after the first time $sepParser fails. $parser can fail the
     * first time it applied, so this function can be used to parse empty lists.
     * 
     * @param callable $parser    The for the values
     * @param callable $sepParser The parser for the separators
     * @return callable
     * @throws \PHParsec\ParseException
     */
    public function sebBy($parser, $sepParser)
    {
        return function () use ($parser, $sepParser) {
            $ret = [];
            try {
                $ret[] = $parser();
            } catch (ParseException $e) {
                return $ret;
            }
            while (true) {
                try {
                    $sepParser();
                } catch (ParseException $e) {
                    return $ret;
                }
                $ret[] = $parser();
            }
        };
    }

    /**
     * Throw a ParseException
     * 
     * Throw a ParseException with the given message, and with the current
     * position. Could be used to conveniently raise ParseException, and having
     * to remember passing $this->_i every time.
     * 
     * @throws \PHParsec\ParseException
     */
    protected function pe($msg)
    {
        throw new ParseException($msg, $this->_i);
    }
}
