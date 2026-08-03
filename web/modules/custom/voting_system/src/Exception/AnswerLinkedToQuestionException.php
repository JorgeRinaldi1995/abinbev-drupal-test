<?php

declare(strict_types=1);

namespace Drupal\voting_system\Exception;

/**
 * Thrown when editing an answer that is already linked to a question.
 */
class AnswerLinkedToQuestionException extends \RuntimeException {}
