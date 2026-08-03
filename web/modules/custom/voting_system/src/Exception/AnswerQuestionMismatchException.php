<?php

declare(strict_types=1);

namespace Drupal\voting_system\Exception;

/**
 * Thrown when a vote's answer isn't linked to the given question.
 */
class AnswerQuestionMismatchException extends \RuntimeException {}
