<?php

namespace PHParsec;

class Base {
    protected $_str;
    protected $_i;

    public function __construct($str)
    {
        $this->_str = $str;
        $this->_i = 0;
    }

    public function char($c)
    {
        return function () use ($c) {
            if ($this->_i >= strlen($this->_str)) {
                throw new ParseException("unexpected end of string", $this->_i);
            }
            if ($c === $myc = $this->_str[$this->_i]) {
                $this->_i++;
                return $c;
            }
            throw new ParseException("got character $myc instead of $c", $this->_i);
        };
    }

    public function str($str)
    {
        return function () use ($str) {
            $ret = '';
            for ($i = 0; $i < strlen($str); $i++) {
                $f = $this->char($str[$i]);
                $ret .= $f();
            }
            return $ret;
        };
    }

    public function preg($rx, $ignoreCase = false)
    {
        return function () use ($rx, $ignoreCase) {
            if ($this->_i >= strlen($this->_str)) {
                throw new ParseException("unexpected end of string", $this->_i);
            }
            $result = preg_match(
                '/^' . str_replace("/", "\\/", $rx) . '/' . ($ignoreCase ? 'i' : ''),
                substr($this->_str, $this->_i), $matches, 0, 0
            );
            if ($result === FALSE) {
                throw new ParseException("preg_match reported too large string offset", $this->_i);
            }
            if ($result === 0) {
                throw new ParseException("could not match expression $rx", $this->_i);
            }
            $this->_i += strlen($matches[0]);
            return $matches[0];
        };
    }

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

    public function many1($parser)
    {
        return function () use ($parser) {
            $r = $parser();
            $f = $this->many($parser);
            return array_merge([$r], $f());
        };
    }

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

    public function sebBy($parser, $sepParser) {
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
}
