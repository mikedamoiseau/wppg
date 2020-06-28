<?php

namespace App\Plugin;

use Symfony\Component\Filesystem\Filesystem;

Class EditorConfig extends AbstractPlugin {

  const SLUG = 'editor_config';

  /**
   * {@inheritDoc}
   */
  public function getName(): string {
    return 'Editor Config';
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
      ['Editor Config' => './.editorconfig'],
    ];
  }

  /**
   * {@inheritDoc}
   */
  public function execute(array $options): void {
    $filesystem = new Filesystem();

    $this->createEditorConfigFile($filesystem, $options);
  }

  protected function createEditorConfigFile(
    Filesystem $filesystem,
    array $options
  ) {
    $content = $this->twig->render(
      "editorconfig/editorconfig.twig"
    );

    $filesystem->dumpFile(
      $options[ProjectInfo::SLUG]['project_slug'] . '/.editorconfig',
      $content
    );
  }

}
