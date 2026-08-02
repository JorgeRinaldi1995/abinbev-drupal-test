<?php

declare(strict_types=1);

namespace Drupal\voting_system\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\file\FileInterface;
use Drupal\voting_system\Entity\VotingAnswer;
use Drupal\voting_system\Entity\VotingQuestion;
use Drupal\voting_system\Exception\DuplicateQuestionIdentifierException;
use Drupal\voting_system\Exception\QuestionNotFoundException;

class VotingManagerService {

  public function __construct(
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly QuestionResolverService $questionResolver,
    protected readonly AnswerImageDownloaderService $answerImageDownloader,
    protected readonly FileUrlGeneratorInterface $fileUrlGenerator,
  ) {}

  public function loadActiveQuestions(): array {
    return $this->entityTypeManager
      ->getStorage('voting_question')
      ->loadByProperties(['status' => 1]);
  }

  /**
   * Active questions as plain arrays, ready to be JSON-encoded.
   *
   * Keeps knowledge of the entities' field names out of the controller.
   */
  public function getActiveQuestionsData(): array {
    return array_map(
      fn (VotingQuestion $question) => $this->buildQuestionData($question),
      $this->loadActiveQuestions()
    );
  }

  /**
   * A single active question as a plain array, by numeric ID or question_id.
   *
   * @throws \Drupal\voting_system\Exception\QuestionNotFoundException
   */
  public function getQuestionData(int|string $question_identifier): array {
    $question = $this->questionResolver->resolve($question_identifier);

    if (!$question->get('status')->value) {
      throw new QuestionNotFoundException(sprintf('Question "%s" not found.', $question_identifier));
    }

    return $this->buildQuestionData($question);
  }

  /**
   * @throws \InvalidArgumentException
   * @throws \Drupal\voting_system\Exception\DuplicateQuestionIdentifierException
   */
  public function createQuestion(string $title, string $question_id, bool $show_percent, int $user_id): VotingQuestion {
    $normalized_question_id = trim($question_id);
    if ($normalized_question_id === '') {
      throw new \InvalidArgumentException('The question identifier cannot be empty.');
    }

    if ($this->questionResolver->findByIdentifier($normalized_question_id)) {
      throw new DuplicateQuestionIdentifierException('The provided question identifier already exists.');
    }

    $question = VotingQuestion::create([
      'title' => $title,
      'question_id' => $normalized_question_id,
      'show_percent' => $show_percent,
      'status' => 1,
      'user_id' => $user_id,
    ]);
    $question->save();
    return $question;
  }

  /**
   * Partially updates a question. Only non-null arguments are changed.
   *
   * @throws \Drupal\voting_system\Exception\QuestionNotFoundException
   * @throws \InvalidArgumentException
   * @throws \Drupal\voting_system\Exception\DuplicateQuestionIdentifierException
   */
  public function updateQuestion(
    int|string $question_identifier,
    ?string $title = NULL,
    ?string $new_question_id = NULL,
    ?bool $show_percent = NULL,
    ?bool $status = NULL,
  ): VotingQuestion {
    $question = $this->questionResolver->resolve($question_identifier);

    if ($title !== NULL) {
      $question->set('title', $title);
    }

    if ($new_question_id !== NULL) {
      $normalized_question_id = trim($new_question_id);
      if ($normalized_question_id === '') {
        throw new \InvalidArgumentException('The question identifier cannot be empty.');
      }

      $existing = $this->questionResolver->findByIdentifier($normalized_question_id);
      if ($existing && (int) $existing->id() !== (int) $question->id()) {
        throw new DuplicateQuestionIdentifierException('The provided question identifier already exists.');
      }

      $question->set('question_id', $normalized_question_id);
    }

    if ($show_percent !== NULL) {
      $question->set('show_percent', $show_percent);
    }

    if ($status !== NULL) {
      $question->set('status', $status);
    }

    $question->save();
    return $question;
  }

  /**
   * @throws \Drupal\voting_system\Exception\QuestionNotFoundException
   * @throws \Drupal\voting_system\Exception\InvalidAnswerImageException
   */
  public function createAnswer(string $title, string $description, int|string $question_identifier, ?string $image_url = NULL): VotingAnswer {
    $question = $this->questionResolver->resolve($question_identifier);

    $values = [
      'title' => $title,
      'description' => $description,
    ];

    if ($image_url !== NULL) {
      $values['image'] = $this->answerImageDownloader->download($image_url);
    }

    $answer = VotingAnswer::create($values);
    $answer->save();

    $this->entityTypeManager->getStorage('voting_answer_assignment')->create([
      'question_id' => $question->id(),
      'answer_id' => $answer->id(),
      'vote_count' => 0,
    ])->save();

    return $answer;
  }

  /**
   * Absolute URL for an answer's image, or NULL if it has none.
   */
  public function getAnswerImageUrl(VotingAnswer $answer): ?string {
    $image = $answer->get('image')->entity;
    if (!$image instanceof FileInterface) {
      return NULL;
    }

    return $this->fileUrlGenerator->generateAbsoluteString($image->getFileUri());
  }

  private function buildQuestionData(VotingQuestion $question): array {
    return [
      'id' => $question->id(),
      'title' => $question->get('title')->value,
      'question_id' => $question->get('question_id')->value,
      'show_percent' => (bool) $question->get('show_percent')->value,
      'status' => (bool) $question->get('status')->value,
      'created' => $question->get('created')->value,
      'answers' => $this->getQuestionAnswersData((int) $question->id()),
    ];
  }

  private function getQuestionAnswersData(int $question_id): array {
    $assignments = $this->entityTypeManager->getStorage('voting_answer_assignment')->loadByProperties([
      'question_id' => $question_id,
    ]);

    $answers = [];
    foreach ($assignments as $assignment) {
      $answer = $assignment->get('answer_id')->entity;
      if (!$answer) {
        continue;
      }

      $answers[] = [
        'id' => $answer->id(),
        'title' => $answer->label(),
        'votes' => (int) $assignment->get('vote_count')->value,
        'img_url' => $this->getAnswerImageUrl($answer),
      ];
    }

    return $answers;
  }

}
