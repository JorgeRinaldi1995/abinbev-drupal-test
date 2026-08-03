<?php

declare(strict_types=1);

namespace Drupal\voting_system\Exception;

/**
 * Thrown when results are requested by a user who hasn't voted yet.
 */
class VoteRequiredException extends \RuntimeException {}
