<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;

define('VIEWS_PATH', APP_FOLDER.'/views/');

class CreateProject extends Command
{
  // the name of the command (the part after "bin/console")
  protected static $defaultName = 'new';
  protected $helper;
  /** @var InputInterface $input */
  protected $input;
  /** @var OutputInterface $output */
  protected $output;
  /** @var string The project name */
  protected $projectName = '';
  /** @var string The project slug (folder name, ...) */
  protected $projectSlug = '';
  protected $options = [];

  public function __construct()
  {
    parent::__construct();

    $this->options['project_name'] = '';
    $this->options['project_slug'] = '';

    $this->options['webserver']           = '';
    $this->options['webserver_port']      = '';
    $this->options['php_version']         = '';
    $this->options['webserver_container'] = '';

    $this->options['db']               = '';
    $this->options['db_version']       = '';
    $this->options['db_port']          = '';
    $this->options['db_service_name']  = '';
    $this->options['db_name']          = '';
    $this->options['db_root_password'] = '';

    $this->options['wp_user_name']     = '';
    $this->options['wp_user_email']    = '';
    $this->options['wp_user_password'] = '';
    $this->options['wp_db_prefix']     = '';
  }

  private function loadView($name, $data = [])
  {
    $content = file_get_contents(VIEWS_PATH.$name);
    $content = ! $content ? '' : $content;

    foreach ($data as $name => $value) {
      $varName = '{%'.strtoupper($name).'%}';

      $content = str_replace($varName, $value, $content);
    }

    return $content;
  }

  public function configure()
  {
    $this
      // the short description shown while running "php bin/console list"
      ->setDescription('Creates a new WordPress project.')
      // the full command description shown when running the command with
      // the "--help" option
      ->setHelp('This command allows you to create a new WordPress project.');
  }

  protected function execute(InputInterface $input, OutputInterface $output)
  {
    $this->input  = $input;
    $this->output = $output;
    $this->helper = $this->getHelper('question');

    $this->output->writeln("Wizard in action... Let's start with the <info>WordPress</info> project!");
    $this->output->writeln("<comment>Pro tips:</comment>");
    $this->output->writeln(
      "<comment>1. Most of the time you can use the up and down keys to select the answers, it is easier!</comment>"
    );
    $this->output->writeln("<comment>2. Control-C to cancel the creation of the project</comment>");

    try {
      $this->setProjectName();
      $this->createFolderStructure();
      $this->enterWordPressInfo();
      $this->createDockerComposeFile();
      $this->createReadmeFile();
      $this->createGitIgnoreFile();
      $this->createGitAttributesFile();
      $this->createEditorConfigFile();
      $this->createDockerIgnoreFile();
    } catch (\Exception $e) {

    }
  }

