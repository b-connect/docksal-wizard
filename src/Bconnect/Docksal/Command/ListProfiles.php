<?php

namespace Bconnect\Docksal\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Bconnect\Docksal\Catalog;

class ListProfiles extends Command
{
    protected function configure()
    {
        $this->setName('list-profiles')
             ->setDescription('Show installable profiles.')
             ->addOption('repository', 'r', InputOption::VALUE_OPTIONAL, 'GitHUB Catalog to use', 'https://github.com/b-connect/docksal-catalog')
             ->addOption('token', 't', InputOption::VALUE_OPTIONAL, 'Github auth token for rate limits', false);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $catalog = new Catalog($input->getOption('repository'), $output, $input->getOption('token'));
        $profiles = $catalog->readCatalog();
        foreach ($profiles as $key => $profile) {
          $output->writeln('<bg=yellow;options=bold>[' . $key .']</> <options=bold>' . $profile['info']['title'] . '</> - ' . $profile['info']['description']);
        }
    }

}