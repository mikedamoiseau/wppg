<?php

namespace App\Module;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Twig\Environment;

abstract class AbstractModule {

  protected $helper;

  /** @var InputInterface $input */
  protected $input;

  /** @var OutputInterface $output */
  protected $output;

  /** @var array */
  protected $options = [];

  /** @var \Twig\Environment */
  protected $twig;

  public function __construct(
    InputInterface $input,
    OutputInterface $output,
    $helper,
    Environment $twig
  ) {
    $this->input = $input;
    $this->output = $output;
    $this->helper = $helper;
    $this->twig = $twig;
  }

  /**
   * Get the name of the module (mostly for summary section)
   *
   * @return string The name of the module
   */
  abstract public function getName(): string;

  /**
   * Get the key of the module
   *
   * @return string The key of the module
   */
  public function getKey(): string {
    return str_replace(
      'app\\module\\',
      '',
      strtolower(static::class)
    );
  }

  /**
   * Run the module (means ask questions)
   *
   * @return array The options selected by user
   */
  public function run(): array {
    return [];
  }

  /**
   * Return an array of string for the summary
   *
   * @return array The list of entries
   */
  public function summarize(): array {
    return [];
  }

  /**
   * Execute the plugin (mostly create files, folders, ...)
   *
   * @param array $options The list of options returned by all modules
   */
  public function execute(array $options): void {

  }

  /**
   * Export the options of the module
   */
  public function export(): array {
    return $this->options;
  }

}
