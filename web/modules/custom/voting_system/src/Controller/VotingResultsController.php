<?php

declare(strict_types=1);

namespace Drupal\voting_system\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\voting_system\Service\VoteResultsService;
use Symfony\Component\DependencyInjection\ContainerInterface;

class VotingResultsController extends ControllerBase implements ContainerInjectionInterface {

  public function __construct(
    protected readonly VoteResultsService $voteResultsService,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('voting_system.vote_results_service')
    );
  }

  public function resultsPage(): array {
    $questions = $this->entityTypeManager()
      ->getStorage('voting_question')
      ->loadMultiple();

    if (empty($questions)) {
      return [
        '#type' => 'markup',
        '#markup' => $this->t('No questions available.'),
      ];
    }

    $build = [];
    foreach ($questions as $question) {
      $results = $this->voteResultsService->getResults($question->id());

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
          '#header' => [$this->t('Answer'), $this->t('Votes'), $this->t('Percentage')],
          '#rows' => $rows,
        ],
      ];
    }

    return $build;
  }

}
