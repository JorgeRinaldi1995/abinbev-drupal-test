<?php

declare(strict_types=1);

namespace Drupal\voting_system\Service;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\file\FileInterface;
use Drupal\voting_system\Entity\VotingAnswer;
use Drupal\voting_system\Entity\VotingQuestion;
use Drupal\voting_system\Exception\AnswerLinkedToQuestionException;
use Drupal\voting_system\Exception\AnswerNotFoundException;
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

  /**
   * All answers as plain arrays, ready to be JSON-encoded.
   *
   * Unlike the answers embedded under a question, these aren't tied to any
   * particular assignment, so there's no per-question vote count here.
   */
  public function getAnswersData(): array {
    $answers = $this->entityTypeManager->getStorage('voting_answer')->loadMultiple();
    $images = $this->loadAnswerImages($answers);

    return array_values(array_map(
      fn (VotingAnswer $answer) => $this->buildAnswerData($answer, $this->resolveAnswerImage($answer, $images)),
      $answers
    ));
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

  /**
   * Batch-loads the answers (and their images) for a question's assignments.
   *
   * Avoids an N+1: without this, resolving each assignment's `answer_id`
   * entity reference (and then each answer's `image` reference) would fire
   * one load per row instead of two loadMultiple() calls total.
   */
  private function getQuestionAnswersData(int $question_id): array {
    $assignments = $this->entityTypeManager->getStorage('voting_answer_assignment')->loadByProperties([
      'question_id' => $question_id,
    ]);

    $answer_ids = array_filter(array_map(
      static fn ($assignment) => $assignment->get('answer_id')->target_id,
      $assignments
    ));
    $answers = $answer_ids ? $this->entityTypeManager->getStorage('voting_answer')->loadMultiple($answer_ids) : [];
    $images = $this->loadAnswerImages($answers);

    $data = [];
    foreach ($assignments as $assignment) {
      $answer_id = $assignment->get('answer_id')->target_id;
      $answer = $answers[$answer_id] ?? NULL;
      if (!$answer) {
        continue;
      }

      $data[] = $this->buildAnswerData(
        $answer,
        $this->resolveAnswerImage($answer, $images),
        (int) $assignment->get('vote_count')->value
      );
    }

    return $data;
  }

  /**
   * Batch-loads the image file entities for a set of answers.
   *
   * @param \Drupal\voting_system\Entity\VotingAnswer[] $answers
   *
   * @return \Drupal\file\FileInterface[]
   *   File entities keyed by file ID.
   */
  private function loadAnswerImages(array $answers): array {
    $image_ids = array_filter(array_map(
      static fn (VotingAnswer $answer) => $answer->get('image')->target_id,
      $answers
    ));

    return $image_ids ? $this->entityTypeManager->getStorage('file')->loadMultiple($image_ids) : [];
  }

  /**
   * @param \Drupal\file\FileInterface[] $images
   *   File entities keyed by file ID, as returned by loadAnswerImages().
   */
  private function resolveAnswerImage(VotingAnswer $answer, array $images): ?FileInterface {
    $image_id = $answer->get('image')->target_id;
    return $image_id ? ($images[$image_id] ?? NULL) : NULL;
  }

  /**
   * Builds the plain array representation of an answer for JSON output.
   *
   * $votes is omitted entirely when NULL, since a standalone answer isn't
   * tied to any particular question/assignment and so has no vote count of
   * its own.
   */
  private function buildAnswerData(VotingAnswer $answer, ?FileInterface $image, ?int $votes = NULL): array {
    $data = [
      'id' => $answer->id(),
      'title' => $answer->label(),
    ];

    if ($votes !== NULL) {
      $data['votes'] = $votes;
    }

    $data['description'] = $this->sanitizeDescription($answer->get('description')->value);
    $data['img_url'] = $image instanceof FileInterface ? $this->fileUrlGenerator->generateAbsoluteString($image->getFileUri()) : NULL;

    return $data;
  }

  /**
   * Sanitizes a WYSIWYG answer description for safe API output.
   *
   * Descriptions come from a CKEditor-backed field but are read here as a
   * plain string with no filter format enforced (the API has no concept of
   * text formats), so this is the sanitization boundary: it keeps common
   * formatting tags (bold, links, lists, headings, images, tables, etc.)
   * while stripping <script>, event handler attributes, javascript: URIs
   * and anything else Xss::filterAdmin() doesn't allow.
   */
  private function sanitizeDescription(?string $description): ?string {
    if ($description === NULL || $description === '') {
      return NULL;
    }

    return Xss::filterAdmin($description);
  }

}
