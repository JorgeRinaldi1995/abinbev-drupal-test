<?php

declare(strict_types=1);

namespace Drupal\voting_system\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\voting_system\Entity\VotingAnswer;
use Drupal\voting_system\Entity\VotingQuestion;
use Drupal\voting_system\Exception\AnswerLinkedToQuestionException;
use Drupal\voting_system\Exception\AnswerNotFoundException;
use Drupal\voting_system\Exception\DuplicateQuestionIdentifierException;

/**
 * Creates and updates questions and answers.
 *
 * Read access lives in VotingQueryService instead, so writes and cached
 * reads each have exactly one reason to change.
 */
class VotingManagerService {

  public function __construct(
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly QuestionResolverService $questionResolver,
    protected readonly AnswerImageDownloaderService $answerImageDownloader,
  ) {}

  /**
   * Creates a question with a unique, normalized question_id.
   *
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
   * Creates an answer and links it to a question via a new assignment.
   *
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
   * Partially updates an answer. Only allowed while it isn't linked to any
   * question yet — once an answer is assigned, editing it here would
   * silently change what people already voted on (answers are reusable
   * across questions, so it could affect more than one question at once).
   *
   * @throws \Drupal\voting_system\Exception\AnswerNotFoundException
   * @throws \Drupal\voting_system\Exception\AnswerLinkedToQuestionException
   * @throws \Drupal\voting_system\Exception\InvalidAnswerImageException
   */
  public function updateAnswer(
    int $answer_id,
    ?string $title = NULL,
    ?string $description = NULL,
    ?string $image_url = NULL,
  ): VotingAnswer {
    $answer = $this->entityTypeManager->getStorage('voting_answer')->load($answer_id);
    if (!$answer instanceof VotingAnswer) {
      throw new AnswerNotFoundException(sprintf('Answer %d not found.', $answer_id));
    }

    $assignments = $this->entityTypeManager->getStorage('voting_answer_assignment')->loadByProperties([
      'answer_id' => $answer_id,
    ]);
    if (!empty($assignments)) {
      throw new AnswerLinkedToQuestionException('This answer is linked to a question and cannot be updated.');
    }

    if ($title !== NULL) {
      $answer->set('title', $title);
    }

    if ($description !== NULL) {
      $answer->set('description', $description);
    }

    if ($image_url !== NULL) {
      $answer->set('image', $this->answerImageDownloader->download($image_url));
    }

    $answer->save();
    return $answer;
  }

}
