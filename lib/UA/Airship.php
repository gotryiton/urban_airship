<?php

namespace UA;

use Requests;

class Airship {

    const SERVER = 'go.urbanairship.com';
    const BASE_URL = 'https://go.urbanairship.com/api';
    const DEVICE_TOKEN_URL = '/device_tokens/';
    const PUSH_URL = '/push/';
    const BROADCAST_URL = '/push/broadcast/';
    const FEEDBACK_URL = '/device_tokens/feedback/';

    private $key = '';
    private $secret = '';
    private $master = null;

    public function __construct($key, $secret, $master = null) {
        $this->key = $key;
        $this->secret = $secret;
        $this->master = $master;
        return true;
    }

    public function _request($page, $method, $body, $content_type = null, $master = false) {
        $url = self::BASE_URL . $page;

        $headers = array();
        if (!is_null($content_type)) {
            $headers['Content-Type'] = $content_type;
        }

        if ($master) {
            if (!is_null($this->master)) {
                $auth = array($this->key, $this->master);
            }
            else {
                throw new Unauthorized("The master key must be used for requests to {$page}");
            }
        }
        else {
            $auth = array($this->key, $this->secret);
        }

        $response = Requests::request($url, $headers, $body, $method, array('auth' => $auth));
        if ($response->status_code == 401) {
            throw new Unauthorized();
        }
        return $response;
    }

    // Register the device token with UA.
    public function register($device_token, $alias=null, $tags=null, $badge=null) {
        $url = self::DEVICE_TOKEN_URL . $device_token;
        $payload = array();
        if ($alias != null) {
            $payload['alias'] = $alias;
        }
        if ($tags != null) {
            $payload['tags'] = $tags;
        }
        if ($badge != null) {
            $payload['badge'] = $badge;
        }
        if (count($payload) != 0) {
            $body = json_encode($payload);
            $content_type = 'application/json';
        } else {
            $body = '';
            $content_type = null;
        }
        $response = $this->_request($url, 'PUT', $body, $content_type);
        if ($response->status_code != 201 && $response->status_code != 200) {
            throw new AirshipFailure($response->body, $response->status_code);
        }
        return ($response->status_code == 201);
    }

    // Mark the device token as inactive.
    public function deregister($device_token) {
        $url = self::DEVICE_TOKEN_URL . $device_token;
        $response = $this->_request($url, 'DELETE', null, null);
        if ($response->status_code != 204) {
            throw new AirshipFailure($response->body, $response->status_code);
        }
        else {
            return true;
        }
    }

    // Retrieve information about this device token.
    public function get_device_token_info($device_token) {
        $url = self::DEVICE_TOKEN_URL . $device_token;
        $response = $this->_request($url, 'GET', null, null);
        if ($response->status_code != 200) {
            throw new AirshipFailure($response->body, $response->status_code);
        }
        return json_decode($response->body);
    }


    public function get_device_tokens() {
        return new DeviceList($this);
    }

    // Push this payload to the specified device tokens and tags.
    public function push($payload, $aliases = null, $device_tokens = null, $tags = null) {
        if ($device_tokens != null) {
            if (!is_array($device_tokens)) {
                $device_tokens = array($device_tokens);
            }
            $payload['device_tokens'] = $device_tokens;
        }
        if ($aliases != null) {
            if (!is_array($aliases)) {
                $aliases = array($aliases);
            }
            $payload['aliases'] = $aliases;
        }
        if ($tags != null) {
            $payload['tags'] = $tags;
        }
        $body = json_encode($payload);
        $response = $this->_request(self::PUSH_URL, 'POST', $body, 'application/json', true);
        if ($response->status_code != 200) {
            throw new AirshipFailure($response->body, $response->status_code);
        }
        else {
            return true;
        }
    }

    // Broadcast this payload to all users.
    public function broadcast($payload, $exclude_tokens=null) {
        if ($exclude_tokens != null) {
            $payload['exclude_tokens'] = $exclude_tokens;
        }
        $body = json_encode($payload);
        $response = $this->_request(self::BROADCAST_URL, 'POST', $body, 'application/json');
        if ($response->status_code != 200) {
            throw new AirshipFailure($response->body, $response->status_code);
        }
        else {
            return true;
        }
    }

    /*
     Return device tokens marked as inactive since this timestamp
     Return a list of (device token, timestamp, alias) functions.
     */
    public function feedback($since) {
        $url = self::FEEDBACK_URL . '?' . 'since=' . rawurlencode($since->format('c'));

        $response = $this->_request($url, 'GET', null, null, true);
        if ($response->status_code != 200) {
            throw new AirshipFailure($response->body, $response->status_code);
        }
        $results = json_decode($response->body);
        foreach ($results as $item) {
            $item->marked_inactive_on = new DateTime($item->marked_inactive_on,
                                                       new DateTimeZone('UTC'));
        }
        return $results;
    }

    public static function build_payload($alert, $url = null, $badge = null, $sound = null) {
        $payload = array('aps' => array(
            'alert' => $alert,
            'loc-args' => array('url' => $url)
        ));

        if (!is_null($badge)) {
            $payload['badge'] = $badge;
        }

        if (!is_null($sound)) {
            $payload['sound'] = $sound;
        }

        return $payload;
     }

}

?>
