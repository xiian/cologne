<?php
namespace Cologne\Commands;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class DiffCommand extends Command
{
    protected $diffreader;

    /**
     * @return mixed
     */
    public function getDiffreader()
    {
        return $this->diffreader;
    }

    protected function configure()
    {
        $this
            ->setName('diff')
            ->setDescription('Generate diff')
            ->addOption(
                'hide-output',
                null,
                InputOption::VALUE_NONE,
                'Whether ot not to output the diff'
            )
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

        if ($output->isDebug()) {
            /* @var $table \Symfony\Component\Console\Helper\TableHelper */
            $table = $this->getHelper('table');
            $table->addRows([
                    ['From', $from],
                    ['To', $to],
                    ['Path', $path],
                    ['git', $git_cmd]
                ]);
            $table->render($output);
        }

        // Get to the root of the matter
        chdir($path);

        // Run git diff
        $diff_output = shell_exec(sprintf('%s diff %s', $git_cmd, escapeshellarg($from)));

        // Run diff reader on the diff
        $diffreader = new \DiffReader();
        $diffreader->setBasePath($path);
        $diffreader->setString($diff_output);
        try {
            $diffreader->parse();
        } catch (\Exception $e) {
            echo $e->getMessage() . PHP_EOL;
            echo $diff_output;
            die(PHP_EOL);
        }

        $this->diffreader = $diffreader;

        if (!$input->getOption('hide-output')) {
            foreach($diffreader->diff as $line) {
                echo $line . PHP_EOL;
            }
        }
    }
}
