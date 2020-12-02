<?php

namespace App\Module;

use Symfony\Component\Filesystem\Filesystem;

Class Git extends AbstractModule {

  const SLUG = 'git';

  /**
   * {@inheritDoc}
   */
  public function getName(): string {
    return 'Git';
  }

  /**
   * {@inheritDoc}
   */
  public function run(): array {
    return [self::SLUG => $this->options];
  }

  /**
   * {@inheritDoc}
   */
  public function summarize(): array {
    return [
      ['Git ignore' => './.gitignore'],
      ['Git attributes' => './.gitattributes'],
    ];
  }

  /**
   * {@inheritDoc}
   */
  public function execute(array $options): void {
    $filesystem = new Filesystem();

    $this->createGitIgnoreFile($filesystem, $options);
    $this->createGitAttributesFile($filesystem, $options);
  }

  protected function createGitIgnoreFile(
    Filesystem $filesystem,
    array $options
  ) {
    $content = $this->twig->render(
      "git/gitignore.twig"
    );

    $filesystem->dumpFile(
      $options[ProjectInfo::SLUG]['project_slug'] . '/.gitignore',
      $content
    );
  }

  protected function createGitAttributesFile(
    Filesystem $filesystem,
    array $options
  ) {
    $content = $this->twig->render(
      "git/gitattributes.twig"
    );

    $filesystem->dumpFile(
      $options[ProjectInfo::SLUG]['project_slug'] . '/.gitattributes',
      $content
    );
  }

}
