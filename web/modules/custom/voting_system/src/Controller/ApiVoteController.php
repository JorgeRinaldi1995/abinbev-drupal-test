<?php

namespace Drupal\voting_system\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\voting_system\Service\VoteService;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;

class ApiVoteController extends ControllerBase {

  protected VoteService $voteService;
  protected $currentUser;

  public function __construct(VoteService $voteService, AccountProxyInterface $currentUser) {
    $this->voteService = $voteService;
    $this->currentUser = $currentUser;
  }

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('voting_system.vote_service'),
      $container->get('current_user')
    );
  }

  public function submitVote(Request $request, int $question_id): JsonResponse {
    $data = json_decode($request->getContent(), TRUE);
    $answer_id = $data['answer_id'] ?? NULL;

    if (!$answer_id || !is_numeric($answer_id)) {
      return new JsonResponse(['error' => 'Missing or invalid answer_id.'], 400);
    }

    // Validate if answer belongs to this question.
    $answer = \Drupal::entityTypeManager()
      ->getStorage('voting_answer')
      ->load($answer_id);

    if (!$answer || (int) $answer->get('question_id')->target_id !== $question_id) {
      return new JsonResponse(['error' => 'Answer does not belong to the selected question.'], 400);
    }

    try {
      $this->voteService->submitVote((int) $answer_id, $this->currentUser);
      return new JsonResponse(['success' => TRUE, 'message' => 'Vote recorded.']);
    } catch (\Exception $e) {
      return new JsonResponse(['error' => $e->getMessage()], 403);
    }
  }

  public function getResults(int $question_id): JsonResponse {
    try {
      $results = $this->voteService->getResults($question_id);
      return new JsonResponse($results);
    } catch (\Exception $e) {
      return new JsonResponse(['error' => $e->getMessage()], 404);
    }
  }

  public static function access(Request $request, AccountInterface $account): AccessResult {
    $auth_header = $request->headers->get('Authorization');

    if (!$auth_header || !str_starts_with($auth_header, 'Bearer ')) {
      return AccessResult::forbidden();
    }

    $token = substr($auth_header, 7);
    $token_service = \Drupal::service('voting_system.token_service');
    $uid = $token_service->validateToken($token);

    if (!$uid) {
      return AccessResult::forbidden();
    }

    return AccessResult::allowed();
  }
}
