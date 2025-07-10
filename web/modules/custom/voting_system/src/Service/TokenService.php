<?php

namespace Drupal\voting_system\Service;

use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\Core\Datetime\DrupalDateTime;

class TokenService {

  public function generateToken($uid) {
    $token = bin2hex(random_bytes(32));
    $store = \Drupal::keyValue('oauth_tokens');
    $store->set($token, [
      'uid' => $uid,
      'expires' => time() + 3600,
    ]);
    return $token;
  }

  public function validateToken($token) {
    $store = \Drupal::keyValue('oauth_tokens');
    $data = $store->get($token);
    if (!$data || $data['expires'] < time()) {
      return NULL;
    }
    return $data['uid'];
  }
}
