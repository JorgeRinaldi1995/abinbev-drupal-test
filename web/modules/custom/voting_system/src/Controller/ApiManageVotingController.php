<?php

namespace Drupal\voting_system\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;
use Drupal\voting_system\Service\VotingManagerService;
use Drupal\voting_system\Trait\JsonRequestTrait;

class ApiManageVotingController extends ControllerBase {
  use JsonRequestTrait;

  protected $currentUser;
  protected VotingManagerService $votingManager;

  public function __construct(AccountProxyInterface $currentUser, VotingManagerService $votingManager) {
    $this->currentUser = $currentUser;
    $this->votingManager = $votingManager;
  }

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('current_user'),
      $container->get('voting_system.voting_manager')
    );
  }

  public function listActiveQuestions(): JsonResponse {
    $questions = $this->votingManager->loadActiveQuestions();
    $data = array_map(fn($question) => [
      'id' => $question->id(),
      'title' => $question->get('title')->value,
      'question_id' => $question->get('question_id')->value,
      'show_percent' => (bool) $question->get('show_percent')->value,
      'created' => $question->get('created')->value,
    ], $questions);

    return new JsonResponse($data);
  }

  public function createQuestion(Request $request): JsonResponse {
    $data = $this->getJsonData($request);
    $title = $data['title'] ?? NULL;
    $question_id = $data['question_id'] ?? NULL;
    $show_percent = $data['show_percent'] ?? TRUE;

    if (!$title || !$question_id) {
      return new JsonResponse(['error' => 'Missing title or question_id.'], 400);
    }

    $question = $this->votingManager->createQuestion($title, $question_id, $show_percent, $this->currentUser->id());
    return new JsonResponse(['success' => TRUE, 'id' => $question->id()]);
  }

  public function createAnswer(Request $request): JsonResponse {
    $data = $this->getJsonData($request);
    $title = $data['title'] ?? NULL;
    $question_id = $data['question_id'] ?? NULL;
    $description = $data['description'] ?? '';

    if (!$title || !$question_id) {
      return new JsonResponse(['error' => 'Missing title or question_id.'], 400);
    }

    $answer = $this->votingManager->createAnswer($title, $description, $question_id);
    return new JsonResponse(['success' => TRUE, 'id' => $answer->id()]);
  }

  public static function access(Request $request, AccountInterface $account): AccessResult {
    return \Drupal::service('voting_system.token_auth_service')->checkAccess($request);
  }

  public static function adminAccess(Request $request, AccountInterface $account): AccessResult {
    return \Drupal::service('voting_system.token_auth_service')->checkAdminAccess($request);
  }
}
