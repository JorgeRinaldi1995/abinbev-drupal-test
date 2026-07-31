<?php

declare(strict_types=1);

namespace Drupal\voting_system\Controller;

use Drupal\Core\Session\AccountProxyInterface;
use Drupal\voting_system\Exception\DuplicateQuestionIdentifierException;
use Drupal\voting_system\Exception\QuestionNotFoundException;
use Drupal\voting_system\Service\VotingManagerService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * API endpoints for administering voting questions and answers.
 */
class ApiManageVotingController extends ApiControllerBase {

  public function __construct(
    protected readonly AccountProxyInterface $currentUser,
    protected readonly VotingManagerService $votingManager,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('current_user'),
      $container->get('voting_system.voting_manager')
    );
  }

  public function listActiveQuestions(): JsonResponse {
    return new JsonResponse($this->votingManager->getActiveQuestionsData());
  }

  public function createQuestion(Request $request): JsonResponse {
    $data = $this->getJsonData($request);
    $title = $data['title'] ?? NULL;
    $question_id = $data['question_id'] ?? NULL;
    $show_percent = $data['show_percent'] ?? TRUE;

    if (!$title || !$question_id) {
      return $this->jsonError('Missing title or question_id.', 400);
    }

    try {
      $question = $this->votingManager->createQuestion($title, $question_id, (bool) $show_percent, (int) $this->currentUser->id());
    }
    catch (DuplicateQuestionIdentifierException|\InvalidArgumentException $exception) {
      return $this->errorResponse($exception);
    }

    return new JsonResponse([
      'success' => TRUE,
      'message' => 'Question created successfully',
      'id' => $question->id(),
    ]);
  }

  public function createAnswer(Request $request): JsonResponse {
    $data = $this->getJsonData($request);
    $title = $data['title'] ?? NULL;
    $question_id = $data['question_id'] ?? NULL;
    $description = $data['description'] ?? '';

    if (!$title || !$question_id) {
      return $this->jsonError('Missing title or question_id.', 400);
    }

    try {
      $answer = $this->votingManager->createAnswer($title, $description, $question_id);
    }
    catch (QuestionNotFoundException $exception) {
      return $this->errorResponse($exception);
    }

    return new JsonResponse([
      'success' => TRUE,
      'message' => 'Answer created successfully',
      'id' => $answer->id(),
    ]);
  }

}
