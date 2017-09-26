<?php

namespace Bconnect\Docksal\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\DialogHelper;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Filesystem\Filesystem;
use SebastianBergmann\Diff\Differ;
use Github\Client;
use Bconnect\Docksal\Catalog;

class Install extends Command
{
    protected $path;
    protected $catalog;
    protected $output;
    protected $target;

    protected function configure()
    {
        $this->setName('install')
             ->setDescription('Install phar into system wide binary directory')
             ->addArgument('path', InputArgument::OPTIONAL, 'Path to install to', '.')
             ->addOption('repository', 'r', InputOption::VALUE_OPTIONAL, 'GitHUB Catalog to use', 'https://github.com/b-connect/docksal-catalog')
             ->addOption('profile', 'p', InputOption::VALUE_OPTIONAL, 'GitHUB Catalog to use', false)
             ->addOption('token', 't', InputOption::VALUE_OPTIONAL, 'Github auth token for rate limits', false);
        $this->path = dirname(__FILE__) . '/../../../docksal';
        $this->files = new Filesystem();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->target = realpath($input->getArgument('path')) . '/.docksal';

        $this->catalog = new Catalog($input->getOption('repository'), $output, $input->getOption('token'));
        $this->output = $output;
        $this->input = $input;
        $profiles = $this->catalog->readCatalog();
        $helper = $this->getHelper('question');
        if ($this->files->exists($this->target)) {
            $question = new ChoiceQuestion('.docksal already exists in this location. Delete/Ignore or overwrite this folder. ', ['I' => 'Ignore', 'd' => 'Delete'], 'I');      
            if ($helper->ask($input, $output, $question) == 'd') {
                $this->files->remove($this->target);
            }
        }
        $profile = $input->getOption('profile');
        if ($profile === FALSE || !isset($profiles[$profile])) {
            $profileQuestion = [];
            foreach ($profiles as $key => $item) {
                $profileQuestion[$key] = '<options=bold>' . $item['info']['title'] . '</>';
            }
            $question = new ChoiceQuestion('Which profile do you want to install. ', $profileQuestion, null);      
            $profile = $helper->ask($input, $output, $question);
        }

        $this->writeCatalog($profiles[$profile]);
    }

    protected function writeCatalog($item) {
        $this->output->writeln('Install: ' . $item['info']['title']);
        $fileInfo = $this->catalog->show($item['path'] . '/contents');
        foreach ($fileInfo as $info) {
            $this->write($info);
        }
        return;
    }

    protected function askForDiff($localPath, $contents) {
        $originalFile = file_get_contents($this->target . '/' . $localPath);
        $differ = new Differ();
        $patch = $differ->diff($originalFile, $contents);
        $patch = explode("\n" , $patch);
        foreach ($patch as $key => $p) {
            if (strlen($p) === 0) {
                $p = ' ';
            }
            switch ($p{0}) {
                case '+':
                    $patch[$key] = '<info>' . $p . '</info>';
                break;
                case '-':
                    $patch[$key] = '<error>' . $p . '</error>';
                break;
                default:
                    $patch[$key] = '<comment>' . $p . '</comment>';
            }
        }
        $this->output->writeln($patch);
    }

    private function write($item) {
        $localPath = explode('contents/', $item['path']);
        $localPath = array_pop($localPath);
        
        if ($item['type'] === 'dir') {
            $fileInfo = $this->catalog->show($item['path']);
            foreach ($fileInfo as $info) {
                $this->write($info);
            }
            return;
        }
        $this->output->writeln('<comment>Download file ' . $localPath .'</comment>');
        $contents = $this->catalog->download($item['path']);
        $fileOp = 'Y';

        if ($this->files->exists($this->target . '/' . $localPath)) {
            $helper = $this->getHelper('question');
            $question = new ChoiceQuestion('File ('.$localPath.') already exists. ', ['Y' => 'Overwrite','k' => 'Keep original','d' => 'Show diff'], 'Y');      
            while (($fileOp = $helper->ask($this->input, $this->output, $question)) === 'd') {
                $this->askForDiff($localPath, $contents);
            }
            if ($fileOp === 'Y') {
                $this->files->remove($this->target . '/' . $localPath);
            }
        }
        if ($fileOp !== 'k') {
            $this->files->dumpFile(
                $this->target . '/' . $localPath,
                $contents
            );
        }
        $this->output->writeln('<info>Ready</info>');
        return;

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