<?php

namespace Bconnect\Docksal;
use Symfony\Component\Console\Application as BaseApplication;

class App extends BaseApplication {
  public function __construct()
  {
      parent::__construct('docksal', '@git_tag@');
      $this->add(new Command\Install());
  }
}