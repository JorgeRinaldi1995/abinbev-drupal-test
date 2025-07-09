<?php

namespace Drupal\voting_system\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;

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

  protected AccountInterface $account;
  protected EntityTypeManagerInterface $entityTypeManager;

  public function __construct(array $configuration, $plugin_id, $plugin_definition, AccountInterface $account, EntityTypeManagerInterface $entityTypeManager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->account = $account;
    $this->entityTypeManager = $entityTypeManager;
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_user'),
      $container->get('entity_type.manager')
    );
  }

  public function defaultConfiguration(): array {
    return ['question_id' => ''];
  }

  public function blockForm($form, FormStateInterface $form_state): array {
    $options = [];

    $questions = $this->entityTypeManager
      ->getStorage('voting_question')
      ->loadMultiple();

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

  public function blockSubmit($form, FormStateInterface $form_state): void {
    $this->configuration['question_id'] = $form_state->getValue('question_id');
  }

  public function build(): array {
    $question_id = $this->configuration['question_id'];

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

    // Check if user already voted
    $existing = $this->entityTypeManager
      ->getStorage('vote_record')
      ->loadByProperties([
        'user_id' => $this->account->id(),
        'question_id' => $question->id(),
      ]);

    if (!empty($existing)) {
      if ($question->get('show_percent')->value) {
        $results = \Drupal::service('voting_system.vote_service')->getResults($question->id());

        $header = [$this->t('Answer'), $this->t('Votes'), $this->t('Percent')];
        $rows = [];

        foreach ($results['answers'] as $answer) {
          $rows[] = [
            $answer['title'],
            $answer['votes'],
            $answer['percent'] . '%',
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

    $answers = $this->entityTypeManager
      ->getStorage('voting_answer')
      ->loadByProperties(['question_id' => $question->id()]);

    $options = [];
    foreach ($answers as $answer) {
      $options[$answer->id()] = $answer->get('title')->value;
    }

    return \Drupal::formBuilder()->getForm('\Drupal\voting_system\Form\VoteForm', $question->id(), $options);
  }
}
