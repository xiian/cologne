<?php
/**
 * Class StringIterator
 */
class StringIterator extends ArrayIterator
{
    public function __construct ($string)
    {
        $array = explode(PHP_EOL, $string);
        parent::__construct($array);
    }
}
