<?php
namespace Cologne\Commands;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class CheckstyleCommand extends Command
{
    /**
     * @var \CheckStyleFilter
     */
    protected $checkstyle;

    /**
     * @return \CheckStyleFilter
     */
    public function getCheckstyle()
    {
        return $this->checkstyle;
    }

    protected function configure()
    {
        $this
            ->setName('checkstyle')
            ->setDescription('Run checkstyle')
            ->addOption(
                'hide-output',
                null,
                InputOption::VALUE_NONE,
                'Whether ot not to output the checkstyle'
            )
            ->addOption(
                'path',
                null,
                InputOption::VALUE_OPTIONAL,
                'What path would you like to work in?',
                getcwd()
            )
            ->addOption(
                'phpcs-cmd',
                null,
                InputOption::VALUE_OPTIONAL,
                'How to call phpcs',
                './vendor/bin/phpcs -d memory_limit=-1 --standard=vendor/tom/codingstandards/VimeoStandard'
            )
            ->addArgument('files', InputArgument::IS_ARRAY | InputArgument::OPTIONAL)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // Get parameters
        $path = $input->getOption('path');
        $files = $input->getArgument('files');

        // Executables
        $phpcs_cmd = $input->getOption('phpcs-cmd');

        // Debug output
        if ($output->isDebug()) {
            if (!empty($files)) {
                $output->writeln(sprintf('<info><options=bold>Running PHPCS on the following %d file(s):</options=bold></info>', count($files)));
                foreach ($files as $file) {
                    $output->writeln(sprintf("\t* %s", str_replace($path, '.', $file)));
                }
            } else {
                $output->writeln(sprintf('<info><options=bold>Running PHPCS on %s</options=bold></info>', $path));
            }
        }

        if (empty($files)) {
            $files = [$path];
        }

        // Run phpcs just on the files in the diff, returning checkstyle xml
        $concatedFiles     = join(' ', array_map('escapeshellarg', $files));
        $checkstyle_output = shell_exec(sprintf('%s --report=checkstyle %s', $phpcs_cmd, $concatedFiles));

        if ($output->isDebug()) {
            $output->writeln('<info>Cleaning checkstyle output</info>');
        }

        // Clean up the checkstyle output
        $checkstyle_output = preg_replace('~\s*(<([^>]*)>[^<]*</\2>|<[^>]*>)\s*~', '\1', $checkstyle_output);

        // Run CheckStyleFilter on checkstyle
        $xml              = simplexml_load_string($checkstyle_output);
        if (!$xml) {
            throw new \Exception('Invalid checkstyle: ' . $checkstyle_output);
        }
        $this->checkstyle = new \CheckStyleFilter($xml);

        if (!$input->getOption('hide-output')) {
            echo $this->checkstyle->asXML();
        }
    }
}
