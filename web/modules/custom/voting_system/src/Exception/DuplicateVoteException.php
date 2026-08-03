<?php

declare(strict_types=1);

namespace Drupal\voting_system\Exception;

/**
 * Thrown when a user has already voted on a question.
 */
class DuplicateVoteException extends \RuntimeException {}
