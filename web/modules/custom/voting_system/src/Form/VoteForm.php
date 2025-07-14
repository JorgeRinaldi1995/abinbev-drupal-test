<?php

namespace Drupal\voting_system\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\voting_system\Service\VoteService;
use Drupal\Core\Session\AccountProxyInterface;

class VoteForm extends FormBase {

  protected int $questionId;
  protected array $options;
  protected VoteService $voteService;
  protected AccountProxyInterface $currentUser;

  public function __construct(VoteService $voteService, AccountProxyInterface $currentUser) {
    $this->voteService = $voteService;
    $this->currentUser = $currentUser;
  }

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('voting_system.vote_service'),
      $container->get('current_user')
    );
  }

  public function getFormId(): string {
    return 'voting_system_vote_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, $question_id = NULL, $options = []): array {
    $this->questionId = $question_id;
    $this->options = $options;

    $form['answer_id'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Select an answer'),
    ];

    foreach ($options as $id => $rendered_option) {
      $form['answer_id'][$id] = [
        '#type' => 'radio',
        '#title' => $rendered_option,
        '#return_value' => $id,
        '#parents' => ['answer_id'],
      ];
    }

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Vote'),
    ];

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $answer_id = (int) $form_state->getValue('answer_id');

    try {
      $this->voteService->submitVote($answer_id, $this->currentUser);
      \Drupal::messenger()->addMessage($this->t('Your vote has been recorded.'));
    } catch (\Throwable $e) {
      \Drupal::messenger()->addError($e->getMessage());
    }
  }
}
