<?php

declare(strict_types=1);

namespace AlexSkrypnyk\File\Internal;

interface RenderInterface {

  public function render(array $options = [], ?callable $renderer = NULL): ?string;

}
