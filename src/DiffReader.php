<?php
require 'FileIterator.php';
require 'StringIterator.php';
require 'FileLineFilterInterface.php';
class DiffReader implements FileLineFilterInterface
{
    public $inspectable = [];

    protected $base_path = '/';

    protected $current_file = '';
    protected $current_line = -1;

    public function __construct(Iterator $diff = null)
    {
        if (!is_null($diff)) {
            $this->setDiff($diff);
        }
    }

    public function setBasePath ($base_path)
    {
        $this->base_path = $base_path;
    }

    public function acceptFile ($file)
    {
        return array_key_exists($file, $this->inspectable);
    }

    public function acceptLine ($file, $line)
    {
        if ($this->acceptFile($file)) {
            return in_array($line, $this->inspectable[$file]);
        }
        return false;
    }

    public function setFile($filename)
    {
        $this->setDiff(new FileIterator($filename));
    }

    public function setString($string)
    {
        $this->setDiff(new StringIterator($string));
    }

    public function setDiff(Iterator $diff)
    {
        $this->diff = $diff;
    }

    public function getFiles()
    {
        return array_keys($this->inspectable);
    }

    public function parse()
    {
        if (!isset($this->diff)) {
            throw new \RuntimeException('Diff has not been set');
        }
        foreach ($this->diff as $line) {
            if (empty($line)) {
                continue;
            }
            switch($line[0]) {
                case 'd':
                    $this->parseDiff($line);
                    break;
                case '@':
                    $this->parseContext($line);
                    break;
            }
        }
    }

    protected function parseDiff($line)
    {
        $chunks = preg_split('~[ ][abciw]/~', $line);
        if (count($chunks) == 3) {
            $this->current_file = $this->base_path . DIRECTORY_SEPARATOR . trim($chunks[2]);
            return;
        }

        throw new \Exception('This diff aint legit! ' . $line);
    }

    protected function parseContext($line)
    {
        preg_match('~@@[ ]{1}(?:[+-](?P<oldline>[0-9]+)(,(?P<oldcount>[0-9]+))?)[ ]+(?:[+-](?P<newline>[0-9]+)(,(?P<newcount>[0-9]+))?)[ ]+@@~', $line, $matches);
        $line = $matches['newline'];
        $count = 1;
        if (isset($matches['newcount'])) {
            $count = $matches['newcount'];
        }
        for ($i = 0; $i < $count; $i++, $line++) {
            $this->inspectable[$this->current_file][] = $line;
        }
    }
}
