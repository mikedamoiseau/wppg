<?php

namespace App\Config;

use Exception;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Encoder\YamlEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Yaml\Yaml;

class ConfigExporter {

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

  /** @var null EncoderInterface */
  private $encoder = NULL;

  /** @var array */
  private $encoderContext = [];

  /**
   * ConfigExporter constructor.
   *
   * @param string $format
   * @throws Exception
   */
  public function __construct(string $format = 'yaml') {
    $key = strtolower($format);

    if (empty($this->supportedEncoders[$key])) {
      throw new Exception();
    }
    $this->encoder = new $this->supportedEncoders[$key]['class'];
    $this->encoderContext = $this->supportedEncoders[$key]['context'];
  }

  public function export(array $data, string $filePath): bool {
    $encoders = [$this->encoder];
    $normalizers = [new ObjectNormalizer()];
    $serializer = new Serializer($normalizers, $encoders);
    $content = $serializer->serialize($data, $this->encoder::FORMAT, $this->encoderContext);

    if (empty($content)) {
      return FALSE;
    }

    return $this->writeFile(
      $content,
      $filePath . '.' . $this->encoder::FORMAT
    );
  }

  private function writeFile(string $content, string $filePath): bool {
    if (empty($content)) {
      return FALSE;
    }

    try {
      (new Filesystem)->dumpFile($filePath, $content);
    } catch (IOException $ioe) {
      return FALSE;
    }

    return TRUE;
  }

}
