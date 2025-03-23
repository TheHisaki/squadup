<?php

namespace PHPMailer\PHPMailer;

class SMTP {
    const VERSION = '6.9.1';
    const CRLF = "\r\n";
    const DEFAULT_PORT = 25;
    const MAX_LINE_LENGTH = 998;
    const DEBUG_OFF = 0;
    const DEBUG_CLIENT = 1;
    const DEBUG_SERVER = 2;
    const DEBUG_CONNECTION = 3;
    const DEBUG_LOWLEVEL = 4;
    
    protected $socket;
    protected $error = [];
    protected $helo_rply = null;
    protected $server_caps = null;
    protected $last_reply = '';
    public $do_debug = self::DEBUG_OFF;
    
    public function connect($host, $port = null, $timeout = 30) {
        $this->socket = @fsockopen(
            'tcp://' . $host,
            $port,
            $errno,
            $errstr,
            $timeout
        );
        
        if (!is_resource($this->socket)) {
            $this->error = ['error' => 'Failed to connect to server',
                           'errno' => $errno,
                           'errstr' => $errstr];
            return false;
        }
        
        stream_set_timeout($this->socket, $timeout);
        $this->last_reply = $this->getLines();
        return true;
    }
    
    public function hello($host = '') {
        if (empty($host)) {
            $host = gethostname();
        }
        return $this->sendCommand('EHLO', $host);
    }
    
    public function authenticate($username, $password) {
        if (!$this->sendCommand('AUTH LOGIN')) {
            return false;
        }
        if (!$this->sendCommand(base64_encode($username))) {
            return false;
        }
        return $this->sendCommand(base64_encode($password));
    }
    
    public function mail($from) {
        return $this->sendCommand('MAIL FROM:', '<' . $from . '>');
    }
    
    public function recipient($to) {
        return $this->sendCommand('RCPT TO:', '<' . $to . '>');
    }
    
    public function data($msg) {
        if (!$this->sendCommand('DATA')) {
            return false;
        }
        
        if (!$this->sendMessage($msg . self::CRLF . '.')) {
            return false;
        }
        
        return (substr($this->last_reply, 0, 3) === '250');
    }
    
    protected function sendCommand($cmd, $arg = '') {
        if (!empty($arg)) {
            $cmd .= ' ' . $arg;
        }
        
        fwrite($this->socket, $cmd . self::CRLF);
        
        $this->last_reply = $this->getLines();
        
        if ($this->do_debug >= self::DEBUG_SERVER) {
            echo "SERVER -> CLIENT: " . $this->last_reply . "\n";
        }
        
        return (substr($this->last_reply, 0, 3) === '250');
    }
    
    protected function sendMessage($msg) {
        fwrite($this->socket, $msg . self::CRLF);
        $this->last_reply = $this->getLines();
        return true;
    }
    
    protected function getLines() {
        $data = '';
        $endtime = time() + 5; // 5 seconds timeout
        
        while (time() < $endtime) {
            $str = @fgets($this->socket, 515);
            if ($str === false) {
                break;
            }
            $data .= $str;
            if (substr($str, 3, 1) == ' ') {
                break;
            }
        }
        
        return $data;
    }
    
    public function close() {
        $this->sendCommand('QUIT');
        fclose($this->socket);
    }
} 