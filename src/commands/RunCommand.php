<?php
namespace Cologne\Commands;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class RunCommand extends Command
{
    /**
     * @param Command         $diff_command
     * @param InputInterface  $input
     * @param OutputInterface $output
     * @param array           $extras
     *
     * @throws \Exception
     */
    public function callAgain(Command $diff_command, InputInterface $input, OutputInterface $output, $extras = [])
    {
        $definition = $diff_command->getDefinition();
        $arguments  = array_intersect_key($input->getArguments(), $definition->getArguments());
        $options    = array_intersect_key($input->getOptions(), $definition->getOptions());
        foreach (array_keys($options) as $option) {
            $options['--' . $option] = $options[$option];
            unset($options[$option]);
        }
        $diff_input = new ArrayInput(array_merge($arguments, $options, $extras), $definition);

        $diff_command->run($diff_input, $output);
    }

    protected function configure()
    {
        $this
            ->setName('run')
            ->setDescription('Run the whole cologne suite')
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
        // Call the diff maker
        /* @var $diff_command \Cologne\Commands\DiffCommand */
        $diff_command = $this->getApplication()->find('diff');
        $this->callAgain($diff_command, $input, $output);

        // Get the diff reader
        $diffreader = $diff_command->getDiffReader();

        // Get the files from the diff reader
        $files = $diffreader->getFiles();

        // Run the checkstyle command, passing it the files from the diff reader
        /* @var $style_command \Cologne\Commands\CheckstyleCommand */
        $style_command = $this->getApplication()->find('checkstyle');
        $this->callAgain($style_command, $input, $output, ['files' => $files, '--hide-output' => true]);

        // Get the checkstyle output
        $checkstyle = $style_command->getCheckstyle();

        // Add the diff reader filter
        $checkstyle->addFilter($diffreader);

        // Filter it out
        $checkstyle->filter();

        // Output
        echo $checkstyle->asXML();
    }
}
