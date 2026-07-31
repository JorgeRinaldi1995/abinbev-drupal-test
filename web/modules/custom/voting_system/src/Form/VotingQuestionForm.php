<?php

namespace Drupal\voting_system\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;

/**
 * Custom form for creating/editing voting questions with reusable answers.
 */
class VotingQuestionForm extends ContentEntityForm {

  /**
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

    $question_id_value = $form_state->getValue('question_id');
    if (is_array($question_id_value)) {
      if (isset($question_id_value['value']) && is_scalar($question_id_value['value'])) {
        $question_id_value = $question_id_value['value'];
      }
      elseif (isset($question_id_value[0]) && is_scalar($question_id_value[0])) {
        $question_id_value = $question_id_value[0];
      }
      else {
        return;
      }
    }

    if (!is_scalar($question_id_value)) {
      return;
    }

    $question_id = trim((string) $question_id_value);
    if ($question_id === '') {
      return;
    }

    $query = \Drupal::entityQuery('voting_question')
      ->accessCheck(FALSE)
      ->condition('question_id', $question_id);

    if (!$this->entity->isNew()) {
      $query->condition('id', $this->entity->id(), '<>');
    }

    $ids = $query->execute();

    if (!empty($ids)) {
      $form_state->setErrorByName(
        'question_id',
        $this->t('The question identifier is already in use. Please choose another one.')
      );
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    try {
      parent::submitForm($form, $form_state);

      $entity = $this->entity;
      if (!$entity->id()) {
        return;
      }

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

      $this->messenger()->addStatus($this->t('Question created successfully.'));
    }
    catch (EntityStorageException $e) {
      $this->messenger()->addError($this->t('The question identifier must be unique'));
      $form_state->setRebuild(TRUE);
    }
    catch (\Exception $e) {
      $this->messenger()->addError($this->t('The question identifier must be unique'));
      $form_state->setRebuild(TRUE);
    }
  }

  /**
   * Load existing assignments for the given question.
   */
  protected function loadExistingAssignments(int $question_id): array {
    return \Drupal::entityTypeManager()
      ->getStorage('voting_answer_assignment')
      ->loadByProperties(['question_id' => $question_id]);
  }

  /**
   * Build the options list for the repeater rows.
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
