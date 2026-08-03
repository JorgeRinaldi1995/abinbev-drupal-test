<?php

declare(strict_types=1);

namespace Drupal\voting_system\Access;

use Drupal\Core\Access\AccessCheckInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\Routing\Route;

/**
 * Checks access to the voting system's bearer-token protected API routes.
 *
 * Routes opt in via a `_voting_system_api_access` requirement: 'user' (any
 * authenticated token) or 'admin' (administrator role). By the time this
 * runs, $account is already the token's owner — TokenAuthenticationProvider
 * resolved that earlier in the request lifecycle.
 */
class VotingApiAccessCheck implements AccessCheckInterface, AccessInterface {

  /**
   * {@inheritdoc}
   */
  public function applies(Route $route): bool {
    return $route->hasRequirement('_voting_system_api_access');
  }

  /**
   * {@inheritdoc}
   */
  public function access(Route $route, RouteMatchInterface $route_match, AccountInterface $account): AccessResult {
    if (!$account->isAuthenticated()) {
      return AccessResult::forbidden();
    }

    if ($route->getRequirement('_voting_system_api_access') === 'admin') {
      return AccessResult::allowedIf(in_array('administrator', $account->getRoles(), TRUE));
    }

    return AccessResult::allowed();
  }

}
