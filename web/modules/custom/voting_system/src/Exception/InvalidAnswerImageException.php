<?php

declare(strict_types=1);

namespace Drupal\voting_system\Exception;

/**
 * Thrown when an answer image URL is invalid, unsafe, or fails to download.
 */
class InvalidAnswerImageException extends \RuntimeException {}
