<?php

namespace App\Module;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Filesystem\Filesystem;
use Twig\Environment;

Class ProjectInfo extends AbstractModule {
  const SLUG = 'project_info';

  /**
   * {@inheritDoc}
   */
  public function getName(): string {
    return 'Project Info';
  }

  /**
   * {@inheritDoc}
   */
  public function run(): array {
    $question = new Question(
      'Please enter the name of the project: ',
      'WPPG WordPress Project'
    );
    $question->setValidator(
      function ($answer) {
        if (!is_string($answer) || empty($answer)) {
          throw new \RuntimeException(
            'The name of the project must be a valid name.'
          );
        }

        return $answer;
      }
    );
    $question->setMaxAttempts(2);

    $this->options['project_name'] = $this->helper->ask(
      $this->input,
      $this->output,
      $question
    );
    $this->options['project_slug'] = strtolower(
      trim(
        preg_replace('/[^A-Za-z0-9-]+/', '-', $this->options['project_name'])
      )
    );

    return [self::SLUG => $this->options];
  }

  /**
   * {@inheritDoc}
   */
  public function summarize(): array {
    return [
      ['Project Name' => $this->options['project_name']],
      ['Project slug' => $this->options['project_slug']],
    ];
  }

  /**
   * {@inheritDoc}
   */
  public function execute(array $options): void {
    $fileSystem = new Filesystem();

    $fileSystem->mkdir($this->options['project_slug'], 0777);
    $fileSystem->mkdir($this->options['project_slug'] . '/html', 0777);
    $fileSystem->mkdir($this->options['project_slug'] . '/development', 0777);
    $fileSystem->mkdir(
      $this->options['project_slug'] . '/development/docker/php',
      0777
    );
    $fileSystem->mkdir(
      $this->options['project_slug'] . '/development/docker/php/scripts',
      0777
    );
  }

}
