<?php

namespace Bconnect\Docksal\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\DialogHelper;
use Symfony\Component\Filesystem\Filesystem;

class Install extends Command
{
    protected $path;

    protected function configure()
    {
        $this->setName('install')
             ->setDescription('Install phar into system wide binary directory')
             ->addArgument('path', InputArgument::OPTIONAL, 'Path to install to', '.');
        $this->path = dirname(__FILE__) . '/../../../docksal';
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->copyDir($input->getArgument('path'), $output);
    }

    protected function copyDir($target, $output) {
        $target = $target . '/.docksal';
        $fileSystem = new Filesystem();
        $fileSystem->mkdir($target);

        $output->writeln('Copy ' . $this->path . ' to ' . $target);
        
        $directoryIterator = new \RecursiveDirectoryIterator($this->path, \RecursiveDirectoryIterator::SKIP_DOTS);
        $iterator = new \RecursiveIteratorIterator($directoryIterator, \RecursiveIteratorIterator::SELF_FIRST);
        foreach ($iterator as $item) {
            if ($item->isDir()) {
                $targetDir = $target.DIRECTORY_SEPARATOR.$iterator->getSubPathName();
                $fileSystem->mkdir($targetDir);
            } else {
                $targetFilename = $target.DIRECTORY_SEPARATOR.$iterator->getSubPathName();
                $fileSystem->copy($item, $targetFilename);
            }
        }
    }
}