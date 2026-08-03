<?php

declare(strict_types=1);

namespace Drupal\voting_system\Exception;

/**
 * Thrown when a question ID or question_id doesn't resolve to a question.
 */
class QuestionNotFoundException extends \RuntimeException {}
