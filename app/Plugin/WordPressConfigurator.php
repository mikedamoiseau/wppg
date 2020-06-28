<?php

namespace App\Plugin;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

class WordPressConfigurator extends AbstractPlugin {
  const SLUG = 'wordpress_configurator';

  /**
   * {@inheritDoc}
   */
  public function getName(): string {
    return 'WordPress Configuration';
  }

  /**
   * {@inheritDoc}
   */
  public function run(): array {
    $this->output->writeln("Some questions about WordPress...");

    $question = new Question(
      'What is the name of the admin account? [adminwp]',
      'adminwp'
    );

    $this->options['wp_user_name'] = $this->helper->ask(
      $this->input,
      $this->output,
      $question
    );

    $question = new Question(
      'What is the email address of the admin account? [adminwp@example.com]',
      'adminwp@example.com'
    );
    $question->setValidator(
      function ($answer) {
        if (!filter_var($answer, FILTER_VALIDATE_EMAIL)) {
          throw new \RuntimeException('The email address seems invalid.');
        }

        return $answer;
      }
    );

    $this->options['wp_user_email'] = $this->helper->ask(
      $this->input,
      $this->output,
      $question
    );

    $question = new Question('What is the admin password?');
    $question->setValidator(
      function ($answer) {
        // GDPR compliance...

        // at least 12 characters
        $is_complex_enough = (strlen($answer) >= 12);
        // at least 1 lowercase letter
        $is_complex_enough = $is_complex_enough && (0 !== preg_match(
              '/[a-z]/',
              $answer
            ));
        // at least 1 uppercase letter
        $is_complex_enough = $is_complex_enough && (0 !== preg_match(
              '/[A-Z]/',
              $answer
            ));
        // at least 1 number
        $is_complex_enough = $is_complex_enough && (0 !== preg_match(
              '/[0-9]/',
              $answer
            ));
        // at least 1 special character
        $is_complex_enough = $is_complex_enough && (0 !== preg_match(
              '/[!@#$%^&*()\-_=+{};:,<.>\[\]]/',
              $answer
            ));

        if (!$is_complex_enough) {
          throw new \RuntimeException(
            'The password must contain at least 12 characters: 1 lowercase letter [a-z], 1 uppercase letter [A-Z], 1 number [0-9] and 1 special symbol [!@#$%^&*()\-_=+{};:,<.>[]].'
          );
        }

        return $answer;
      }
    );

    $this->options['wp_user_password'] = $this->helper->ask(
      $this->input,
      $this->output,
      $question
    );

    $question = new Question(
      'The prefix of your database tables? [wppg_] ', 'wppg_'
    );

    $this->options['wp_db_prefix'] = $this->helper->ask(
      $this->input,
      $this->output,
      $question
    );

    return [self::SLUG => $this->options];
  }

  /**
   * {@inheritDoc}
   */
  public function summarize(): array {
    return [
      ['Admin name' => $this->options['wp_user_name']],
      ['Admin email' => $this->options['wp_user_email']],
      ['Admin password' => $this->options['wp_user_password']],
      ['Table prefix (database)' => $this->options['wp_db_prefix']],
    ];
  }

  /**
   * {@inheritDoc}
   */
  public function execute(array $options): void {

  }

}
