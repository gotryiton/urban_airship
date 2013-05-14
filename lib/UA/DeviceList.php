<?php

namespace UA;

use Iterator,
    Countable;

class DeviceList implements Iterator, Countable {
    private $_airship = null;
    private $_page = null;
    private $_position = 0;

    public function __construct($airship) {
        $this->_airship = $airship;
        $this->_load_page(Airship::DEVICE_TOKEN_URL);
        $this->_position = 0;
    }

    private function _load_page($url) {
        $response = $this->_airship->_request($url, 'GET', null, null, true);
        if ($response->status_code != 200) {
            throw new AirshipFailure($response->body, $response->status_code);
        }
        $result = json_decode($response->body);
        if ($this->_page == null) {
            $this->_page = $result;
        } else {
            $this->_page->device_tokens = array_merge($this->_page->device_tokens, $result->device_tokens);
            $this->_page->next_page = $result->next_page;
        }
    }

    // Countable interface
    public function count() {
        return $this->_page->device_tokens_count;
    }

    // Iterator interface
    function rewind() {
        $this->_position = 0;
    }

    function current() {
        return $this->_page->device_tokens[$this->_position];
    }

    function key() {
        return $this->_position;
    }

    function next() {
        ++$this->_position;
    }

    function valid() {
        if (!isset($this->_page->device_tokens[$this->_position])) {
            $next_page =  isset($this->_page->next_page) ? $this->_page->next_page : null;
            if ($next_page == null) {
                return false;
            } else {
                $this->_load_page($next_page);
                return $this->valid();
            }
        }
        return true;
    }
}

?>