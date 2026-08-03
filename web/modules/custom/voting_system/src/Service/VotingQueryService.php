<?php

declare(strict_types=1);

namespace Drupal\voting_system\Service;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\file\FileInterface;
use Drupal\voting_system\Entity\VotingAnswer;
use Drupal\voting_system\Entity\VotingQuestion;
use Drupal\voting_system\Exception\QuestionNotFoundException;

/**
 * Read-only, cached access to questions and answers for the management API.
 *
 * Split out from VotingManagerService (which only writes) so caching lives
 * entirely here: every public method is a cache-aside lookup, tagged with
 * the same tags Drupal's entity API already invalidates on save()/delete().
 */
class VotingQueryService {

  /**
   * Extra tags every cached payload here needs, beyond its own primary tag.
   *
   * Answers are reusable across questions and their vote tally lives on the
   * assignment, not the question, so a question's cached payload (which
   * embeds both) must also be invalidated when either changes.
   */
  private const SHARED_TAGS = ['voting_answer_assignment_list', 'voting_answer_list'];

  public function __construct(
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly QuestionResolverService $questionResolver,
    protected readonly FileUrlGeneratorInterface $fileUrlGenerator,
    protected readonly CacheBackendInterface $cache,
  ) {}

  /**
   * Active questions as plain arrays, ready to be JSON-encoded.
   *
   * @return list<array<string, mixed>>
   */
  public function getActiveQuestionsData(): array {
    $cid = 'voting_system:questions:active';
    if ($cached = $this->cache->get($cid)) {
      return $cached->data;
    }

    $data = array_map(
      fn (VotingQuestion $question) => $this->buildQuestionData($question),
      $this->loadActiveQuestions()
    );

    $this->cache->set($cid, $data, Cache::PERMANENT, Cache::mergeTags(['voting_question_list'], self::SHARED_TAGS));
    return $data;
  }

  /**
   * A single active question as a plain array, by numeric ID or question_id.
   *
   * @return array<string, mixed>
   *
   * @throws \Drupal\voting_system\Exception\QuestionNotFoundException
   */
  public function getQuestionData(int|string $question_identifier): array {
    $question = $this->questionResolver->resolve($question_identifier);

    if (!$question->get('status')->value) {
      throw new QuestionNotFoundException(sprintf('Question "%s" not found.', $question_identifier));
    }

    // Cached by the resolved entity ID (not the raw $question_identifier
    // argument), so a lookup by question_id and a lookup by numeric ID for
    // the same question share one cache entry instead of two.
    $cid = 'voting_system:question:' . $question->id();
    if ($cached = $this->cache->get($cid)) {
      return $cached->data;
    }

    $data = $this->buildQuestionData($question);

    $this->cache->set($cid, $data, Cache::PERMANENT, Cache::mergeTags(['voting_question:' . $question->id()], self::SHARED_TAGS));
    return $data;
  }

  /**
   * All answers as plain arrays, ready to be JSON-encoded.
   *
   * Unlike answers embedded under a question, these aren't tied to any
   * assignment, so there's no per-question vote count here.
   *
   * @return list<array<string, mixed>>
   */
  public function getAnswersData(): array {
    $cid = 'voting_system:answers:all';
    if ($cached = $this->cache->get($cid)) {
      return $cached->data;
    }

    $answers = $this->entityTypeManager->getStorage('voting_answer')->loadMultiple();
    $images = $this->loadAnswerImages($answers);

    $data = array_values(array_map(
      fn (VotingAnswer $answer) => $this->buildAnswerData($answer, $this->resolveAnswerImage($answer, $images)),
      $answers
    ));

    $this->cache->set($cid, $data, Cache::PERMANENT, ['voting_answer_list']);
    return $data;
  }

  /**
   * Absolute URL for an answer's image, or NULL if it has none.
   *
   * Not cached: a single field access plus, at most, one file load — not
   * worth the overhead of a cache round-trip.
   */
  public function getAnswerImageUrl(VotingAnswer $answer): ?string {
    $image = $answer->get('image')->entity;
    if (!$image instanceof FileInterface) {
      return NULL;
    }

    return $this->fileUrlGenerator->generateAbsoluteString($image->getFileUri());
  }

  /**
   * @return \Drupal\voting_system\Entity\VotingQuestion[]
   */
  private function loadActiveQuestions(): array {
    return $this->entityTypeManager
      ->getStorage('voting_question')
      ->loadByProperties(['status' => 1]);
  }

  /**
   * @return array<string, mixed>
   */
  private function buildQuestionData(VotingQuestion $question): array {
    return [
      'id' => $question->id(),
      'title' => $question->get('title')->value,
      'question_id' => $question->get('question_id')->value,
      'show_percent' => (bool) $question->get('show_percent')->value,
      'status' => (bool) $question->get('status')->value,
      'created' => $question->get('created')->value,
      'answers' => $this->getQuestionAnswersData((int) $question->id(), (bool) $question->get('show_percent')->value),
    ];
  }

  /**
   * Batch-loads the answers (and their images) for a question's assignments,
   * avoiding an N+1 (two loadMultiple() calls total instead of one load per
   * assignment). $include_votes mirrors the question's `show_percent`: when
   * off, the vote count is omitted, not just the percentage — otherwise a
   * caller could read this listing to bypass a disabled show_percent.
   *
   * @return list<array<string, mixed>>
   */
  private function getQuestionAnswersData(int $question_id, bool $include_votes): array {
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
        $include_votes ? (int) $assignment->get('vote_count')->value : NULL
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
   * $votes is omitted entirely when NULL (a standalone answer has no vote
   * count of its own).
   *
   * @return array<string, mixed>
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
   * Sanitizes a WYSIWYG answer description for safe API output — the API
   * has no text-format concept, so this is the sanitization boundary
   * (keeps formatting tags, strips scripts/event handlers/javascript: URIs).
   */
  private function sanitizeDescription(?string $description): ?string {
    if ($description === NULL || $description === '') {
      return NULL;
    }

    return Xss::filterAdmin($description);
  }

}
