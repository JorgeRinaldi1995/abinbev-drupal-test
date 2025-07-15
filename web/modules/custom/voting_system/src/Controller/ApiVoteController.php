<?php

namespace Drupal\voting_system\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\voting_system\Service\VoteService;
use Drupal\voting_system\Service\TokenAuthService;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;
use Drupal\voting_system\Trait\JsonRequestTrait;

class ApiVoteController extends ControllerBase {
  use JsonRequestTrait;

  protected VoteService $voteService;
  protected TokenAuthService $tokenAuthService;

  public function __construct(VoteService $voteService, TokenAuthService $tokenAuthService) {
    $this->voteService = $voteService;
    $this->tokenAuthService = $tokenAuthService;
  }

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('voting_system.vote_service'),
      $container->get('voting_system.token_auth_service')
    );
  }

  public function submitVote(Request $request, int $question_id): JsonResponse {
    $data = $this->getJsonData($request);
    $answer_id = $data['answer_id'] ?? NULL;

    if (!$answer_id || !is_numeric($answer_id)) {
      return new JsonResponse(['error' => 'Missing or invalid answer_id.'], 400);
    }

    if (!$this->voteService->validateAnswerForQuestion((int) $answer_id, $question_id)) {
      return new JsonResponse(['error' => 'Answer does not belong to the selected question.'], 400);
    }

    $user = $this->tokenAuthService->getUserFromToken($request);
    if (!$user) {
      return new JsonResponse(['error' => 'Unauthorized'], 401);
    }

    try {
      $this->voteService->submitVote((int) $answer_id, $user->id());
      return new JsonResponse(['success' => TRUE, 'message' => 'Vote recorded.']);
    }
    catch (\Exception $e) {
      return new JsonResponse(['error' => $e->getMessage()], 403);
    }
  }

  public function getResults(int $question_id): JsonResponse {
    try {
      $results = $this->voteService->getResults($question_id);
      return new JsonResponse($results);
    }
    catch (\Exception $e) {
      return new JsonResponse(['error' => $e->getMessage()], 404);
    }
  }

  public static function access(Request $request, AccountInterface $account): AccessResult {
    return \Drupal::service('voting_system.token_auth_service')->checkAccess($request);
  }
}
