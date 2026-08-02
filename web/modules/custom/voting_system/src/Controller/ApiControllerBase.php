<?php

declare(strict_types=1);

namespace Drupal\voting_system\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\voting_system\Exception\AnswerLinkedToQuestionException;
use Drupal\voting_system\Exception\AnswerNotFoundException;
use Drupal\voting_system\Exception\AnswerQuestionMismatchException;
use Drupal\voting_system\Exception\DuplicateQuestionIdentifierException;
use Drupal\voting_system\Exception\DuplicateVoteException;
use Drupal\voting_system\Exception\InvalidAnswerImageException;
use Drupal\voting_system\Exception\QuestionNotFoundException;
use Drupal\voting_system\Exception\VoteRequiredException;
use Drupal\voting_system\Trait\JsonRequestTrait;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Base class for the module's JSON API controllers.
 *
 * Controllers should stay thin: parse the request, delegate to a service,
 * and translate the outcome (or a domain exception) into an HTTP response.
 */
abstract class ApiControllerBase extends ControllerBase {

  use JsonRequestTrait;

  protected function jsonError(string $message, int $status): JsonResponse {
    return new JsonResponse(['success' => FALSE, 'error' => $message], $status);
  }

  /**
   * Maps a domain exception to a JSON error response with the right status.
   */
  protected function errorResponse(\Throwable $exception): JsonResponse {
    $status = match (TRUE) {
      $exception instanceof QuestionNotFoundException,
      $exception instanceof AnswerNotFoundException => 404,
      $exception instanceof DuplicateVoteException,
      $exception instanceof DuplicateQuestionIdentifierException,
      $exception instanceof AnswerLinkedToQuestionException => 409,
      $exception instanceof AnswerQuestionMismatchException,
      $exception instanceof InvalidAnswerImageException,
      $exception instanceof \InvalidArgumentException => 400,
      $exception instanceof VoteRequiredException => 403,
      default => 500,
    };

    return $this->jsonError($exception->getMessage(), $status);
  }

}
