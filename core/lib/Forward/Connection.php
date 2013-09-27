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
		 * Socket stream
		 * @var resource
		 */
		private $stream;

		/**
		 * Construct a connection
		 *
		 * @param  string $host
		 * @param  string $port
		 */
		public function __construct($host, $port)
		{
			$this->stream = @\stream_socket_client(
				"tcp://{$host}:{$port}",
				$error,
				$error_msg,
				5
				// TODO: TLS
			);
			if (!$this->stream)
			{
				throw new NetworkException("Unable to connect to {$host}:{$port} (Error:{$error} {$error_msg})");
			}
		}

		/**
		 * Call a server method
		 *
		 * @param  string $method
		 * @param  array $args
		 * @return mixed
		 */
		public function remote($method, $args = array())
		{
			if (!$this->stream)
			{
				throw new NetworkException("Unable to execute '{$method}' (Error: Connection closed)");
			}

			$this->remote_request($this->stream, $method, $args);

			return $this->remote_response($this->stream);
		}

		/**
		 * Write a server request
		 *
		 * @param  resource $stream
		 * @param  string $method
		 * @param  array $args
		 * @return void
		 */
		private function remote_request($stream, $method, $args)
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
		private function remote_response($stream)
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
		}
	}
}