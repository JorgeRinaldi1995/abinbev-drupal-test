<?php

declare(strict_types=1);

namespace Drupal\voting_system\Authentication\Provider;

use Drupal\Core\Authentication\AuthenticationProviderInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\voting_system\Service\TokenAuthService;
use Symfony\Component\HttpFoundation\Request;

/**
 * Authenticates API requests carrying a `voting_system` bearer token.
 *
 * Without a real authentication provider, Drupal's ambient current user
 * (\Drupal::currentUser(), the current_user service, ControllerBase's
 * currentUser()) stays anonymous for the whole request, because nothing
 * tells Drupal who the bearer token belongs to — VotingApiAccessCheck only
 * decided whether the request could proceed, it never authenticated it.
 * That's why question/answer authorship was silently saved as anonymous.
 */
class TokenAuthenticationProvider implements AuthenticationProviderInterface {

  public function __construct(
    protected readonly TokenAuthService $tokenAuthService,
  ) {}

  public function applies(Request $request): bool {
    return str_starts_with((string) $request->headers->get('Authorization'), 'Bearer ');
  }

  public function authenticate(Request $request): ?AccountInterface {
    return $this->tokenAuthService->getUserFromToken($request);
  }

}
