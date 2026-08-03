<?php

declare(strict_types=1);

namespace Drupal\voting_system\Exception;

/**
 * Thrown when voting is attempted on a disabled question.
 */
class QuestionNotActiveException extends \RuntimeException {}
