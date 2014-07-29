<?php
interface FileLineFilterInterface {
    public function acceptFile($file);
    public function acceptLine($file, $line);
} 