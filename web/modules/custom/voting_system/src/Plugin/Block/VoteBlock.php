<?php

namespace Drupal\voting_system\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\voting_system\Entity\VotingQuestion;
use Drupal\Core\Session\AccountInterface;

/**
 * Provides a Voting Block.
 *
 * @Block(
 *   id = "voting_block",
 *   admin_label = @Translation("Voting Block")
 * )
 */
class VoteBlock extends BlockBase implements ContainerFactoryPluginInterface {

  protected AccountInterface $account;

  public function __construct(array $configuration, $plugin_id, $plugin_definition, AccountInterface $account) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->account = $account;
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_user')
    );
  }

  public function build(): array {
    $questions = \Drupal::entityTypeManager()
      ->getStorage('voting_question')
      ->loadByProperties(['status' => 1]);

    if (empty($questions)) {
      return ['#markup' => $this->t('No active voting question.')];
    }

    $question = reset($questions);

    // Check if user already voted
    $existing = \Drupal::entityTypeManager()
      ->getStorage('vote_record')
      ->loadByProperties([
        'user_id' => $this->account->id(),
        'question_id' => $question->id(),
      ]);

    if (!empty($existing)) {
      return ['#markup' => $this->t('You have already voted.')];
    }

    $answers = \Drupal::entityTypeManager()
      ->getStorage('voting_answer')
      ->loadByProperties(['question_id' => $question->id()]);

    $options = [];
    foreach ($answers as $answer) {
      $options[$answer->id()] = $answer->get('title')->value;
    }

    return \Drupal::formBuilder()->getForm('\Drupal\voting_system\Form\VoteForm', $question->id(), $options);
  }
}
