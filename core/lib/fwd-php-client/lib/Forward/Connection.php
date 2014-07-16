<?php

namespace Forward
{
    /**
     * Base class for exceptions
     */
    class ConnectionException extends \Exception {}

    /**
     * Thrown on network errors
     */
    class NetworkException extends ConnectionException {}

    /**
     * Thrown on remote protocol errors
     */
    class ProtocolException extends ConnectionException {}

    /**
     * Thrown on server errors
     */
    class ServerException extends ConnectionException {}

    /**
     * Connection class
     * Implements Forward API connection protocol
     */
    class Connection
    {
        /**
         * Connection status
         * @var bool
         */
        public $connected;

        /**
         * Connection host
         * @var string
         */
        public $host;

        /**
         * Connection port
         * @var int
         */
        public $port;

        /**
         * Connection options
         * @var bool
         */
        public $options;

        /**
         * Socket stream
         * @var resource
         */
        protected $stream;

        /**
         * Last request identifier
         * @var int
         */
        protected $last_request_id;

        /**
         * Construct a connection
         *
         * @param  string $host
         * @param  string $port
         */
        public function __construct($host, $port, $options = null)
        {
            $this->host = $host;
            $this->port = $port;
            $this->options = $options ?: array();
            $this->connected = false;
        }

        /**
         * Activate the connection
         *
         * @return void
         */
        public function connect()
        {
            if ($this->options['clear']) {
                $this->stream = stream_socket_client(
                    "tcp://{$this->host}:{$this->port}", $error, $error_msg, 10
                );
            } else {
                $context = stream_context_create(array(
                    'ssl' => array(
                        'verify_peer' => $this->options['verify_cert'] ? true : false,
                        'allow_self_signed' => $this->options['verify_cert'] ? false : true,
                        'verify_depth' => 5,
                        'SNI_enabled' => true
                    )
                ));
                $this->stream = stream_socket_client(
                    "tls://{$this->host}:{$this->port}", $error, $error_msg, 10,
                    STREAM_CLIENT_CONNECT, $context
                );
            }
            if ($this->stream) {
                $this->connected = true;
            } else {
                $error_msg = $error_msg ?: 'Peer certificate rejected';
                throw new NetworkException(
                    "Unable to connect to {$this->host}:{$this->port} "
                    ."(Error:{$error} {$error_msg})"
                );
            }
        }

        /**
         * Request a server method
         *
         * @param  string $method
         * @param  array $args
         * @return mixed
         */
        public function request($method, $args = array())
        {
            if (!$this->stream) {
                throw new NetworkException("Unable to execute '{$method}' (Error: Connection closed)");
            }

            $this->request_write($this->stream, $method, $args);

            return $this->request_response($this->stream);
        }

        /**
         * Write a server request
         *
         * @param  resource $stream
         * @param  string $method
         * @param  array $args
         * @return void
         */
        private function request_write($stream, $method, $args)
        {
            $req_id = $this->request_id(array($method, $args));
            $request = array($method, $args, $req_id);
            fwrite($stream, json_encode($request)."\n");
        }

        /**
         * Get a server response
         *
         * @param  resource $stream
         * @return mixed
         */
        private function request_response($stream)
        {
            // Block until server responds
            if (false === ($response = fgets($stream))) {
                $this->close();
                throw new ProtocolException("Unable to read response from server");
            }

            if (null === ($message = json_decode(trim($response), true))) {
                throw new ProtocolException("Unable to parse response from server ({$response})");
            } else if (!is_array($message) || !is_array($message[0])) {
                throw new ProtocolException("Invalid response from server (".json_encode($message).")");
            }

            $data = $message[0];
            $id = $message[1];

            if ($data['$error']) {
                throw new ServerException((string)$data['$error']);
            }

            return $data;
        }

        /**
         * Get or create a unique request identifier
         *
         * @param  string $method
         * @param  string $url
         * @param  array $data
         * @return mixed
         */
        function request_id($set_params = null)
        {
            if ($set_params !== null) {
                $hash_id = openssl_random_pseudo_bytes(20);
                $this->last_request_id = md5(
                    serialize(array($hash_id, $set_params))
                );
            }
            return $this->last_request_id;
        }

        /**
         * Close connection stream
         *
         * @return void
         */
        public function close()
        {
            fclose($this->stream);
            $this->stream = null;
            $this->connected = false;
        }
    }
}