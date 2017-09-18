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
use Symfony\Component\Yaml\Yaml;
use Github\Client;

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
        $this->client = new Client();
        $this->files = new Filesystem();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if ($input->getOption('token') !== FALSE) {
            $output->writeln('Authenticate with ' . $input->getOption('token'));
            $this->client->authenticate($input->getOption('token'),  null, Client::AUTH_HTTP_TOKEN);
        }
        $this->target = realpath($input->getArgument('path')) . '/.docksal';

        $repository = explode('/', $input->getOption('repository'));
        $this->repository = array_pop($repository);
        $this->user = array_pop($repository);
        $this->output = $output;
        $profiles = $this->readCatalog();
        $helper = $this->getHelper('question');
        if ($this->files->exists($this->target)) {
            $question = new ChoiceQuestion('.docksal already exists in this location. Delete/Ignore or overwrite this folder. ', [ 'd' => 'Delete', 'i' => 'Ignore', 'o' => 'Overwrite'], 'd');      
            switch ($helper->ask($input, $output, $question)) {
                case 'd':
                    $this->files->remove($this->target . '/.docksal');
                break;
                default:
                break;
            }
        }
        $profile = $input->getOption('profile');
        if ($profile === FALSE || !isset($profiles[$profile])) {
            $profileQuestion = [];
            foreach ($profiles as $key => $item) {
                $profileQuestion[$key] = $item['info']['title'];
            }
            $question = new ChoiceQuestion('Which profile do you want to install. ', $profileQuestion, null);      
            $profile = $helper->ask($input, $output, $question);
        }

        $this->writeCatalog($profiles[$profile]);
    }

    protected function writeCatalog($item) {
        $this->output->writeln('Install: ' . $item['info']['title']);
        $fileInfo = $this->client->api('repo')->contents()->show($this->user, $this->repository, $item['path'] . '/contents', 'master');
        foreach ($fileInfo as $info) {
            $this->write($info);
        }
        return;
    }

    private function write($item) {
        $localPath = explode('contents/', $item['path']);
        $localPath = array_pop($localPath);
        
        if ($item['type'] === 'dir') {
            $fileInfo = $this->client->api('repo')->contents()->show($this->user, $this->repository, $item['path'], 'master');
            foreach ($fileInfo as $info) {
                $this->write($info);
            }
            return;
        }
        $this->output->write('<comment>Download file ' . $localPath .' </comment>');
        $this->files->dumpFile(
            $this->target . '/' . $localPath,
            $this->client->api('repo')->contents()->download($this->user, $this->repository, $item['path'] , 'master')
        );
        $this->output->writeln('<info>Ready</info>');
        return;

    }

    protected function readCatalog() {
        $this->output->writeln('Reading catalog on' . $this->user . '/' . $this->repository);
        $catalogInfo = [];
        $catalog = array_filter($this->client->api('repo')->contents()->show($this->user, $this->repository, 'catalog', 'master'),function($value, $key) use (&$catalogInfo) {
            if ($value['type'] !== 'dir') {
                return FALSE;
            }
            if ($this->client->api('repo')->contents()->exists($this->user, $this->repository, 'catalog', 'master', $value['path'] . '/index.yml', 'master')
                && ($content = $this->client->api('repo')->contents()->download($this->user, $this->repository, $value['path'] . '/index.yml', 'master'))
                && ($content = Yaml::parse($content))) {
              $this->output->writeln('<info>Found: ' . $content['title'] . '</info>');
              $key = explode('/' , $value['path']);
              array_shift($key);
              $key = implode('/', $key);
              $catalogInfo[$key] = [
                'path' => $value['path'],
                'info' => $content
              ];
              return TRUE;
            }
            return FALSE;
          }, ARRAY_FILTER_USE_BOTH);
        return $catalogInfo;
        
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