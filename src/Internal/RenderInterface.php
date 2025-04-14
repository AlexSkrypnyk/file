<?php

declare(strict_types=1);

namespace AlexSkrypnyk\File\Internal;

/**
 * Interface for rendering diff results.
 */
interface RenderInterface {

  /**
   * Renders a diff result.
   *
   * @param array $options
   *   Rendering options.
   * @param callable|null $renderer
   *   Optional custom renderer callback.
   *
   * @return string|null
   *   The rendered diff or NULL if there is nothing to render.
   */
  public function render(array $options = [], ?callable $renderer = NULL): ?string;

}
