<?php

namespace App\Plugin;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;

Class DockerCompose extends AbstractPlugin {

  const SLUG = 'docker_compose';

  /** @var array Array of services options for the file docker-compose.yml */
  private $services;

  /** @var array Array of services options for the file docker-compose.override.yml */
  private $extraServices;

  /**
   * {@inheritDoc}
   */
  public function getName(): string {
    return 'Docker Compose';
  }

  /**
   * {@inheritDoc}
   */
  public function run(): array {
    $this->services = [];
    $this->extraServices = [];

    $this->output->writeln('<comment>Web server:</comment>');
    $this->services = array_merge($this->services, $this->getWebServerConfig());

    $this->output->writeln('<comment>Database:</comment>');
    $this->services = array_merge($this->services, $this->getDbServerConfig());

    $this->output->writeln('<comment>PHPMyAdmin:</comment>');
    $this->extraServices = array_merge(
      $this->extraServices,
      $this->getPHPMyAdmin()
    );

    return [self::SLUG => $this->options];
  }

  private function getWebServerConfig() {
    // Which web server?
    $question  = new ChoiceQuestion(
      'Which web server do you want to use? [Apache]',
      ['Apache', 'Nginx'],
      0
    );
    $webServer = $this->helper->ask($this->input, $this->output, $question);
    $webServer = strtolower($webServer);

    $question = new Question(
      'Which port number should the web server use? [80] ', '80'
    );
    $question->setValidator(
      function ($answer) {
        $portNumber = (int) $answer;
        if (($portNumber <= 0) || ($portNumber > 65535)) {
          throw new \RuntimeException('Invalid port number!');
        }

        return $answer;
      }
    );
    $webServerPort = $this->helper->ask($this->input, $this->output, $question);

    // Which version of PHP?
    $question   = new ChoiceQuestion(
      'Which version of PHP do you want to use? [7.2]',
      ['7.2', '7.1', '7.0', '5.6', '5.5', '5.4'],
      0
    );
    $phpVersion = $this->helper->ask($this->input, $this->output, $question);

    $this->options['webserver']      = $webServer;
    $this->options['webserver_port'] = $webServerPort;
    $this->options['php_version']    = $phpVersion;
    //$this->options['webserver_container'] = $webServerContainerName;

    $blocks = [];
    if ($webServer == 'apache') {
      $blocks['php'] = [
        'image'   => sprintf('chialab/php-dev:%s-apache', $phpVersion),
        'ports'   => [sprintf('%d:%d', $webServerPort, 80)],
        'volumes' => [
          './:/var/www',
          './development/docker/vhost.conf:/etc/apache2/sites-enabled/000-default.conf',
        ],
      ];
    }
    else {
      // Nginx
      $blocks['nginx'] = [
        'image'   => 'nginx:latest',
        'ports'   => [sprintf('%d:%d', $webServerPort, 80)],
        'volumes' => [
          './:/var/www',
          './development/docker/vhost.conf:/etc/nginx/conf.d/default.conf',
        ],
        'links'   => ['php'],
      ];

      $blocks['php'] = [
        'image'   => sprintf('chialab/php-dev:%s-fpm', $phpVersion),
        'volumes' => [
          './:/var/www',
        ],
      ];
    }

    $blocks['php']['volumes'][] = './development/docker/php/php-ini-overrides.ini:/usr/local/etc/php/conf.d/99-overrides.ini';
    $blocks['php']['volumes'][] = './development/docker/php/scripts:/scripts';
    //$blocks['php']['entrypoint'] = [
    //  'bash',
    //  '/scripts/entrypoint.sh',
    //];
    $blocks['php']['restart']     = 'on-failure';
    $blocks['php']['working_dir'] = '/var/www/html';
    $blocks['php']['depends_on']  = ['mysql'];

    // WP Cli
    $blocks['wpcli'] = [
      'image'      => sprintf('chialab/php-dev:%s-fpm', $phpVersion),
      'volumes'    => [
        './:/var/www',
        './development/docker/php/scripts:/scripts',
        './development/docker/php/php-ini-overrides.ini:/usr/local/etc/php/conf.d/99-overrides.ini',
      ],
      'entrypoint' => [
        'bash',
        '/scripts/entrypoint.sh',
      ],
      'working_dir' => '/var/www/html',
      'depends_on' => ['mysql'],
    ];

    // service for Web server + PHP
    return $blocks;
  }

  private function getDbServerConfig() {
    // Database
    $dbs      = ['mariadb', 'mysql'];
    $question = new Question(
      'Which database manager should the project use? [mariadb] ', 'mariadb'
    );
    $question->setAutocompleterValues($dbs);
    $dbManager = $this->helper->ask($this->input, $this->output, $question);
    $dbManager = strtolower($dbManager);

    // Database version
    $versions       = [];
    $defaultVersion = '';
    switch ($dbManager) {
      case 'mysql':
        $versions       = ['8.0', '5.7', '5.6', '5.5'];
        $defaultVersion = '5.7';
        break;
      case 'mariadb':
        $versions       = ['10.4', '10.3', '10.2', '10.1', '10.0'];
        $defaultVersion = '10.4';
        break;
    }
    $question = new Question(
      sprintf(
        'Which version of the database manager should the project use? [%s] ',
        $defaultVersion
      ),
      empty($defaultVersion) ? NULL : $defaultVersion
    );
    $question->setAutocompleterValues($versions);
    $dbManagerVersion = $this->helper->ask(
      $this->input,
      $this->output,
      $question
    );
    $dbManagerVersion = strtolower($dbManagerVersion);

    // Confirm Database service name
    $dbManagerService = sprintf('%s:%s', $dbManager, $dbManagerVersion);
    $question         = new Question(
      sprintf(
        'Here is your last chance to change the name of the docker image for the database [%s] ',
        $dbManagerService
      ),
      $dbManagerService
    );
    $dbManagerService = $this->helper->ask(
      $this->input,
      $this->output,
      $question
    );
    $dbManagerService = strtolower($dbManagerService);

    $question = new Question(
      'Which port number should the db server use? [3306] ', '3306'
    );
    $question->setValidator(
      function ($answer) {
        $portNumber = (int) $answer;
        if (($portNumber <= 0) || ($portNumber > 65535)) {
          throw new \RuntimeException('Invalid port number!');
        }

        return $answer;
      }
    );
    $dbPort = $this->helper->ask($this->input, $this->output, $question);

    $question = new Question(
      'What is the name of the database? [wp] ',
      'wp'
    );
    $dbName   = $this->helper->ask($this->input, $this->output, $question);

    $question = new Question(
      'What is the password of the root user? [wp] ',
      'wp'
    );
    $question->setValidator(
      function ($answer) {
        if (empty($answer)) {
          throw new \RuntimeException('Invalid root password.');
        }

        return $answer;
      }
    );
    $dbRootPassword = $this->helper->ask(
      $this->input,
      $this->output,
      $question
    );

    $this->options['db']               = $dbManager;
    $this->options['db_version']       = $dbManagerVersion;
    $this->options['db_port']          = $dbPort;
    $this->options['db_service_name']  = $dbManagerService;
    $this->options['db_name']          = $dbName;
    $this->options['db_root_password'] = $dbRootPassword;

    return [
      'mysql' => [
        'image'       => $dbManagerService,
        'ports'       => [sprintf('%d:%d', $dbPort, 3306)],
        'environment' => [
          'MYSQL_DATABASE'      => $dbName,
          'MYSQL_ROOT_PASSWORD' => $dbRootPassword,
        ],
        'volumes'     => [
          'db:/var/lib/mysql',
        ],
        'healthcheck' => [
          'test' => '["CMD-SHELL", \'mysql --database=$$MYSQL_DATABASE --password=$$MYSQL_ROOT_PASSWORD --execute="SELECT count(table_name) > 0 FROM information_schema.tables;" --skip-column-names -B\']',
          'interval' => '30s',
          'timeout' => '10s',
          'retries' => 4,
        ],
      ],
    ];
  }

  private function getPHPMyAdmin() {
    $question          = new ConfirmationQuestion(
      'Do you want to include PHPmyAdmin? [Yes/no] ', TRUE
    );
    $includePhpMyAdmin = $this->helper->ask(
      $this->input,
      $this->output,
      $question
    );

    $this->options['phpmyadmin'] = $includePhpMyAdmin;

    if (!$includePhpMyAdmin) {
      return [];
    }

    return [
      'phpmyadmin' => [
        'image'       => 'phpmyadmin/phpmyadmin',
        'environment' => [
          'PMA_HOST=mysql',
          'PMA_USER=root',
          'PMA_PASSWORD=' . $this->options['db_root_password'],
        ],
        'restart'     => 'always',
        'ports'       => ['8080:80'],
      ],
    ];
  }

  private function buildDockerFile() {
    $content = [
      'version'  => '3',
      'services' => $this->services,
      'volumes'  => ['db' => []],
    ];

    return Yaml::dump($content, 10, 2);
  }

  private function buildDockerOverrideFile() {
    if (empty($this->extraServices)) {
      return [];
    }

    $content = [
      'version'  => '3',
      'services' => $this->extraServices,
    ];

    return Yaml::dump($content, 10, 2);
  }

  /**
   * {@inheritDoc}
   */
  public function summarize(): array {
    // @todo
    return [
      ['Web server' => $this->options['webserver']],
      ['Port' => $this->options['webserver_port']],
      ['PHP version' => $this->options['php_version']],
      ['Database' => $this->options['db']],
      ['Database version' => $this->options['db_version']],
      ['Database port' => $this->options['db_port']],
      ['Database service name' => $this->options['db_service_name']],
      ['Database name' => $this->options['db_name']],
      ['Database root password' => $this->options['db_root_password']],
      ['Include PHPMyAdmin' => $this->options['phpmyadmin'] ? 'yes' : 'no'],
    ];
  }

  /**
   * {@inheritDoc}
   */
  public function execute(array $options): void {
    $filesystem = new Filesystem();

    $this->createDockerComposeYamls($filesystem, $options);
    $this->createFileVhost($filesystem, $options);
    $this->createPhpIniOverrides($filesystem, $options);
    $this->createEntryPointScript($filesystem, $options);
  }

  /**
   * Create the file docker-compose.yml
   *
   * @param \Symfony\Component\Filesystem\Filesystem $filesystem
   * @param array $options The list of options returned by all plugins
   */
  private function createDockerComposeYamls(
    Filesystem $filesystem,
    array $options
  ): void {
    // docker-compose.yml
    $yamlContent = $this->buildDockerFile();
    $filesystem->dumpFile(
      $options['project_info']['project_slug'] . '/docker-compose.yml',
      $yamlContent
    );

    // docker-compose.override.yml
    $yamlContent = $this->buildDockerOverrideFile();
    if (!empty($yamlContent)) {
      $filesystem->dumpFile(
        $options['project_info']['project_slug'] . '/docker-compose.override.yml',
        $yamlContent
      );
    }

  }

  /**
   * @param \Symfony\Component\Filesystem\Filesystem $filesystem
   * @param array $options The list of options returned by all plugins
   *
   * @throws \Twig\Error\LoaderError
   * @throws \Twig\Error\RuntimeError
   * @throws \Twig\Error\SyntaxError
   */
  private function createFileVhost(
    Filesystem $filesystem,
    array $options
  ): void {
    $webServer = $this->options['webserver'];
    $port      = $this->options['webserver_port'];

    $content = $this->twig->render(
      "docker_compose/vhost/{$webServer}.twig",
      ['port' => $port]
    );

    $filesystem->dumpFile(
      $options[ProjectInfo::SLUG]['project_slug'] . '/development/docker/vhost.conf',
      $content
    );
  }

  /**
   * @param \Symfony\Component\Filesystem\Filesystem $filesystem
   * @param array $options The list of options returned by all plugins
   *
   * @throws \Twig\Error\LoaderError
   * @throws \Twig\Error\RuntimeError
   * @throws \Twig\Error\SyntaxError
   */
  private function createPhpIniOverrides(
    Filesystem $filesystem,
    array $options
  ): void {
    $content = $this->twig->render(
      "docker_compose/php/php-ini-overrides.twig"
    );
    $filesystem->dumpFile(
      $options[ProjectInfo::SLUG]['project_slug'] . '/development/docker/php/php-ini-overrides.ini',
      $content
    );
  }

  /**
   * @param \Symfony\Component\Filesystem\Filesystem $filesystem
   * @param array $options The list of options returned by all plugins
   *
   * @throws \Twig\Error\LoaderError
   * @throws \Twig\Error\RuntimeError
   * @throws \Twig\Error\SyntaxError
   */
  private function createEntryPointScript(
    Filesystem $filesystem,
    array $options
  ): void {
    $content = $this->twig->render(
      "docker_compose/wpcli/scripts/entrypoint.twig",
      [
        // 'webserver'        => $this->options['webserver'],
        'webserver'        => 'nginx',
        'db_name'          => $this->options['db_name'],
        'db_root_password' => $this->options['db_root_password'],
        'db_port'          => $this->options['db_port'],
        'project_name'     => $options[ProjectInfo::SLUG]['project_name'],
        'wp_db_prefix'     => $options[WordPressConfigurator::SLUG]['wp_db_prefix'],
        'wp_user_name'     => $options[WordPressConfigurator::SLUG]['wp_user_name'],
        'wp_user_password' => $options[WordPressConfigurator::SLUG]['wp_user_password'],
        'wp_user_email'    => $options[WordPressConfigurator::SLUG]['wp_user_email'],
      ]
    );
    $filesystem->dumpFile(
      $options[ProjectInfo::SLUG]['project_slug'] . '/development/docker/php/scripts/entrypoint.sh',
      $content
    );
  }

}
