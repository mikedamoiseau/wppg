<?php

namespace App\Command;

use App\Config\ConfigExporter;
use App\Module\DockerCompose;
use App\Module\EditorConfig;
use App\Module\Git;
use App\Module\ProjectInfo;
use App\Module\WordPressConfigurator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;

define('VIEWS_PATH', APP_FOLDER . '/views/');

class CreateProject extends Command {

  // the name of the command (the part after "bin/console")
  protected static $defaultName = 'new';

  /** @var QuestionHelper */
  protected $questionHelper;

  /** @var InputInterface $input */
  protected $input;

  /** @var OutputInterface $output */
  protected $output;

  /** @var array */
  private $modules = [
    ProjectInfo::class,
    WordPressConfigurator::class,
    DockerCompose::class,
    Git::class,
    EditorConfig::class,
  ];

  /** @var array */
  private $options = [];

  /** @var array  */
  private $loadedModules = [];

  /** @var \Twig\Environment */
  protected $twig;

  /** @var array */
  private $executionOptions = [];

  protected function configure() {
    $this
      ->setDescription('wppg new [--cex=="path/to/the/file"] [--cexf="json|yaml"]')
      ->setDefinition(
        new InputDefinition([
          new InputOption('cex', NULL, InputOption::VALUE_OPTIONAL, 'Export the configuration to a file', false),
          new InputOption('cexf', NULL, InputOption::VALUE_REQUIRED, 'Format of the exported configuration file ("yaml" or "json")', 'yaml'),
        ])
      );
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    $this->input  = $input;
    $this->output = $output;
    $this->questionHelper = $this->getHelper('question');
    $this->initTwig();

    $this->executionOptions = $input->getOptions();

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

    $this->instantiateModules();
    $this->runModules();
    $this->summarizeModules();

    if ($this->shouldExportConfig()) {
      $this->saveConfigFile(
        $this->exportModules(),
        $this->getExportConfigType()
      );
    } else {
      $this->executeModules();
    }

  }

  private function saveConfigFile(array $data, string $format): bool {
    try {
      return (new ConfigExporter($format))->export(
        $data,
        $this->getExportConfigPath()
      );
    } catch (\Exception $e) {
      echo $e->getMessage();
      // Nothing to do
    }

    return FALSE;
  }

  private function shouldExportConfig(): bool {
    return $this->executionOptions['cex'] !== false;
  }

  private function getExportConfigPath(): string {
    return !empty($this->executionOptions['cex']) ?
      $this->executionOptions['cex'] :
      'wppg';
  }

  private function getExportConfigType(): string {
    return $this->executionOptions['cexf'] ?? 'yaml';
  }

  /**
   * Return the user's home directory.
   */
  private function getServerHome() {
    // getenv('HOME') isn't set on Windows and generates a Notice.
    $home = getenv('HOME');
    if (!empty($home)) {
      // home should never end with a trailing slash.
      $home = rtrim($home, '/');
    }
    elseif (!empty($_SERVER['HOMEDRIVE']) && !empty($_SERVER['HOMEPATH'])) {
      // home on windows
      $home = $_SERVER['HOMEDRIVE'] . $_SERVER['HOMEPATH'];
      // If HOMEPATH is a root directory the path can end with a slash. Make sure
      // that doesn't happen.
      $home = rtrim($home, '\\/');
    }
    return empty($home) ? NULL : $home;
  }

  /**
   * Initialize the Twig component
   */
  private function initTwig(): void {
    $loader = new \Twig\Loader\FilesystemLoader(APP_FOLDER . '/twig/templates');

    $home_folder = $this->getServerHome();
    $possible_cache_folders = array_filter([
      // @see https://stackoverflow.com/a/32528391
      $home_folder ? $home_folder . '/.wppg/cache' : NULL,
      '/tmp/wppg',
      APP_FOLDER . '/twig/compilation_cache'
    ]);

    $selected_cache_folder = '';
    foreach ($possible_cache_folders as $cache_folder) {
      if (is_dir($cache_folder)) {
        $selected_cache_folder = $cache_folder;
        break;
      }

      if (mkdir($cache_folder, 0777, true)) {
        $selected_cache_folder = $cache_folder;
        break;
      }
    }

    $this->twig = new \Twig\Environment($loader, [
      'cache' => !empty($selected_cache_folder) ? $selected_cache_folder : FALSE,
    ]);

    // an anonymous function
    $filter = new \Twig\TwigFilter('bashescape', function ($string) {
      return addcslashes($string, '$`"\\');
    }, ['is_safe' => ['html']]);
    $this->twig->addFilter($filter);
  }

  private function instantiateModules() {
    $this->loadedModules = array_map(
      function ($className) {
        return new $className(
          $this->input,
          $this->output,
          $this->questionHelper,
          $this->twig
        );
      },
      $this->modules
    );
  }

  private function runModules() {
    foreach ($this->loadedModules as $plugin) {
      $this->output->writeln('<info>' . $plugin->getName() . '</info>');

      $this->options = array_merge(
        $this->options,
        $plugin->run()
      );
    }
  }

  private function summarizeModules() {
    $summary = [];
    foreach ($this->loadedModules as $idx => $plugin) {
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
    $correct  = $this->questionHelper->ask($this->input, $this->output, $question);

    if (!$correct) {
      throw new \Exception('The creation of the project has been cancelled.');
    }
  }

  private function executeModules() {
    foreach ($this->loadedModules as $plugin) {
      $plugin->execute($this->options);
    }
  }

  private function exportModules() {
    $exported_config = [
      'generator' => 'wppg',
      'version' => APP_VERSION,
      'creation' => (new \DateTime())->format('c'),
    ];

    foreach ($this->loadedModules as $module) {
      $data = $module->export($this->options);

      if (empty($data)) {
        continue;
      }

      $exported_config = array_merge(
        $exported_config,
        [$module->getKey() => $data]
      );
    }

    return $exported_config;
  }

}
