<?php

namespace Bconnect\Docksal;

use Github\Client;
use Symfony\Component\Yaml\Yaml;

class Catalog {

  private $catalog;
  private $repository;
  private $user;
  private $output;
  const CATALOG_PATH = 'catalog';
  const CATALOG_BRANCH = 'master';

  function __construct($repo, $output, $token = false) {
    $this->client = new Client();
    $repository = explode('/', $repo);
    $this->repository = array_pop($repository);
    $this->user = array_pop($repository);
    $this->output = $output;

    if ($token !== false) {
      $this->client->authenticate($token,  null, Client::AUTH_HTTP_TOKEN);
    }
  }

  public function show($path = self::CATALOG_PATH) {
    try {
      return $this->client->api('repo')
        ->contents()
        ->show($this->user, $this->repository, $path, self::CATALOG_BRANCH);
    } catch (\Github\Exception\RuntimeException $ex) {
      print_r('Got an exception on show ' . $this->user .'/'. $this->repository . ' - ' . $path);
    }
  }

  public function download($file) {
    try {
      return $this->client->api('repo')
        ->contents()
        ->download($this->user, $this->repository, $file, self::CATALOG_BRANCH);
    } catch (\Github\Exception\RuntimeException $ex) {
      print_r('Got an exception on download');
    }
  }

  public function exists($file) {
    try {
      return $this->client->api('repo')
        ->contents()
        ->exists($this->user, $this->repository, $file, self::CATALOG_BRANCH);
    } catch (\Github\Exception\RuntimeException $ex) {
      print_r('Got an exception on exists', $ex->getMessage());
    }
  }

  public function readCatalog($reset = false) {
    if (!empty($this->catalog) && $reset === false) {
      return $this->catalog;
    }
    $this->catalog = [];
    $catalogInfo = [];

    
    
    $catalog = array_filter($this->show(),function($value, $key) use (&$catalogInfo) {
      if ($value['type'] !== 'dir') {
          return FALSE;
      }
      if ($this->exists( $value['path'] . '/index.yml')
          && ($content = $this->download($value['path'] . '/index.yml'))
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
    $this->catalog = $catalogInfo;
    return $this->catalog;
  }

}
