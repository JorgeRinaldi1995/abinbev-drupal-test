<?php

namespace Drupal\voting_system\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\voting_system\Service\VoteResultsService;
use Drupal\Core\Render\Markup;
use Drupal\file\FileInterface;


/**
 * Provides a Voting Block.
 *
 * @Block(
 *   id = "voting_block",
 *   admin_label = @Translation("Voting Block"),
 *   category = @Translation("Voting")
 * )
 */
class VoteBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected AccountInterface $account;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The form builder service.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected FormBuilderInterface $formBuilder;

  /**
   * The vote results service.
   *
   * @var \Drupal\voting_system\Service\VoteResultsService
   */
  protected VoteResultsService $voteResultsService;

  /**
   * The file URL generator.
   *
   * @var \Drupal\Core\File\FileUrlGeneratorInterface
   */
  protected FileUrlGeneratorInterface $fileUrlGenerator;

  /**
   * Constructs the VoteBlock.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    AccountInterface $account,
    EntityTypeManagerInterface $entityTypeManager,
    FormBuilderInterface $formBuilder,
    VoteResultsService $voteResultsService,
    FileUrlGeneratorInterface $fileUrlGenerator,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->account = $account;
    $this->entityTypeManager = $entityTypeManager;
    $this->formBuilder = $formBuilder;
    $this->voteResultsService = $voteResultsService;
    $this->fileUrlGenerator = $fileUrlGenerator;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_user'),
      $container->get('entity_type.manager'),
      $container->get('form_builder'),
      $container->get('voting_system.vote_results_service'),
      $container->get('file_url_generator'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return ['question_id' => ''];
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state): array {
    $questions = $this->entityTypeManager
      ->getStorage('voting_question')
      ->loadMultiple();

    $options = [];
    foreach ($questions as $question) {
      $options[$question->id()] = $question->label();
    }

    $form['question_id'] = [
      '#type' => 'select',
      '#title' => $this->t('Select a question to show'),
      '#options' => $options,
      '#default_value' => $this->configuration['question_id'],
      '#required' => TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state): void {
    $this->configuration['question_id'] = $form_state->getValue('question_id');
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $question_id = (int) $this->configuration['question_id'];

    if (!$question_id) {
      return ['#markup' => $this->t('No question selected.')];
    }

    /** @var \Drupal\voting_system\Entity\VotingQuestion|null $question */
    $question = $this->entityTypeManager
      ->getStorage('voting_question')
      ->load($question_id);

    // Whether the current visitor has voted (and thus sees results instead
    // of the ballot) depends on who they are, so the render cache must vary
    // per user — without this, the first voter's outcome gets cached and
    // served to everyone else until the cache is cleared.
    $cache = new CacheableMetadata();
    $cache->addCacheContexts(['user']);
    $cache->addCacheTags(['voting_question_list']);

    if ($question) {
      $cache->addCacheableDependency($question);
    }

    if (!$question || !$question->get('status')->value) {
      $build = ['#markup' => $this->t('The selected question is not available.')];
      $cache->applyTo($build);
      return $build;
    }

    $assignments = $this->entityTypeManager
      ->getStorage('voting_answer_assignment')
      ->loadByProperties(['question_id' => $question->id()]);

    // Each assignment's own cache tag is invalidated when its vote_count
    // changes, and vote_record_list is invalidated whenever anyone casts a
    // new vote — together they keep counts, percentages and the "already
    // voted" state correct for every cached variation.
    foreach ($assignments as $assignment) {
      $cache->addCacheableDependency($assignment);
    }
    $cache->addCacheTags(['vote_record_list']);

    $has_user_vote = FALSE;
    foreach ($assignments as $assignment) {
      $vote_records = $this->entityTypeManager
        ->getStorage('vote_record')
        ->loadByProperties([
          'user_id' => $this->account->id(),
          'assignment_id' => $assignment->id(),
        ]);

      if (!empty($vote_records)) {
        $has_user_vote = TRUE;
        break;
      }
    }

    if ($has_user_vote) {
      if ($question->get('show_percent')->value) {
        $results = $this->voteResultsService->getResults($question->id());

        $header = [$this->t('Answer'), $this->t('Votes'), $this->t('Percent')];
        $rows = [];

        foreach ($results['answers'] as $answer) {
          $rows[] = [
            ['data' => $answer['title']],
            ['data' => $answer['votes']],
            ['data' => $answer['percent'] . '%'],
          ];
        }

        $build = [
          '#type' => 'table',
          '#header' => $header,
          '#rows' => $rows,
          '#caption' => $this->t('Results for: @title', ['@title' => $question->label()]),
        ];
        $cache->applyTo($build);
        return $build;
      }

      $build = ['#markup' => $this->t('You have already voted.')];
      $cache->applyTo($build);
      return $build;
    }

    $build = [
      '#type' => 'container',
      '#attributes' => ['class' => ['voting-block']],
    ];

    $build['question_title'] = [
      '#type' => 'html_tag',
      '#tag' => 'h3',
      '#value' => $question->label(),
    ];

    $options = [];
    foreach ($assignments as $assignment) {
      $answer = $assignment->get('answer_id')->entity;
      if (!$answer) {
        continue;
      }

      $image_url = '';
      $image = $answer->get('image')->entity;

      if ($image instanceof FileInterface) {
        $image_url = $this->fileUrlGenerator->generateAbsoluteString(
          $image->getFileUri()
        );
      }

      $title = $answer->get('title')->value;
      $description = $answer->get('description')->value;

      $markup = '<div class="vote-option">';
      if ($image_url) {
        $markup .= '<div><img src="' . $image_url . '" style="max-width:100px;margin-bottom:8px;" /></div>';
      }
      $markup .= '<div><strong>' . $title . '</strong></div>';
      if (!empty($description)) {
        $markup .= '<div>' . $description . '</div>';
      }
      $markup .= '</div>';

      $options[$assignment->id()] = Markup::create($markup);
    }

    $build['form'] = $this->formBuilder->getForm('Drupal\voting_system\Form\VoteForm', $question->id(), $options);

    $cache->applyTo($build);
    return $build;
  }
}
