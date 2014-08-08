<?php
require('src/DiffReader.php');
require('src/CheckStyleFilter.php');

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class GreetCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('run')
            ->setDescription('Greet someone')
            ->addArgument(
                'base_ref',
                InputArgument::REQUIRED,
                'Where do you want the comparison to start?'
            )
            ->addArgument(
                'to_ref',
                InputArgument::OPTIONAL,
                'Where do you want the comparison to end?',
                'master'
            )
            ->addOption(
                'path',
                null,
                InputOption::VALUE_OPTIONAL,
                'What path would you like to work in?',
                getcwd()
            )
            ->addOption(
                'git-cmd',
                null,
                InputOption::VALUE_OPTIONAL,
                'How to call git',
                'git'
            )
            ->addOption(
                'phpcs-cmd',
                null,
                InputOption::VALUE_OPTIONAL,
                'How to call phpcs',
                './vendor/bin/phpcs -d memory_limit=-1 --standard=vendor/tom/codingstandards/VimeoStandard'
            )
            ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // Get parameters
        $from = $input->getArgument('base_ref');
        $to   = $input->getArgument('to_ref');
        $path = $input->getOption('path');

        // Executables
        $git_cmd   = $input->getOption('git-cmd');
        $phpcs_cmd = $input->getOption('phpcs-cmd');

        if ($output->isDebug()) {
            /* @var $table \Symfony\Component\Console\Helper\TableHelper */
            $table = $this->getHelper('table');
            $table->addRows([
                    ['From', $from],
                    ['To', $to],
                    ['Path', $path],
                    ['git', $git_cmd],
                    ['PHPCS', $phpcs_cmd]
                ]);
            $table->render($output);
        }

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

        // Debug output
        if ($output->isDebug()) {
            $files = $diffreader->getFiles();
            $output->writeln(sprintf('<info><options=bold>Running PHPCS on the following %d file(s):</options=bold></info>', count($files)));
            foreach ($files as $file) {
                $output->writeln(sprintf("\t* %s", str_replace($path, '.', $file)));
            }
        }

        // Run phpcs just on the files in the diff, returning checkstyle xml
        $concatedFiles     = join(' ', array_map('escapeshellarg', $diffreader->getFiles()));
        $checkstyle_output = shell_exec(sprintf('%s --report=checkstyle %s', $phpcs_cmd, $concatedFiles));

        if ($output->isDebug()) {
            $output->writeln('<info>Cleaning checkstyle output</info>');
        }

        // Clean up the checkstyle output
        $checkstyle_output = preg_replace('~\s*(<([^>]*)>[^<]*</\2>|<[^>]*>)\s*~', '\1', $checkstyle_output);

        // Run CheckStyleFilter on checkstyle
        $filter = new CheckStyleFilter(simplexml_load_string($checkstyle_output));
        $filter->addFilter($diffreader);
        $filter->filter();
        echo $filter->asXML();
    }
}

$application = new Application();
$application->add(new GreetCommand);
$application->run();


