<?php

namespace App\Command;

use App\Plugin\DockerCompose;
use App\Plugin\EditorConfig;
use App\Plugin\Git;
use App\Plugin\ProjectInfo;
use App\Plugin\WordPressConfigurator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;

define('VIEWS_PATH', APP_FOLDER . '/views/');

class CreateProject extends Command {

  // the name of the command (the part after "bin/console")
  protected static $defaultName = 'new';

  protected $helper;

  /** @var InputInterface $input */
  protected $input;

  /** @var OutputInterface $output */
  protected $output;

  /** @var array */
  private $plugins = [
    ProjectInfo::class,
    WordPressConfigurator::class,
    DockerCompose::class,
    Git::class,
    EditorConfig::class,
  ];

  /** @var array */
  private $options = [];

  /** @var array  */
  private $loadedPlugins = [];

  /** @var \Twig\Environment */
  protected $twig;

  protected function execute(InputInterface $input, OutputInterface $output) {
    $this->input  = $input;
    $this->output = $output;
    $this->helper = $this->getHelper('question');
    $this->initTwig();

    $this->output->writeln(
      "Wizard in action... Let's start with the <info>WordPress</info> project!"
    );
    $this->output->writeln("<comment>Pro tips:</comment>");
    $this->output->writeln(
      "<comment>1. Most of the time you can use the up and down keys to select the answers, it is easier!</comment>"
    );
    $this->output->writeln(
      "<comment>2. Control-C to cancel the creation of the project</comment>"
    );

    $this->instantiatePlugins();
    $this->runPlugins();
    $this->summarizePlugins();
    $this->executePlugins();
  }

  /**
   * Initialize the Twig component
   */
  private function initTwig(): void {
    $loader = new \Twig\Loader\FilesystemLoader(APP_FOLDER . '/twig/templates');

    $this->twig = new \Twig\Environment($loader, [
      'cache' => APP_FOLDER . '/twig/compilation_cache',
    ]);

    // an anonymous function
    $filter = new \Twig\TwigFilter('bashescape', function ($string) {
      return addcslashes($string, '$`"\\');
    }, ['is_safe' => ['html']]);
    $this->twig->addFilter($filter);
  }

  private function instantiatePlugins() {
    $this->loadedPlugins = array_map(
      function ($className) {
        return new $className(
          $this->input,
          $this->output,
          $this->helper,
          $this->twig
        );
      },
      $this->plugins
    );
  }

  private function runPlugins() {
    foreach ($this->loadedPlugins as $plugin) {
      $this->output->writeln('<info>' . $plugin->getName() . '</info>');

      $this->options = array_merge(
        $this->options,
        $plugin->run()
      );
    }
  }

  private function summarizePlugins() {
    $summary = [];
    foreach ($this->loadedPlugins as $idx => $plugin) {
      if ($idx) {
        $summary[] = new TableSeparator();
      }

      $summary[] = $plugin->getName();
      $summary   = array_merge(
        $summary,
        $plugin->summarize()
      );
    }

    $io = new SymfonyStyle($this->input, $this->output);
    $io->title('Please confirm before proceeding');

    $io->definitionList(...$summary);

    $question = new ConfirmationQuestion('Would you like to proceed? [Yes/no] ', TRUE);
    $correct  = $this->helper->ask($this->input, $this->output, $question);

    if (!$correct) {
      throw new \Exception('The creation of the project has been cancelled.');
    }
  }

  private function executePlugins() {
    foreach ($this->loadedPlugins as $plugin) {
      $plugin->execute($this->options);
    }
  }

}
