<?php

declare(strict_types=1);

namespace Drupal\voting_system\Exception;

/**
 * Thrown when a question_id is already used by another question.
 */
class DuplicateQuestionIdentifierException extends \RuntimeException {}
