<?php

declare(strict_types=1);

namespace AlexSkrypnyk\File\Tests\Unit;

use AlexSkrypnyk\File\File;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class WindowsSymlinkProbeTest extends TestCase {

  public function testProbe(): void {
    if (DIRECTORY_SEPARATOR !== '\\') {
      $this->markTestSkipped('Windows only.');
    }

    $out = [];
    $tmp = sys_get_temp_dir();
    $out[] = 'sys_get_temp_dir: ' . $tmp;
    $out[] = 'realpath(tmp): ' . var_export(realpath($tmp), TRUE);
    $out[] = 'readlink(tmp dir): ' . var_export(@readlink($tmp), TRUE);
    $out[] = 'File::realpath(tmp): ' . File::realpath($tmp);

    $base = $tmp . '\\probe_' . uniqid();
    $src = $base . '\\src';
    mkdir($src . '\\library', 0777, TRUE);
    mkdir($src . '\\hooks\\update', 0777, TRUE);
    file_put_contents($src . '\\library\\provision.sh', 'provision');
    file_put_contents($src . '\\hooks\\run.sh', 'run');
    file_put_contents($src . '\\hooks\\update\\script.sh', 'script');
    $out[] = 'readlink(src dir): ' . var_export(@readlink($src), TRUE);
    $out[] = 'readlink(regular file): ' . var_export(@readlink($src . '\\hooks\\run.sh'), TRUE);

    $links = [
      'l_back' => '..\\library\\provision.sh',
      'l_fwd' => '../library/provision.sh',
      'l_same' => 'run.sh',
      'l_abs' => $src . '\\library\\provision.sh',
      'd_rel' => 'update',
      'd_dot' => '.',
    ];

    foreach ($links as $name => $target) {
      $out[] = sprintf('symlink(%s -> %s): %s', $name, $target, var_export(@symlink($target, $src . '\\hooks\\' . $name), TRUE));
    }

    file_put_contents($src . '\\hooks\\update\\inner.txt', 'inner');
    $out[] = 'symlink(update/inner_link -> ..\\run.sh): ' . var_export(@symlink('..\\run.sh', $src . '\\hooks\\update\\inner_link'), TRUE);

    $out = array_merge($out, $this->describe($src . '\\hooks', array_keys($links)));
    $out[] = 'via d_rel/inner_link: is_link=' . var_export(is_link($src . '\\hooks\\d_rel\\inner_link'), TRUE) . ' file_exists=' . var_export(file_exists($src . '\\hooks\\d_rel\\inner_link'), TRUE);
    $out[] = 'via d_rel/script.sh: is_file=' . var_export(is_file($src . '\\hooks\\d_rel\\script.sh'), TRUE);
    $out[] = 'dir /AL: ' . shell_exec('cmd /c dir /AL ' . escapeshellarg($src . '\\hooks'));

    $dst = $base . '\\dst';
    File::copy($src, $dst);
    $out[] = '--- after File::copy';
    $out = array_merge($out, $this->describe($dst . '\\hooks', array_keys($links)));
    $out[] = 'dst dir /AL: ' . shell_exec('cmd /c dir /AL ' . escapeshellarg($dst . '\\hooks'));

    File::remove($src);
    $out[] = '--- after removing src';
    $out = array_merge($out, $this->describe($dst . '\\hooks', array_keys($links)));

    // Broken link behaviour.
    $broken = $base . '\\broken';
    mkdir($broken);
    file_put_contents($broken . '\\t.txt', 't');
    symlink($broken . '\\t.txt', $broken . '\\b_abs');
    symlink('t.txt', $broken . '\\b_rel');
    unlink($broken . '\\t.txt');
    $out = array_merge($out, $this->describe($broken, ['b_abs', 'b_rel']));

    fwrite(STDERR, "\n=== PROBE ===\n" . implode("\n", $out) . "\n=== END PROBE ===\n");
    File::remove($base);

    $this->addToAssertionCount(1);
  }

  /**
   * @param array<int, string> $names
   *
   * @return array<int, string>
   */
  protected function describe(string $dir, array $names): array {
    $out = [];
    clearstatcache();
    foreach ($names as $name) {
      $path = $dir . '\\' . $name;
      $lstat = @lstat($path);
      $out[] = sprintf('%s: is_link=%s file_exists=%s is_dir=%s is_file=%s readlink=%s realpath=%s lstat_mode=%s content=%s', $name, var_export(is_link($path), TRUE), var_export(file_exists($path), TRUE), var_export(is_dir($path), TRUE), var_export(is_file($path), TRUE), var_export(@readlink($path), TRUE), var_export(realpath($path), TRUE), $lstat ? decoct($lstat['mode']) : 'false', is_file($path) ? var_export(@file_get_contents($path), TRUE) : '-');
    }

    return $out;
  }

}
