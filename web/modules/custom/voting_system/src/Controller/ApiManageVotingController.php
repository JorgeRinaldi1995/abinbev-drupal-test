<?php

namespace Drupal\voting_system\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\voting_system\Entity\VotingQuestion;
use Drupal\voting_system\Entity\VotingAnswer;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;

class ApiManageVotingController extends ControllerBase {

  protected $currentUser;

  public function __construct(AccountProxyInterface $current_user) {
    $this->currentUser = $current_user;
  }

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('current_user')
    );
  }

  /**
   * List active voting questions.
   */
  public function listActiveQuestions(): JsonResponse {
    $questions = \Drupal::entityTypeManager()
      ->getStorage('voting_question')
      ->loadByProperties(['status' => 1]);

    $data = [];

    foreach ($questions as $question) {
      $data[] = [
        'id' => $question->id(),
        'title' => $question->get('title')->value,
        'question_id' => $question->get('question_id')->value,
        'show_percent' => (bool) $question->get('show_percent')->value,
        'created' => $question->get('created')->value,
      ];
    }

    return new JsonResponse($data);
  }

  /**
   * Admin creates a new voting question.
   */
  public function createQuestion(Request $request): JsonResponse {
    $data = json_decode($request->getContent(), TRUE);
    $title = $data['title'] ?? NULL;
    $question_id = $data['question_id'] ?? NULL;
    $show_percent = $data['show_percent'] ?? TRUE;

    if (!$title || !$question_id) {
      return new JsonResponse(['error' => 'Missing title or question_id.'], 400);
    }

    $question = VotingQuestion::create([
      'title' => $title,
      'question_id' => $question_id,
      'show_percent' => (bool) $show_percent,
      'status' => 1,
      'user_id' => $this->currentUser->id(),
    ]);
    $question->save();

    return new JsonResponse(['success' => TRUE, 'id' => $question->id()]);
  }

  /**
   * Admin creates a new answer for a question.
   */
  public function createAnswer(Request $request): JsonResponse {
    $data = json_decode($request->getContent(), TRUE);
    $title = $data['title'] ?? NULL;
    $question_id = $data['question_id'] ?? NULL;
    $description = $data['description'] ?? '';
    $image = NULL; // skipping image upload for now

    if (!$title || !$question_id) {
      return new JsonResponse(['error' => 'Missing title or question_id.'], 400);
    }

    $answer = VotingAnswer::create([
      'title' => $title,
      'description' => $description,
      'question_id' => $question_id,
      'vote_count' => 0,
    ]);
    $answer->save();

    return new JsonResponse(['success' => TRUE, 'id' => $answer->id()]);
  }

  /**
   * Access for any authenticated user.
   */
  public static function access(Request $request, AccountInterface $account): AccessResult {
    $auth_header = $request->headers->get('Authorization');
    if (!$auth_header || !str_starts_with($auth_header, 'Bearer ')) {
      return AccessResult::forbidden();
    }

    $token = substr($auth_header, 7);
    $token_service = \Drupal::service('voting_system.token_service');
    return $token_service->validateToken($token) ? AccessResult::allowed() : AccessResult::forbidden();
  }

  /**
   * Only allow admin users.
   */
  public static function adminAccess(Request $request, AccountInterface $account): AccessResult {
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

    $user = \Drupal\user\Entity\User::load($uid);
    if ($user && in_array('administrator', $user->getRoles())) {
      return AccessResult::allowed();
    }

    return AccessResult::forbidden();
  }
}
