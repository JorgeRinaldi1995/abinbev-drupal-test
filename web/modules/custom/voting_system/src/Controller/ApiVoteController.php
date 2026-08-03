<?php

declare(strict_types=1);

namespace Drupal\voting_system\Controller;

use Drupal\voting_system\Exception\AnswerNotFoundException;
use Drupal\voting_system\Exception\AnswerQuestionMismatchException;
use Drupal\voting_system\Exception\DuplicateVoteException;
use Drupal\voting_system\Exception\QuestionNotActiveException;
use Drupal\voting_system\Exception\QuestionNotFoundException;
use Drupal\voting_system\Exception\VoteRequiredException;
use Drupal\voting_system\Service\VoteResultsService;
use Drupal\voting_system\Service\VoteService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Public API endpoints for casting a vote and reading its results.
 *
 * The acting user comes from ControllerBase::currentUser(), which
 * TokenAuthenticationProvider resolves from the bearer token for the
 * whole request — the controller no longer re-parses the token itself.
 */
class ApiVoteController extends ApiControllerBase {

  public function __construct(
    protected readonly VoteService $voteService,
    protected readonly VoteResultsService $voteResultsService,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('voting_system.vote_service'),
      $container->get('voting_system.vote_results_service')
    );
  }

  public function submitVote(Request $request, string $question_id): JsonResponse {
    if (!$this->currentUser()->isAuthenticated()) {
      return $this->jsonError('Unauthorized', 401);
    }

    $data = $this->getJsonData($request);
    $answer_id = $data['answer_id'] ?? NULL;

    if (!$answer_id || !is_numeric($answer_id)) {
      return $this->jsonError('Missing or invalid answer_id.', 400);
    }

    try {
      $this->voteService->submitVote((int) $answer_id, $question_id, (int) $this->currentUser()->id());
    }
    catch (QuestionNotFoundException|QuestionNotActiveException|AnswerNotFoundException|AnswerQuestionMismatchException|DuplicateVoteException $exception) {
      return $this->errorResponse($exception);
    }

    return new JsonResponse(['success' => TRUE, 'message' => 'Vote recorded.']);
  }

  public function getResults(string $question_id): JsonResponse {
    if (!$this->currentUser()->isAuthenticated()) {
      return $this->jsonError('Unauthorized', 401);
    }

    try {
      return new JsonResponse($this->voteResultsService->getResultsForVoter($question_id, (int) $this->currentUser()->id()));
    }
    catch (QuestionNotFoundException|VoteRequiredException $exception) {
      return $this->errorResponse($exception);
    }
  }

}
