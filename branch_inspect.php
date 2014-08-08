<?php
require('src/DiffReader.php');
require('src/CheckStyleFilter.php');

$from = '08797be7e1417f718247955fe6bef5e1127f68fb';
$to = 'master';
$path = getcwd();

// Executables
$git_cmd = 'git';
$phpcs_cmd = './vendor/bin/phpcs -d memory_limit=-1 --standard=vendor/vimeo/codingstandard/VimeoStandard';

// Get to the root of the matter
chdir($path);

// Run git diff
$diff_output = shell_exec(sprintf('%s diff %s', $git_cmd, escapeshellarg($from)));

// Run diff reader on the diff
$diffreader = new DiffReader();
$diffreader->setBasePath($path);
$diffreader->setString($diff_output);
try {
    $diffreader->parse();
} catch (\Exception $e) {
    echo $e->getMessage() . PHP_EOL;
    echo $diff_output;
    die(PHP_EOL);
}

// Run phpcs just on the files in the diff, returning checkstyle xml
$concatedFiles = join(' ', array_map('escapeshellarg', $diffreader->getFiles()));
$checkstyle_output = shell_exec(sprintf('%s --report=checkstyle %s', $phpcs_cmd, $concatedFiles));

// Clean up the checkstyle output
$checkstyle_output = preg_replace('~\s*(<([^>]*)>[^<]*</\2>|<[^>]*>)\s*~', '\1', $checkstyle_output);

// Run CheckStyleFilter on checkstyle
$filter = new CheckStyleFilter(simplexml_load_string($checkstyle_output));
$filter->addFilter($diffreader);
$filter->filter();
echo $filter->asXML();
