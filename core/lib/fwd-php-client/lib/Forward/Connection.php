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
         * Indicates when connection is active
         * @var resource
         */
        public $connected;

        /**
         * Connection host
         * @var resource
         */
        protected $host;

        /**
         * Connection port
         * @var resource
         */
        protected $port;

        /**
         * Socket stream
         * @var resource
         */
        protected $stream;

        /**
         * Construct a connection
         *
         * @param  string $host
         * @param  string $port
         */
        public function __construct($host, $port)
        {
            $this->host = $host;
            $this->port = $port;
            $this->connected = null;
        }

        /**
         * Activate the connection
         *
         * @return void
         */
        public function connect()
        {
            $this->stream = @\stream_socket_client(
                "tcp://{$this->host}:{$this->port}",
                $error,
                $error_msg,
                5
                // TODO: TLS
            );
            if ($this->stream)
            {
                $this->connected = true;
            }
            else
            {
                throw new NetworkException("Unable to connect to {$this->host}:{$this->port} (Error:{$error} {$error_msg})");
            }
        }

        /**
         * Request a server method
         *
         * @param  string $method
         * @param  array $args
         * @return mixed
         */
        public function request($method, $args = array(), $id = null)
        {
            if (!$this->stream)
            {
                throw new NetworkException("Unable to execute '{$method}' (Error: Connection closed)");
            }

            array_push($args, $id);

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
            $callbacks = new \stdclass();
            $callbacks->{++$this->callback_number} = array(count($args));

            // Write request
            $request = array(strtolower($method), $args, $callbacks);
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
            if (false === ($response = fgets($stream)))
            {
                $this->close();
                throw new ProtocolException("Unable to read response from server");
            }

            if (null === ($message = json_decode(trim($response), true)))
            {
                throw new ProtocolException("Unable to parse response from server ({$response})");
            }
            else if (!is_array($message) || !is_array($message[1]))
            {
                throw new ProtocolException("Invalid response from server ({$message})");
            }

            if ($message[1]['$error'])
            {
                throw new ServerException($message[1]['$error']);
            }

            return $message[1];
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