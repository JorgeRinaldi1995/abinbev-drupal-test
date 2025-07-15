<?php

namespace Drupal\voting_system\Service;

use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;

/**
 * Service to handle API token generation and validation.
 */
class TokenService {

  protected KeyValueFactoryInterface $keyValueFactory;

  public function __construct(KeyValueFactoryInterface $keyValueFactory) {
    $this->keyValueFactory = $keyValueFactory;
  }

  /**
   * Generates a secure bearer token for the given user ID.
   *
   * @param int $uid
   *   The user ID.
   *
   * @return string
   *   The generated token.
   */
  public function generateToken(int $uid): string {
    $token = bin2hex(random_bytes(32));

    $store = $this->keyValueFactory->get('oauth_tokens');
    $store->set($token, [
      'uid' => $uid,
      'expires' => time() + 3600,
    ]);

    return $token;
  }

  /**
   * Validates a bearer token.
   *
   * @param string $token
   *   The token string.
   *
   * @return int|null
   *   Returns the user ID if valid, or NULL if invalid/expired.
   */
  public function validateToken(string $token): ?int {
    $store = $this->keyValueFactory->get('oauth_tokens');
    $data = $store->get($token);

    if (!$data || !isset($data['expires']) || $data['expires'] < time()) {
      return NULL;
    }

    return (int) $data['uid'];
  }
}