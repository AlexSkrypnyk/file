<?php

declare(strict_types=1);

namespace AlexSkrypnyk\File\Tests\Unit;

use AlexSkrypnyk\File\File;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * Temporary probe of Windows symlink behaviour.
 */
#[CoversNothing]
final class WindowsSymlinkProbeTest extends TestCase {

  public function testProbe(): void {
    if (DIRECTORY_SEPARATOR !== '\\') {
      $this->markTestSkipped('Windows only.');
    }

    $out = [];
    $base = sys_get_temp_dir() . '\\probe_' . uniqid();
    $src = $base . '\\src';

    File::dump($src . '\\hooks\\library\\provision.sh', 'provision');
    File::dump($src . '\\hooks\\common\\post-code-update\\script.sh', 'update');
    File::dump($src . '\\hooks\\prod\\post-code-deploy\\script.sh', 'deploy');
    $this->link($src . '\\hooks\\common\\post-code-update\\1.provision.sh', '..\\..\\library\\provision.sh');
    $this->link($src . '\\hooks\\common\\post-code-deploy', 'post-code-update');
    $this->link($src . '\\hooks\\prod\\post-code-update', 'post-code-deploy');

    $out[] = 'where tar: ' . shell_exec('where tar');
    $out[] = 'tar version: ' . shell_exec('tar --version');
    $out[] = 'create: ' . shell_exec(sprintf('tar -cf %s -C %s hooks 2>&1', escapeshellarg($base . '\\a.tar'), escapeshellarg($src)));

    mkdir($base . '\\x_path');
    $out[] = 'extract PATH tar: ' . shell_exec(sprintf('tar -xf %s -C %s 2>&1', escapeshellarg($base . '\\a.tar'), escapeshellarg($base . '\\x_path')));
    mkdir($base . '\\x_sys');
    $out[] = 'extract System32 tar: ' . shell_exec(sprintf('C:\\Windows\\System32\\tar.exe -xf %s -C %s 2>&1', escapeshellarg($base . '\\a.tar'), escapeshellarg($base . '\\x_sys')));

    File::dump($base . '\\m\\dir\\f.txt', 'f');
    $out[] = 'mklink file to dir: ' . shell_exec(sprintf('cmd /c mklink %s dir 2>&1', escapeshellarg($base . '\\m\\filelink')));
    $out[] = 'mklink /J junction: ' . shell_exec(sprintf('cmd /c mklink /J %s %s 2>&1', escapeshellarg($base . '\\m\\junction'), escapeshellarg($base . '\\m\\dir')));

    foreach (['src', 'x_path', 'x_sys', 'm'] as $tree) {
      $dir = $base . '\\' . $tree;
      $out[] = '##### ' . $tree;
      $out[] = (string) shell_exec(sprintf('cmd /c dir /AL /S %s 2>&1', escapeshellarg($dir)));
      $out = array_merge($out, $this->describe($dir));
      $copy = $base . '\\' . $tree . '_copy';
      $out[] = 'File::copy returned ' . var_export(File::copy($dir, $copy), TRUE);
      $out[] = (string) shell_exec(sprintf('cmd /c dir /AL /S %s 2>&1', escapeshellarg($copy)));
      $out = array_merge($out, $this->describe($copy));
    }

    fwrite(STDERR, "\n=== PROBE ===\n" . implode("\n", $out) . "\n=== END PROBE ===\n");
    File::remove($base);

    $this->addToAssertionCount(1);
  }

  /**
   * Create a symlink from within its directory.
   *
   * @param string $link
   *   Link.
   * @param string $target
   *   Target.
   */
  protected function link(string $link, string $target): void {
    $cwd = (string) getcwd();
    chdir(dirname($link));
    symlink($target, basename($link));
    chdir($cwd);
  }

  /**
   * Describe every path in a tree without following links.
   *
   * @param string $dir
   *   Directory.
   *
   * @return array<int, string>
   *   Lines.
   */
  protected function describe(string $dir): array {
    $out = [];
    clearstatcache();
    $paths = [
      'hooks\\common\\post-code-update\\1.provision.sh',
      'hooks\\common\\post-code-deploy',
      'hooks\\prod\\post-code-update',
      'filelink',
      'junction',
    ];
    foreach ($paths as $name) {
      $path = $dir . '\\' . $name;
      $lstat = @lstat($path);
      if ($lstat === FALSE && !file_exists($path)) {
        $out[] = $name . ': lstat=false';
        continue;
      }
      $out[] = sprintf('%s: is_link=%s file_exists=%s is_dir=%s is_file=%s readlink=%s realpath=%s lstat_mode=%s', $name, var_export(is_link($path), TRUE), var_export(file_exists($path), TRUE), var_export(is_dir($path), TRUE), var_export(is_file($path), TRUE), var_export(@readlink($path), TRUE), var_export(realpath($path), TRUE), $lstat ? decoct($lstat['mode']) : 'false');
    }

    return $out;
  }

}
