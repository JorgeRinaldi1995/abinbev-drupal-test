<?php

namespace Drupal\voting_system\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\voting_system\Service\VoteService;
use Drupal\Core\Render\Markup;

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
   * The vote service.
   *
   * @var \Drupal\voting_system\Service\VoteService
   */
  protected VoteService $voteService;
  
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
    VoteService $voteService,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->account = $account;
    $this->entityTypeManager = $entityTypeManager;
    $this->formBuilder = $formBuilder;
    $this->voteService = $voteService;
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
      $container->get('voting_system.vote_service'),
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

    if (!$question || !$question->get('status')->value) {
      return ['#markup' => $this->t('The selected question is not available.')];
    }

    // Verifica se o usuário já votou.
    $vote_records = $this->entityTypeManager
      ->getStorage('vote_record')
      ->loadByProperties([
        'user_id' => $this->account->id(),
        'question_id' => $question->id(),
      ]);

    if (!empty($vote_records)) {
      if ($question->get('show_percent')->value) {
        $results = $this->voteService->getResults($question->id());

        $header = [$this->t('Answer'), $this->t('Votes'), $this->t('Percent')];
        $rows = [];

        foreach ($results['answers'] as $answer) {
          $rows[] = [
            ['data' => $answer['title']],
            ['data' => $answer['votes']],
            ['data' => $answer['percent'] . '%'],
          ];
        }

        return [
          '#type' => 'table',
          '#header' => $header,
          '#rows' => $rows,
          '#caption' => $this->t('Results for: @title', ['@title' => $question->label()]),
        ];
      }

      return ['#markup' => $this->t('You have already voted.')];
    }

    // Carrega as respostas da pergunta selecionada.
    $answers = $this->entityTypeManager
      ->getStorage('voting_answer')
      ->loadByProperties(['question_id' => $question->id()]);

    $file_url_generator = \Drupal::service('file_url_generator');

    $options = [];
    foreach ($answers as $answer) {
      $image_url = '';
      if (!$answer->get('image')->isEmpty()) {
        $image_file = $answer->get('image')->entity;
        $image_url = $file_url_generator->generateAbsoluteString($image_file->getFileUri());
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

      $options[$answer->id()] = Markup::create($markup);
    }

    // Retorna o formulário com os dados da questão e respostas.
    return $this->formBuilder->getForm('Drupal\voting_system\Form\VoteForm', $question->id(), $options);
  }
}
