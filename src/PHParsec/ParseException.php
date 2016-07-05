<?php

namespace PHParsec;

/**
 * Exception thrown on parse errors
 */
class ParseException extends \Exception {
    public function __construct($msg, $i)
    {
        parent::__construct("Parse error: $msg at position $i");
    }
}
