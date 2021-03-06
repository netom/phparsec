<?php
/**
 * A strange right-associative infix calculator without operator precedence
 */

require '../vendor/autoload.php';

class Calculator extends \PHParsec\Base {
    public function expression()
    {
        return function() {
            $f = $this->choice_([
                $this->number(),
                $this->parExp(),
                $this->opExp(),
            ]);
            return $f();
        };
    }

    public function number()
    {
        return function () {
            $f = $this->preg('-?\\d+(\\.\\d+)?(E-?\d+)?');
            return (double)$f();
        };
    }

    public function parExp()
    {
        return function () {
            $f = $this->seq([
                $this->char('('),
                $this->expression(),
                $this->char(')')
            ]);
            return $f()[1];
        };
    }

    public function opExp()
    {
        return function () {
            $f = $this->seq([
                $this->choice([$this->parExp(), $this->number()]),
                $this->operator(),
                $this->expression()
            ]);
            $res = $f();
            $lval = $res[0];
            $op   = $res[1];
            $rval = $res[2];
            switch ($op) {
                case '+':
                    return $lval + $rval;
                    break;
                case '-':
                    return $lval - $rval;
                    break;
                case '*':
                    return $lval * $rval;
                    break;
                case '/':
                    return $lval / $rval;
                    break;
                default:
                    throw new \PHParsec\ParseException('Invalid operator: ' . $op, $this->_i);
                    break;
            }
        };
    }

    public function operator()
    {
        return $this->choice([
            $this->char('+'),
            $this->char('-'),
            $this->char('*'),
            $this->char('/')
        ]);
    }
}

$c = new Calculator();
while (true) {
    if(false === $l = readline('> ')) break;
    try {
        $f = $c->reset($l)->expression();
        print "# " . $f() . "\n";
    } catch (\Exception $e) {
        print $e->getMessage() . "\n";
    }
}
