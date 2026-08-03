<?php

declare(strict_types=1);

namespace Drupal\voting_system\Exception;

/**
 * Thrown when an answer ID doesn't resolve to an existing entity.
 */
class AnswerNotFoundException extends \RuntimeException {}
