<?php

declare(strict_types=1);

namespace Drupal\voting_system\Controller;

use Drupal\voting_system\Exception\AnswerLinkedToQuestionException;
use Drupal\voting_system\Exception\AnswerNotFoundException;
use Drupal\voting_system\Exception\DuplicateQuestionIdentifierException;
use Drupal\voting_system\Exception\InvalidAnswerImageException;
use Drupal\voting_system\Exception\QuestionNotFoundException;
use Drupal\voting_system\Service\VotingManagerService;
use Drupal\voting_system\Service\VotingQueryService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * API endpoints for administering voting questions and answers.
 */
class ApiManageVotingController extends ApiControllerBase {

  public function __construct(
    protected readonly VotingManagerService $votingManager,
    protected readonly VotingQueryService $votingQuery,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('voting_system.voting_manager'),
      $container->get('voting_system.voting_query'),
    );
  }

  /**
   * GET /api/voting/questions — active questions with their answers.
   */
  public function listActiveQuestions(): JsonResponse {
    return new JsonResponse($this->votingQuery->getActiveQuestionsData());
  }

  /**
   * GET /api/voting/answers — all answers, admin-only.
   */
  public function listAnswers(): JsonResponse {
    return new JsonResponse($this->votingQuery->getAnswersData());
  }

  /**
   * GET /api/voting/question/{question_id} — one active question.
   */
  public function getQuestion(string $question_id): JsonResponse {
    try {
      return new JsonResponse($this->votingQuery->getQuestionData($question_id));
    }
    catch (QuestionNotFoundException $exception) {
      return $this->errorResponse($exception);
    }
  }

  /**
   * POST /api/voting/question — creates a question, admin-only.
   */
  public function createQuestion(Request $request): JsonResponse {
    $data = $this->getJsonData($request);
    $title = $data['title'] ?? NULL;
    $question_id = $data['question_id'] ?? NULL;
    $show_percent = $data['show_percent'] ?? TRUE;

    if (!$title || !$question_id) {
      return $this->jsonError('Missing title or question_id.', 400);
    }

    try {
      $question = $this->votingManager->createQuestion($title, $question_id, (bool) $show_percent, (int) $this->currentUser()->id());
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

  /**
   * PATCH /api/voting/question/{question_id} — partial update, admin-only.
   */
  public function updateQuestion(Request $request, string $question_id): JsonResponse {
    $data = $this->getJsonData($request);

    if ($data === []) {
      return $this->jsonError('Provide at least one field to update (title, question_id, show_percent, status).', 400);
    }

    try {
      $question = $this->votingManager->updateQuestion(
        $question_id,
        title: array_key_exists('title', $data) ? (string) $data['title'] : NULL,
        new_question_id: array_key_exists('question_id', $data) ? (string) $data['question_id'] : NULL,
        show_percent: array_key_exists('show_percent', $data) ? (bool) $data['show_percent'] : NULL,
        status: array_key_exists('status', $data) ? (bool) $data['status'] : NULL,
      );
    }
    catch (QuestionNotFoundException|DuplicateQuestionIdentifierException|\InvalidArgumentException $exception) {
      return $this->errorResponse($exception);
    }

    return new JsonResponse([
      'success' => TRUE,
      'message' => 'Question updated successfully',
      'id' => $question->id(),
    ]);
  }

  /**
   * POST /api/voting/answer — creates an answer, admin-only.
   */
  public function createAnswer(Request $request): JsonResponse {
    $data = $this->getJsonData($request);
    $title = $data['title'] ?? NULL;
    $question_id = $data['question_id'] ?? NULL;
    $description = $data['description'] ?? '';
    $image_url = $data['img_url'] ?? NULL;

    if (!$title || !$question_id) {
      return $this->jsonError('Missing title or question_id.', 400);
    }

    try {
      $answer = $this->votingManager->createAnswer($title, $description, $question_id, $image_url);
    }
    catch (QuestionNotFoundException|InvalidAnswerImageException $exception) {
      return $this->errorResponse($exception);
    }

    return new JsonResponse([
      'success' => TRUE,
      'message' => 'Answer created successfully',
      'id' => $answer->id(),
    ]);
  }

  /**
   * PATCH /api/voting/answer/{answer_id} — partial update, admin-only.
   */
  public function updateAnswer(Request $request, string $answer_id): JsonResponse {
    $data = $this->getJsonData($request);

    if ($data === []) {
      return $this->jsonError('Provide at least one field to update (title, description, img_url).', 400);
    }

    try {
      $answer = $this->votingManager->updateAnswer(
        (int) $answer_id,
        title: array_key_exists('title', $data) ? (string) $data['title'] : NULL,
        description: array_key_exists('description', $data) ? (string) $data['description'] : NULL,
        image_url: array_key_exists('img_url', $data) ? (string) $data['img_url'] : NULL,
      );
    }
    catch (AnswerNotFoundException|AnswerLinkedToQuestionException|InvalidAnswerImageException $exception) {
      return $this->errorResponse($exception);
    }

    return new JsonResponse([
      'success' => TRUE,
      'message' => 'Answer updated successfully',
      'id' => $answer->id(),
      'img_url' => $this->votingQuery->getAnswerImageUrl($answer),
    ]);
  }

}
