<?php

declare(strict_types=1);

namespace AlexSkrypnyk\File\Internal;

class ExtendedSplFileInfo extends \SplFileInfo {

  public const string CONTENT_IGNORED_MARKER = 'content_ignored';

  protected string $basepath;

  protected ?string $hash;

  protected string $content;

  public function __construct(string $filename, string $base, ?string $content = NULL) {
    parent::__construct($filename);

    $this->setBasepath($base);
    $this->setContent($content);
  }

  public function setBasepath(string $basepath): void {
    $this->basepath = rtrim($basepath, DIRECTORY_SEPARATOR);
  }

  public function getBasepath(): string {
    return $this->basepath;
  }

  public function getHash(): ?string {
    return $this->hash;
  }

  public function getContent(): string {
    return $this->content;
  }

  public function getPathFromBasepath():string {
    return static::stripBasepath($this->getBasepath(), $this->getPath());
  }

  public function getPathnameFromBasepath():string {
    return static::stripBasepath($this->getBasepath(), $this->getPathname());
  }

  public function isIgnoreContent(): bool {
    return $this->content === static::CONTENT_IGNORED_MARKER;
  }

  public function setIgnoreContent(bool $ignore = TRUE): void {
    $this->setContent($ignore ? static::CONTENT_IGNORED_MARKER : NULL);
  }

  public function setContent(?string $content): void {
    if ($this->isLink()) {
      $this->content = static::stripBasepath($this->getBasepath(), $this->getRealPath());
    }
    elseif (!is_null($content)) {
      $this->content = $content;
    }
    else {
      $this->content = (string) file_get_contents($this->getRealPath());
    }

    $this->hash = $this->hash($this->content);
  }

  protected function hash(string $content): string {
    return md5(trim($content));
  }

  protected static function stripBasepath(string $basepath, string $path): string {
    if (!str_starts_with($path, $basepath)) {
      throw new \Exception(sprintf('Path %s does not start with basepath %s', $path, $basepath));
    }

    return ltrim(str_replace($basepath, '', $path), DIRECTORY_SEPARATOR);
  }

}
