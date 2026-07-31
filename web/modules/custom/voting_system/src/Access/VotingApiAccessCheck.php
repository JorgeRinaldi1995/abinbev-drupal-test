<?php

declare(strict_types=1);

namespace Drupal\voting_system\Access;

use Drupal\Core\Access\AccessCheckInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\voting_system\Service\TokenAuthService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Route;

/**
 * Checks bearer-token access to the voting system's API routes.
 *
 * Routes opt in with a `_voting_system_api_access` requirement set to
 * either 'user' (any authenticated token) or 'admin' (token belonging to
 * an administrator).
 */
class VotingApiAccessCheck implements AccessCheckInterface, AccessInterface {

  public function __construct(protected readonly TokenAuthService $tokenAuthService) {}

  public function applies(Route $route): bool {
    return $route->hasRequirement('_voting_system_api_access');
  }

  public function access(Route $route, RouteMatchInterface $route_match, AccountInterface $account, ?Request $request = NULL): AccessResult {
    if (!$request) {
      return AccessResult::forbidden();
    }

    if ($route->getRequirement('_voting_system_api_access') === 'admin') {
      return $this->tokenAuthService->checkAdminAccess($request);
    }

    $user = $this->tokenAuthService->getUserFromToken($request);
    if ($user) {
      $request->attributes->set('voting_system.current_user', $user);
      return AccessResult::allowed();
    }

    return AccessResult::forbidden();
  }

}