  /**
   * @throws \Exception
   */
  protected function setProjectName()
  {
    $question = new Question('Please enter the name of the project: ', 'Buzzwoo WordPress Project');
    $question->setValidator(
      function ($answer) {
        if ( ! is_string($answer) || empty($answer)) {
          throw new \RuntimeException('The name of the project must be a valid name.');
        }

        return $answer;
      }
    );
    $question->setMaxAttempts(2);

    $this->projectName = $this->helper->ask($this->input, $this->output, $question);
    $this->projectSlug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $this->projectName)));

    $this->options['project_name'] = $this->projectName;
    $this->options['project_slug'] = $this->projectSlug;

    $this->output->writeln(sprintf("Project name: <info>%s</info>", $this->projectName));
    $this->output->writeln(sprintf("Project slug: <info>%s</info>", $this->projectSlug));

    $question = new ConfirmationQuestion('Is this correct? [yes/No] ', false);
    $correct  = $this->helper->ask($this->input, $this->output, $question);

    if ( ! $correct) {
      throw new \Exception('The project name is not correct.');
    }
  }

  /**
   * @throws \Exception
   */
  protected function createFolderStructure()
  {
    $this->output->writeln(sprintf("Creating the folder structure in <info>%s</info>...", $this->projectSlug));

    $fileSystem = new Filesystem();

    $fileSystem->mkdir($this->projectSlug, 0777);
    $fileSystem->mkdir($this->projectSlug.'/html', 0777);
    $fileSystem->mkdir($this->projectSlug.'/development', 0777);
    $fileSystem->mkdir($this->projectSlug.'/development/docker/php', 0777);
    $fileSystem->mkdir($this->projectSlug.'/development/docker/php/scripts', 0777);
  }

  protected function enterWordPressInfo()
  {
    $this->output->writeln("");
    $this->output->writeln("Some questions regarding the configuration of your website...");

    $question                      = new Question(
      'What is the name of the admin account? [admin_buzzwoo]',
      'admin_buzzwoo'
    );
    $this->options['wp_user_name'] = $this->helper->ask($this->input, $this->output, $question);

    $question = new Question(
      'What is the email address of the admin account? [adminwp@buzzwoo.de]',
      'adminwp@buzzwoo.de'
    );
    $question->setValidator(
      function ($answer) {
        if ( ! filter_var($answer, FILTER_VALIDATE_EMAIL)) {
          throw new \RuntimeException('The email address seems invalid.');
        }

        return $answer;
      }
    );
    $this->options['wp_user_email'] = $this->helper->ask($this->input, $this->output, $question);

    $question = new Question('What is the password of the admin account?');
    $question->setValidator(
      function ($answer) {
        // GDPR compliance...

        // at least 12 characters
        $is_complex_enough = (strlen($answer) >= 12);
        // at least 1 lowercase letter
        $is_complex_enough = $is_complex_enough && (0 !== preg_match('/[a-z]/', $answer));
        // at least 1 uppercase letter
        $is_complex_enough = $is_complex_enough && (0 !== preg_match('/[A-Z]/', $answer));
        // at least 1 number
        $is_complex_enough = $is_complex_enough && (0 !== preg_match('/[0-9]/', $answer));
        // at least 1 special character
        $is_complex_enough = $is_complex_enough && (0 !== preg_match(
              '/[!@#$%^&*()\-_=+{};:,<.>\[\]]/',
              $answer
            ));

        if ( ! $is_complex_enough) {
          throw new \RuntimeException(
            'The password must contain at least 12 characters: 1 lowercase letter [a-z], 1 uppercase letter [A-Z], 1 number [0-9] and 1 special symbol [!@#$%^&*()\-_=+{};:,<.>[]].'
          );
        }

        return $answer;
      }
    );
    $this->options['wp_user_password'] = $this->helper->ask($this->input, $this->output, $question);

    $question = new Question('The prefix of your database tables? [wp_] ', 'wp_');
    $this->options['wp_db_prefix'] = $this->helper->ask($this->input, $this->output, $question);
  }

  protected function createDockerComposeFile()
  {
    $this->output->writeln("Let's generate the <info>docker-compose.yml</info>...");

    $services = [];

    $this->output->writeln('');
    $this->output->writeln('<comment>We will now configure the web server...</comment>');
    $services = array_merge($services, $this->getWebServerConfig());

    $this->output->writeln('');
    $this->output->writeln('<comment>We will now configure the database server...</comment>');
    $services = array_merge($services, $this->getDbServerConfig());

    $phpmyadmin = $this->getPHPMyAdmin($services['mysql']);
    $services   = array_merge($services, $phpmyadmin);

    $content = $this->buildDockerFile($services);

    $fileSystem = new Filesystem();
    $fileSystem->dumpFile($this->projectSlug.'/docker-compose.yml', $content);

    // copy associated files (vhost for Apache/Nginx)
    $content = $this->loadView(
      'vhost/'.$this->options['webserver'].'.php',
      ['PORT' => $this->options['webserver_port']]
    );
    $fileSystem->dumpFile($this->projectSlug.'/development/docker/vhost.conf', $content);

    // copy associated files (vhost for Apache/Nginx)
    $content = $this->loadView('php/php-ini-overrides.ini');
    $fileSystem->dumpFile($this->projectSlug.'/development/docker/php/php-ini-overrides.ini', $content);

    // copy entrypoint.sh file
    $content = $this->loadView(
      sprintf('php/scripts/entrypoint-%s.sh', $this->options['webserver']),
      $this->options
    );
    $fileSystem->dumpFile($this->projectSlug.'/development/docker/php/scripts/entrypoint.sh', $content);
  }

  private function buildDockerFile($services)
  {
    $content = [
      'version'  => '3',
      'services' => $services,
      'volumes'  => ['db' => []],
    ];

    return Yaml::dump($content, 10, 2);
  }

  private function getPHPMyAdmin($mysqlInfo)
  {
    $question          = new ConfirmationQuestion('Do you want to include PHPmyAdmin? [Yes/no] ', true);
    $includePhpMyAdmin = $this->helper->ask($this->input, $this->output, $question);
    if ( ! $includePhpMyAdmin) {
      return [];
    }

    return [
      'phpmyadmin' => [
        'image'       => 'phpmyadmin/phpmyadmin',
        'environment' => [
          'PMA_HOST=mysql',
          'PMA_USER=root',
          'PMA_PASSWORD='.$mysqlInfo['environment']['MYSQL_ROOT_PASSWORD'],
        ],
        'restart'     => 'always',
        'ports'       => ['8080:80'],
      ],
    ];
  }

  private function getWebServerConfig()
  {
    // Which web server?
    $question  = new ChoiceQuestion(
      'Which web server do you want to use? [Apache]',
      ['Apache', 'Nginx'],
      0
    );
    $webServer = $this->helper->ask($this->input, $this->output, $question);
    $webServer = strtolower($webServer);

    $question = new Question('Which port number should the web server use? [80] ', '80');
    $question->setValidator(
      function ($answer) {
        $portNumber = (int)$answer;
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

    $this->options['webserver']           = $webServer;
    $this->options['webserver_port']      = $webServerPort;
    $this->options['php_version']         = $phpVersion;
    $this->options['webserver_container'] = $webServerContainerName;

    $blocks = [];
    if ($webServer == 'apache') {
      $blocks = [
        'php' => [
          'image'   => sprintf('chialab/php-dev:%s-apache', $phpVersion),
          'ports'   => [sprintf('%d:%d', $webServerPort, 80)],
          'volumes' => [
            './:/var/www',
            './development/docker/vhost.conf:/etc/apache2/sites-enabled/000-default.conf',
          ],
        ],
      ];
    } else {
      // Nginx
      $blocks = [
        'nginx' => [
          'image'   => 'nginx:latest',
          'ports'   => [sprintf('%d:%d', $webServerPort, 80)],
          'volumes' => [
            './:/var/www',
            './development/docker/vhost.conf:/etc/nginx/conf.d/default.conf',
          ],
          'links'   => ['php'],
        ],
        'php'   => [
          'image'   => sprintf('chialab/php-dev:%s-fpm', $phpVersion),
          'volumes' => [
            './:/var/www',
          ],
        ],
      ];
    }

    $blocks['php']['volumes'][]   = './development/docker/php/php-ini-overrides.ini:/usr/local/etc/php/conf.d/99-overrides.ini';
    $blocks['php']['volumes'][]   = './development/docker/php/scripts:/scripts';
    $blocks['php']['entrypoint']  = ['bash', '/scripts/entrypoint.sh'];
    $blocks['php']['restart']     = 'on-failure';
    $blocks['php']['working_dir'] = '/var/www/html';

    $blocks['php']['depends_on'] = ['mysql'];

    // service for Web server + PHP
    return $blocks;
  }

  private function getDbServerConfig()
  {
    // Database
    $dbs      = ['mysql', 'mariadb'];
    $question = new Question('Which database manager should the project use? [mysql] ', 'mysql');
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
      sprintf('Which version of the database manager should the project use? [%s] ', $defaultVersion),
      empty($defaultVersion) ? null : $defaultVersion
    );
    $question->setAutocompleterValues($versions);
    $dbManagerVersion = $this->helper->ask($this->input, $this->output, $question);
    $dbManagerVersion = strtolower($dbManagerVersion);

    // Confirm Database service name
    $dbManagerService = sprintf('%s:%s', $dbManager, $dbManagerVersion);
    $question         = new Question(
      sprintf('Here is your last chance to change the name of the docker image for the database [%s] ', $dbManagerService),
      $dbManagerService
    );
    $dbManagerService = $this->helper->ask($this->input, $this->output, $question);
    $dbManagerService = strtolower($dbManagerService);

    $question = new Question('Which port number should the db server use? [3306] ', '3306');
    $question->setValidator(
      function ($answer) {
        $portNumber = (int)$answer;
        if (($portNumber <= 0) || ($portNumber > 65535)) {
          throw new \RuntimeException('Invalid port number!');
        }

        return $answer;
      }
    );
    $dbPort = $this->helper->ask($this->input, $this->output, $question);

    $question = new Question(
      sprintf('What is the name of the database? [%s] ', $this->projectSlug),
      $this->projectSlug
    );
    $dbName   = $this->helper->ask($this->input, $this->output, $question);

    $question = new Question('What is the password of the root user? ');
    $question->setValidator(
      function ($answer) {
        if (empty($answer)) {
          throw new \RuntimeException('Invalid root password.');
        }

        return $answer;
      }
    );
    $dbRootPassword = $this->helper->ask($this->input, $this->output, $question);

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
      ],
    ];
  }

  protected function createReadmeFile()
  {
    $this->output->writeln("Creating the file <info>readme.md</info>...");

    $content = '';
    $content .= sprintf("# %s\n", $this->projectName);
    $content .= sprintf("Short description of the project.\n", $this->projectName);

    $content .= sprintf("## Installation\n");

    $content .= sprintf("Download Docker\n```\nhttps://www.docker.com/community-edition\n```\n");
    $content .= sprintf(
      "Clone the repository\n```\ngit clone git@git.buzzwoo.de:buzzwoo/%s.git\n```\n",
      $this->projectSlug
    );

    $content .= sprintf(
      "Setup Docker Container / Images\n```\ncd %s && docker-compose up\n```\n",
      $this->projectSlug
    );

    $content .= sprintf(
      "SSH Into the Docker container\n```\ncd %s && docker-compose exec php bash\n```\n",
      $this->projectSlug
    );

    $content .= sprintf(
      "Composer Install\n```\ncd /var/www/html && composer install && composer dump-autoload\n```\n"
    );

    $content .= sprintf("## Documentation\n");

    $content .= sprintf("### Generate the documentation\n");

    $content .= sprintf(
      "Generating Documentation with Swagger\n```\ncd /var/www/development && chmod +x swagger.sh\n./swagger.sh\n```\n",
      $this->projectSlug
    );

    $content .= sprintf("### View the documentation\n");
    $content .= sprintf("[http://localhost/swagger](http://localhost/swagger)\n");

    $fileSystem = new Filesystem();
    $fileSystem->dumpFile($this->projectSlug.'/readme.md', $content);
  }

  protected function createGitIgnoreFile()
  {
    $this->output->writeln("Creating the file <info>.gitignore</info>...");

    $content = '.DS_Store
.idea
html/wp-content/upgrade

# ignore OS generated files
ehthumbs.db
Thumbs.db

# ignore Editor files
*.sublime-project
*.sublime-workspace
*.komodoproject

# ignore log files and databases
*.log
*.sqlite

# ignore packaged files
*.7z
*.dmg
*.gz
*.iso
*.jar
*.rar
*.tar
*.zip

# ignore these plugins
html/wp-content/plugins/hello.php

# ignore specific themes
#html/wp-content/themes/twenty*/

# ignore node/grunt dependency directories
node_modules/
';

    $fileSystem = new Filesystem();
    $fileSystem->dumpFile($this->projectSlug.'/.gitignore', $content);
  }

  protected function createGitAttributesFile()
  {
    $this->output->writeln("Creating the file <info>.gitattributes</info>...");

    $content = '* text=auto
*.css linguist-vendored
*.scss linguist-vendored
*.js linguist-vendored
CHANGELOG.md export-ignore
';

    $fileSystem = new Filesystem();
    $fileSystem->dumpFile($this->projectSlug.'/.gitattributes', $content);
  }

  protected function createEditorConfigFile()
  {
    $this->output->writeln("Creating the file <info>.editorconfig</info>...");

    $content = 'root = true

[*]
indent_style = space
indent_size = 4
end_of_line = lf
charset = utf-8
trim_trailing_whitespace = true
insert_final_newline = true
';

    $fileSystem = new Filesystem();
    $fileSystem->dumpFile($this->projectSlug.'/.editorconfig', $content);
  }

  protected function createDockerIgnoreFile()
  {
    $this->output->writeln("Creating the file <info>.dockerignore</info>...");

    $content = 'node_modules
npm-debug.log
';

    $fileSystem = new Filesystem();
    $fileSystem->dumpFile($this->projectSlug.'/.dockerignore', $content);
  }
}
