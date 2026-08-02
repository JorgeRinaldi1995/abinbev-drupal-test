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
 * Routes opt in with a `_voting_system_api_access` requirement set to
 * either 'user' (any authenticated token) or 'admin' (token belonging to
 * an administrator). Resolving the bearer token into an account is
 * TokenAuthenticationProvider's job, which runs earlier in the request
 * lifecycle; by the time this check runs, $account already IS that user.
 */
class VotingApiAccessCheck implements AccessCheckInterface, AccessInterface {

  public function applies(Route $route): bool {
    return $route->hasRequirement('_voting_system_api_access');
  }

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
