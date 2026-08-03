<?php

namespace Drupal\voting_system\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Form\FormStateInterface;

/**
 * Custom form for creating/editing voting questions with reusable answers.
 */
class VotingQuestionForm extends ContentEntityForm {

  /**
   * Adds the "add another answer" repeater on top of the default entity form.
   *
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    $entity = $this->entity;
    $rows = $form_state->get('answer_rows');
    if ($rows === NULL) {
      $rows = [0];
      $form_state->set('answer_rows', $rows);
    }

    $form['status_messages'] = [
      '#type' => 'status_messages',
      '#weight' => -10,
    ];

    $form['answers_wrapper'] = [
      '#type' => 'details',
      '#title' => $this->t('Answers for this question'),
      '#open' => TRUE,
      '#tree' => TRUE,
      '#prefix' => '<div id="question-answers-wrapper">',
      '#suffix' => '</div>',
      '#weight' => 10,
    ];

    if ($entity->id()) {
      $existing_assignments = $this->loadExistingAssignments($entity->id());
      if (!empty($existing_assignments)) {
        $existing_items = [];
        foreach ($existing_assignments as $assignment) {
          $answer = $assignment->get('answer_id')->entity;
          if ($answer) {
            $existing_items[] = $answer->label();
          }
        }

        if (!empty($existing_items)) {
          $form['answers_wrapper']['existing'] = [
            '#type' => 'item',
            '#title' => $this->t('Existing answers already linked to this question'),
            '#markup' => implode(', ', $existing_items),
          ];
        }
      }
    }

    $selected_values = [];

    foreach ($rows as $delta) {
      $selected_value = $form_state->getValue(['answers_wrapper', 'rows', $delta, 'answer']);
      if ($selected_value) {
        $selected_values[] = $selected_value;
      }
    }

    $options = $this->buildAnswerOptions(array_unique($selected_values));

    foreach ($rows as $delta) {
      $default_value = $form_state->getValue(['answers_wrapper', 'rows', $delta, 'answer']);

      $form['answers_wrapper']['rows'][$delta] = [
        '#type' => 'container',
        '#tree' => TRUE,
      ];

      $form['answers_wrapper']['rows'][$delta]['answer'] = [
        '#type' => 'select',
        '#title' => $this->t('Answer @number', ['@number' => $delta + 1]),
        '#options' => $options,
        '#empty_option' => $this->t('- Select an existing answer -'),
        '#default_value' => $default_value,
      ];
    }

    $form['answers_wrapper']['add_more'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add another answer'),
      '#submit' => ['::addAnotherAnswer'],
      '#limit_validation_errors' => [],
      '#name' => 'add_more_answers',
    ];

    return $form;
  }

  /**
   * Add another answer row to the repeater.
   */
  public function addAnotherAnswer(array &$form, FormStateInterface $form_state): void {
    $rows = $form_state->get('answer_rows') ?: [];
    $rows[] = count($rows);
    $form_state->set('answer_rows', $rows);
    $form_state->setRebuild(TRUE);
  }

  /**
   * Rejects duplicate answer selections. Duplicate question_id values are
   * already caught by the UniqueQuestionIdentifier constraint on the field
   * (see VotingQuestion::baseFieldDefinitions()), enforced automatically by
   * parent::validateForm() via $entity->validate().
   *
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);

    $rows = $form_state->get('answer_rows') ?: [];
    $selected_ids = [];

    foreach ($rows as $delta) {
      $answer_id = $form_state->getValue(['answers_wrapper', 'rows', $delta, 'answer']);
      if (!$answer_id) {
        continue;
      }

      if (in_array($answer_id, $selected_ids, TRUE)) {
        $form_state->setError($form['answers_wrapper']['rows'][$delta]['answer'], $this->t('This answer was already selected.'));
        continue;
      }

      $selected_ids[] = $answer_id;
    }
  }

  /**
   * Saves the question, then links the selected answers.
   *
   * Answer linking must happen here rather than in submitForm(): entity
   * forms save in a separate ::save handler that runs afterward in the
   * button's #submit chain, so a new question's ID doesn't exist yet by the
   * end of submitForm().
   *
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    try {
      $status = parent::save($form, $form_state);
    }
    catch (EntityStorageException $e) {
      $this->messenger()->addError($this->t('The question identifier must be unique'));
      $form_state->setRebuild(TRUE);
      return NULL;
    }

    $this->saveAnswerAssignments($form_state);
    $this->messenger()->addStatus($this->t('Question created successfully.'));

    return $status;
  }

  /**
   * Creates assignments for any newly selected answers on the saved question.
   */
  protected function saveAnswerAssignments(FormStateInterface $form_state): void {
    $entity = $this->entity;
    $rows = $form_state->get('answer_rows') ?: [];
    $assignment_storage = \Drupal::entityTypeManager()->getStorage('voting_answer_assignment');

    foreach ($rows as $delta) {
      $answer_id = $form_state->getValue(['answers_wrapper', 'rows', $delta, 'answer']);
      if (!$answer_id) {
        continue;
      }

      $existing = $assignment_storage->loadByProperties([
        'question_id' => $entity->id(),
        'answer_id' => $answer_id,
      ]);

      if (!empty($existing)) {
        continue;
      }

      $assignment = $assignment_storage->create([
        'question_id' => $entity->id(),
        'answer_id' => $answer_id,
        'vote_count' => 0,
      ]);
      $assignment->save();
    }
  }

  /**
   * @return \Drupal\voting_system\Entity\VotingAnswerAssignment[]
   */
  protected function loadExistingAssignments(int $question_id): array {
    return \Drupal::entityTypeManager()
      ->getStorage('voting_answer_assignment')
      ->loadByProperties(['question_id' => $question_id]);
  }

  /**
   * Builds the options list for the repeater rows, excluding answers already
   * picked in another row.
   *
   * @return array<int|string, string>
   */
  protected function buildAnswerOptions(array $excluded_ids = []): array {
    $answers = \Drupal::entityTypeManager()->getStorage('voting_answer')->loadMultiple();
    $options = ['' => $this->t('- Select an existing answer -')];

    foreach ($answers as $answer) {
      if (in_array((int) $answer->id(), $excluded_ids, TRUE)) {
        continue;
      }

      $options[$answer->id()] = $answer->label();
    }

    return $options;
  }

}
