<?php

namespace App\Config;

use Exception;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Serializer\Encoder\EncoderInterface;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Encoder\YamlEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

class ConfigExporter {

  /**
   * @var array[] List of supported encoders
   */
  private $supportedEncoders = [
    YamlEncoder::FORMAT => [
        'class' => YamlEncoder::class,
        'context' => [YamlEncoder::YAML_INLINE=>2, YamlEncoder::YAML_INDENT=>2],
      ],
    'yml' => [
      'class' => YamlEncoder::class,
      'context' => [YamlEncoder::YAML_INLINE=>2, YamlEncoder::YAML_INDENT=>2],
    ],
    JsonEncoder::FORMAT => [
      'class' => JsonEncoder::class,
      'context' => [],
    ],
    XmlEncoder::FORMAT => [
      'class' => XmlEncoder::class,
      'context' => [],
    ],
  ];

  /** @var array */
  private $encoders = [];

  /**
   * ConfigExporter constructor.
   *
   * @param string $formats
   * @throws Exception
   */
  public function __construct(string $formats = 'yaml') {
    $this->loadEncoders(
      $this->extractRequestedFormats($formats)
    );
  }

  /**
   * Extract supported formats from the command line parameters
   * @param string $formats requested formats, in comma separated values
   * @return array The list of supported formats requested
   * @throws Exception Thrown if requesting an unsupported format
   */
  private function extractRequestedFormats(string $formats): array {
    $formats = array_map(
      function(string $format) {
        $key = strtolower(trim($format));

        if (empty($this->supportedEncoders[$key])) {
          throw new Exception('Unsupported format.');
        }

        return $key;
      },
      explode(',', $formats)
    );

    return array_unique($formats);
  }

  /**
   * Load the encoders for all supported formats
   *
   * @param array $formats The list of requested formats
   */
  private function loadEncoders(array $formats): void {
    $this->unloadEncoders();

    foreach($formats as $format) {
      if (empty($this->encoders[$format])) {
        $this->encoders[$format] = [
          'encoder' => new $this->supportedEncoders[$format]['class'],
          'context' => $this->supportedEncoders[$format]['context'],
        ];
      }
    }
  }

  /**
   * Unload the loaded encoders
   */
  private function unloadEncoders(): void {
    foreach((array)$this->encoders as &$encoder) {
      if (!empty($encoder['encoder'])) {
        unset($encoder['encoder']);
      }
    }

    $this->encoders = [];
  }

  /**
   * Export the data to given formats using loaded encoders
   *
   * @param array $data The data to export
   * @param string $filePath The path of the file to store the export
   * @return bool TRUE if all export went well, FALSE otherwise
   */
  public function export(array $data, string $filePath): bool {
    $serializer = new Serializer(
      [new ObjectNormalizer()],
      array_map(
        function($encoder) {
          return $encoder['encoder'];
        },
        $this->encoders)
    );

    $result = TRUE;
    foreach($this->encoders as $encoder) {
      $content = $serializer->serialize($data, $encoder['encoder']::FORMAT, $encoder['context']);

      if (empty($content)) {
        continue;
      }

      $result = $this->dumpFile(
        $content,
        $filePath . '.' . $encoder['encoder']::FORMAT
      ) && $result;
    }

    return $result;
  }

  /**
   * Dump a content to a file
   * @param string $content The content to dump
   * @param string $filePath The path of the file to dump the content to
   * @return bool TRUE if dump went well, FALSE otherwise
   */
  private function dumpFile(string $content, string $filePath): bool {
    try {
      (new Filesystem)->dumpFile($filePath, $content);
    } catch (IOException $ioe) {
      return FALSE;
    }

    return TRUE;
  }

}
