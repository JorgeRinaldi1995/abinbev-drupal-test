<?php

namespace Drupal\voting_system\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\voting_system\Service\VoteService;
use Drupal\voting_system\Entity\VotingQuestion;

class ApiController extends ControllerBase {

  protected VoteService $voteService;

  public function __construct(VoteService $voteService) {
    $this->voteService = $voteService;
  }

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('voting_system.vote_service')
    );
  }

  /**
   * GET /api/vote/question/{id}
   */
  public function getQuestion($id): JsonResponse {
    $question = VotingQuestion::load($id);
    if (!$question) {
      return new JsonResponse(['error' => 'Question not found.'], 404);
    }

    $answers = \Drupal::entityTypeManager()
      ->getStorage('voting_answer')
      ->loadByProperties(['question_id' => $id]);

    $data = [
      'id' => $question->id(),
      'title' => $question->get('title')->value,
      'answers' => array_map(fn($a) => [
        'id' => $a->id(),
        'title' => $a->get('title')->value,
        'description' => $a->get('description')->value,
        'image_url' => file_create_url($a->get('image')->entity?->getFileUri() ?? ''),
      ], $answers),
    ];

    return new JsonResponse($data);
  }

  /**
   * POST /api/vote/question/{id}
   */
  public function postVote(Request $request, $id): JsonResponse {
    $data = json_decode($request->getContent(), TRUE);
    $answer_id = $data['answer_id'] ?? null;

    if (!$answer_id) {
      return new JsonResponse(['error' => 'Missing answer_id.'], 400);
    }

    try {
      $this->voteService->submitVote($answer_id, $this->currentUser());
    } catch (\Throwable $e) {
      return new JsonResponse(['error' => $e->getMessage()], 400);
    }

    return new JsonResponse(['success' => TRUE]);
  }

  /**
   * GET /api/vote/results/{id}
   */
  public function getResults($id): JsonResponse {
    $question = VotingQuestion::load($id);
    if (!$question || !$question->get('status')->value) {
      return new JsonResponse(['error' => 'Invalid question or disabled.'], 404);
    }

    if (!$question->get('show_results')->value) {
      return new JsonResponse(['error' => 'Results hidden by admin.'], 403);
    }

    $results = $this->voteService->getResults($id);
    return new JsonResponse($results);
  }
}
