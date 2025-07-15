<?php

namespace Drupal\voting_system\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\voting_system\Service\VoteService;

class VotingResultsController extends ControllerBase implements ContainerInjectionInterface {

  protected VoteService $voteService;

  public function __construct(VoteService $voteService) {
    $this->voteService = $voteService;
  }

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('voting_system.vote_service')
    );
  }

  public function resultsPage(): array {
    $build = [
      '#type' => 'markup',
      '#markup' => '',
    ];

    $questions = \Drupal::entityTypeManager()
      ->getStorage('voting_question')
      ->loadMultiple();

    if (empty($questions)) {
      $build['#markup'] = $this->t('No questions available.');
      return $build;
    }

    foreach ($questions as $question) {
      $results = $this->voteService->getResults($question->id());

      $header = [
        $this->t('Answer'),
        $this->t('Votes'),
        $this->t('Percentage'),
      ];

      $rows = [];

      foreach ($results['answers'] as $answer) {
        $rows[] = [
          $answer['title'],
          $answer['votes'],
          $answer['percent'] . '%',
        ];
      }

      $build[] = [
        '#type' => 'details',
        '#title' => $question->label() . ' (' . $results['total_votes'] . ' votes)',
        '#open' => TRUE,
        'table' => [
          '#type' => 'table',
          '#header' => $header,
          '#rows' => $rows,
        ],
      ];
    }

    return $build;
  }
}
