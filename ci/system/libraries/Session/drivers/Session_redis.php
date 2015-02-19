<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class CI_Session_redis extends CI_Session_driver
{
//region session cookie config
    /**
     * Length of time (in seconds) for sessions to expire
     *
     * @var int
     */
    public $sess_expiration = 7200;

    /**
     * Whether to kill session on close of browser window
     *
     * @var bool
     */
    public $sess_expire_on_close = FALSE;

    /**
     * Whether to match session on ip address
     *
     * @var bool
     */
    public $sess_match_ip = FALSE;

    /**
     * Whether to match session on user-agent
     *
     * @var bool
     */
    public $sess_match_useragent = TRUE;

    /**
     * Name of session cookie
     *
     * @var string
     */
    public $sess_cookie_name = 'ci_session_id';

    /**
     * Session cookie prefix
     *
     * @var string
     */
    public $cookie_prefix = '';

    /**
     * Session cookie path
     *
     * @var string
     */
    public $cookie_path = '';

    /**
     * Session cookie domain
     *
     * @var string
     */
    public $cookie_domain = '';

    /**
     * Whether to set the cookie only on HTTPS connections
     *
     * @var bool
     */
    public $cookie_secure = FALSE;

    /**
     * Whether cookie should be allowed only to be sent by the server
     *
     * @var bool
     */
    public $cookie_httponly = FALSE;

    /**
     * Interval at which to update session
     *
     * @var int
     */
    public $sess_time_to_update = 300;

    /**
     * Key with which to encrypt the session cookie
     *
     * @var string
     */
    public $encryption_key = '';

    /**
     * Timezone to use for the current time
     *
     * @var string
     */
    public $time_reference = 'local';

    /**
     * Session data
     *
     * @var array
     */
    public $userdata = array();

    /**
     * Current time
     *
     * @var int
     */
    public $now;
//endregion

//region redis config
    /**
     * Default config for redis
     *
     * @static
     * @var    array
     */
    protected static $_default_config = array(
        'socket_type' => 'tcp',
        'host' => '127.0.0.1',
        'password' => NULL,
        'port' => 6379,
        'timeout' => 0
    );

    /**
     * Redis connection
     *
     * @var    Redis
     */
    protected $_redis;

    private function _init_redis()
    {
        if (extension_loaded('redis')) {
            // connect to redis
            $config = array();
            $CI =& get_instance();

            if ($CI->config->load('redis', TRUE, TRUE)) {
                $config += $CI->config->item('redis');
            }

            $config = array_merge(self::$_default_config, $config);

            $this->_redis = new Redis();

            try {
                if ($config['socket_type'] === 'unix') {
                    $success = $this->_redis->connect($config['socket']);
                } else // tcp socket
                {
                    $success = $this->_redis->connect($config['host'], $config['port'], $config['timeout']);
                }

                if (!$success) {
                    log_message('debug', 'Cache: Redis connection refused. Check the config.');
                    return FALSE;
                }
            } catch (RedisException $e) {
                log_message('debug', 'Cache: Redis connection refused (' . $e->getMessage() . ')');
                return FALSE;
            }

            if (isset($config['password'])) {
                $this->_redis->auth($config['password']);
            }

            // db o is for session
            $this->_redis->select(0);
            return TRUE;
        } else {
            //TODO:throw error when not production
            // use native when it is production...
            log_message('debug', 'The Redis extension must be loaded to use Redis cache.');
            return FALSE;
        }

    }

//endregion

    public $sess_redis_json = FALSE; // store data as json in redis

    protected function initialize()
    {
        //TODO:throw error when can't init redis
        $this->_init_redis();

        $prefs = array(
            'sess_cookie_name',
            'sess_expire_on_close',
            'sess_expiration',
            'sess_match_ip',
            'sess_match_useragent',
            'sess_time_to_update',
            'cookie_prefix',
            'cookie_path',
            'cookie_domain',
            'cookie_secure',
            'cookie_httponly',
            'sess_redis_json'
        );
        foreach ($prefs as $key) {
            $this->$key = isset($this->_parent->params[$key])
                ? $this->_parent->params[$key]
                : $this->CI->config->item($key);
        }

        // set it to time() for simple
        $this->now = time();

        // Set the session length. If the session expiration is
        // set to zero we'll set the expiration two years from now.
        if ($this->sess_expiration === 0) {
            $this->sess_expiration = (60 * 60 * 24 * 365 * 2);
        }

        // Set the cookie name
        $this->sess_cookie_name = $this->cookie_prefix . $this->sess_cookie_name;

        // Run the Session routine. If a session doesn't exist we'll
        // create a new one. If it does, we'll update it if possible.
        if (!$this->_sess_read()) {
            log_message('error', 'Session: cant read session');
            $this->_sess_create();
        } else {
            $this->_sess_update(); //TODO:why we have to do that? for secure?
        }

    }

    public function sess_save()
    {
        log_message('error', 'Session: sess_save set redis');
        $this->_set_redis();
    }

    public function sess_destroy()
    {
        // Kill the redis key .... (why we have to say kill, it is so violent)
        $session_id = $this->CI->input->cookie($this->sess_cookie_name);
        if ($session_id) {
            $key = $this->sess_cookie_name . ':' . $session_id;
            $this->_redis->del($key);
        }


        // Kill the cookie
        setcookie($this->sess_cookie_name, '', ($this->now - 31500000),
            $this->cookie_path, $this->cookie_domain, 0);

        // Kill session data
        $this->userdata = array();
    }

    public function sess_regenerate($destroy = FALSE)
    {
        // Check destroy flag
        if ($destroy) {
            // Destroy old session and create new one
            $this->sess_destroy();
            $this->_sess_create();
        } else {
            // Just force an update to recreate the id
            $this->_sess_update(TRUE);
        }
    }

    public function &get_userdata()
    {
        return $this->userdata;
    }

    protected function _sess_read()
    {
        $session_id = $this->CI->input->cookie($this->sess_cookie_name);
        if (!$session_id) {
            log_message('error', 'Session: session id is not set in cookie');
            return FALSE;
        }

        //check if this session_id exists in redis
        //TODO:here is the problem, can't get from redis
        $key = $this->sess_cookie_name . ':' . $session_id;
        $session = $this->_redis->get($key);
        if (!$session) {
            log_message('error', 'Session: cant get from redis' . $key);
            return FALSE;
        }

        //TODO:the hmac auth?? (also need to do in _sess_create)

        if ($this->sess_redis_json) {
            //TODO:use @ seems to be low efficient?
            $session = json_decode($session, TRUE); //!!!!must by array!
        } else {
            $session = unserialize($session);
        }


        // do the ip,usr-agent check etc
        // Is the session data we unserialized an array with the correct format?
        if (!is_array($session) OR !isset($session['session_id'], $session['ip_address'], $session['user_agent'], $session['last_activity'])) {
            log_message('error', 'Session: Wrong cookie data format');
            $this->sess_destroy();
            return FALSE;
        }

        // Is the session current?
        if (($session['last_activity'] + $this->sess_expiration) < $this->now OR $session['last_activity'] > $this->now) {
            log_message('error', 'Session: Expired');
            $this->sess_destroy();
            return FALSE;
        }

        // Does the IP match?
        if ($this->sess_match_ip === TRUE && $session['ip_address'] !== $this->CI->input->ip_address()) {
            log_message('error', 'Session: IP address mismatch');
            $this->sess_destroy();
            return FALSE;
        }

        // Does the User Agent Match?
        if ($this->sess_match_useragent === TRUE &&
            trim($session['user_agent']) !== trim(substr($this->CI->input->user_agent(), 0, 120))
        ) {
            log_message('error', 'Session: User Agent string mismatch');
            $this->sess_destroy();
            return FALSE;
        }

        // Session is valid!
        $this->userdata = $session;
        return TRUE;
    }

    protected function _sess_create()
    {
        // Initialize userdata
        $this->userdata = array(
            'session_id' => $this->_make_sess_id(),
            'ip_address' => $this->CI->input->ip_address(),
            'user_agent' => trim(substr($this->CI->input->user_agent(), 0, 120)),
            'last_activity' => $this->now,
        );
        log_message('error', 'Session: _sess_create set to redis');
        $this->_set_redis();
    }

    protected function _sess_update($force = FALSE)
    {
        // We only update the session every five minutes by default (unless forced)
        if (!$force && ($this->userdata['last_activity'] + $this->sess_time_to_update) >= $this->now) {
            return;
        }

        // Update last activity to now
        $this->userdata['last_activity'] = $this->now;

        // Changing the session ID during an AJAX call causes problems
        // TODO:for Angular js, you have to add XMLHttpRequest, and for flash like uploadify
        // the better way is to use html5 upload, jquery-file-upload or webuploader(baidu)
        // $httpProvider.defaults.headers.post['X-Requested-With'] = "XMLHttpRequest";
        if (!$this->CI->input->is_ajax_request()) {
            // Get new id
            $this->userdata['session_id'] = $this->_make_sess_id();
            log_message('error', 'Session: Regenerate ID');
        }

        // Write to redis
        $this->_set_redis();
    }

    private function _set_redis()
    {
        $session_id = $this->userdata['session_id'];

        //TODO:add the HMAC auth .... how do php avoid this?
        $expire = ($this->sess_expire_on_close === TRUE) ? 0 : $this->sess_expiration + time();

        // Set the cookie, it only holds the session_id
        setcookie($this->sess_cookie_name, $session_id, $expire, $this->cookie_path, $this->cookie_domain,
            $this->cookie_secure, $this->cookie_httponly);


        // store the value to redis
        $key = $this->sess_cookie_name . ':' . $session_id;
        $ttl = ($this->sess_expire_on_close === TRUE) ? 0 : $this->sess_expiration;
        //TODO:how to achieve the falsh data in redis... if ttl is 0?
        log_message('error', 'Session: set redis key ' . $key);
        if ($this->sess_redis_json) {
            $this->_redis->setex($key, $ttl, json_encode($this->userdata));
        } else {
            $this->_redis->setex($key, $ttl, serialize($this->userdata));
        }

    }


    //copied from session_cookie
    private function _make_sess_id()
    {
        $new_sessid = '';
        do {
            $new_sessid .= mt_rand();
        } while (strlen($new_sessid) < 32);

        // To make the session ID even more secure we'll combine it with the user's IP
        $new_sessid .= $this->CI->input->ip_address();

        // Turn it into a hash and return
        // TODO:why we can't just use uniqid?
        return md5(uniqid($new_sessid, TRUE));
    }
}