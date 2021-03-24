<?php
/**
 * @file
 * SharedConfig class definition.
 *
 * LICENSE
 *
 * This source file is subject to the new BSD license that is bundled
 * with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://code.google.com/p/apns-php/wiki/License
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to aldo.armiento@gmail.com so we can send you a copy immediately.
 *
 * @author (C) 2010 Aldo Armiento (aldo.armiento@gmail.com)
 * @version $Id$
 */

/**
 * @mainpage
 *
 * @li ApnsPHP on GitHub: https://github.com/immobiliare/ApnsPHP
 */

/**
 * @defgroup ApplePushNotificationService ApnsPHP
 */

namespace ApnsPHP;

use DateTimeImmutable;
use ApnsPHP\Log\EmbeddedLogger;
use Psr\Log\LoggerInterface;
use Lcobucci\JWT\Signer\Key;
use Lcobucci\JWT\Signer\Ecdsa\Sha256;
use Lcobucci\JWT\Configuration;

/**
 * Abstract class: this is the superclass for all Apple Push Notification Service
 * classes.
 *
 * This class is responsible for the connection to the Apple Push Notification Service
 * and Feedback.
 *
 * @ingroup ApplePushNotificationService
 * @see http://tinyurl.com/ApplePushNotificationService
 */
abstract class SharedConfig
{
	const ENVIRONMENT_PRODUCTION = 0; /**< @type integer Production environment. */
	const ENVIRONMENT_SANDBOX = 1; /**< @type integer Sandbox environment. */

	const PROTOCOL_BINARY = 0; /**< @type integer Binary Provider API. */
	const PROTOCOL_HTTP   = 1; /**< @type integer APNs Provider API. */

	const DEVICE_BINARY_SIZE = 32; /**< @type integer Device token length. */

	const WRITE_INTERVAL = 10000; /**< @type integer Default write interval in micro seconds. */
	const CONNECT_RETRY_INTERVAL = 1000000; /**< @type integer Default connect retry interval in micro seconds. */
	const SOCKET_SELECT_TIMEOUT = 1000000; /**< @type integer Default socket select timeout in micro seconds. */

	protected $_aServiceURLs = array(); /**< @type array Container for service URLs environments. */
	protected $_aHTTPServiceURLs = array(); /**< @type array Container for HTTP/2 service URLs environments. */

	protected $_nEnvironment; /**< @type integer Active environment. */
	protected $_nProtocol; /**< @type integer Active protocol. */

	protected $_nConnectTimeout; /**< @type integer Connect timeout in seconds. */
	protected $_nConnectRetryTimes = 3; /**< @type integer Connect retry times. */

	protected $_sProviderCertificateFile; /**< @type string Provider certificate file with key (Bundled PEM). */
	protected $_sProviderCertificatePassphrase; /**< @type string Provider certificate passphrase. */
	protected $_sProviderToken; /**< @type string|null Provider Authentication token. */
	protected $_sProviderTeamId; /**< @type string|null Apple Team Identifier. */
	protected $_sProviderKeyId; /**< @type string|null Apple Key Identifier. */
	protected $_sRootCertificationAuthorityFile; /**< @type string Root certification authority file. */

	protected $_nWriteInterval; /**< @type integer Write interval in micro seconds. */
	protected $_nConnectRetryInterval; /**< @type integer Connect retry interval in micro seconds. */
	protected $_nSocketSelectTimeout; /**< @type integer Socket select timeout in micro seconds. */

	protected $_logger; /**< @type Psr\Log\LoggerInterface Logger. */

	protected $_hSocket; /**< @type resource SSL Socket. */

	/**
	 * Constructor.
	 *
	 * @param  $nEnvironment @type integer Environment.
	 * @param  $sProviderCertificateFile @type string Provider certificate file
	 *         with key (Bundled PEM).
	 * @param  $nProtocol @type integer Protocol.
	 * @throws BaseException if the environment is not
	 *         sandbox or production or the provider certificate file is not readable.
	 */
	public function __construct($nEnvironment, $sProviderCertificateFile, $nProtocol = self::PROTOCOL_BINARY)
	{
		if ($nEnvironment != self::ENVIRONMENT_PRODUCTION && $nEnvironment != self::ENVIRONMENT_SANDBOX) {
			throw new BaseException(
				"Invalid environment '{$nEnvironment}'"
			);
		}
		$this->_nEnvironment = $nEnvironment;

		if (!is_readable($sProviderCertificateFile)) {
			throw new BaseException(
				"Unable to read certificate file '{$sProviderCertificateFile}'"
			);
		}
		$this->_sProviderCertificateFile = $sProviderCertificateFile;

		if ($nProtocol != self::PROTOCOL_BINARY && $nProtocol != self::PROTOCOL_HTTP) {
			throw new BaseException(
				"Invalid protocol '{$nProtocol}'"
			);
		}
		$this->_nProtocol = $nProtocol;
		
		$this->_nConnectTimeout = ini_get("default_socket_timeout");
		$this->_nWriteInterval = self::WRITE_INTERVAL;
		$this->_nConnectRetryInterval = self::CONNECT_RETRY_INTERVAL;
		$this->_nSocketSelectTimeout = self::SOCKET_SELECT_TIMEOUT;
	}

	/**
	 * Set the Logger instance to use for logging purpose.
	 *
	 * The default logger is ApnsPHP_Log_Embedded, an instance
	 * of LoggerInterface that simply print to standard
	 * output log messages.
	 *
	 * To set a custom logger you have to implement LoggerInterface
	 * and use setLogger, otherwise standard logger will be used.
	 *
	 * @param  $logger @type LoggerInterface Logger instance.
	 * @throws BaseException if Logger is not an instance
	 *         of LoggerInterface.
     * @see Psr\Log\LoggerInterface
	 * @see EmbeddedLogger
	 *
	 */
	public function setLogger(LoggerInterface $logger)
	{
		if (!is_object($logger)) {
			throw new BaseException(
				"The logger should be an instance of 'Psr\Log\LoggerInterface'"
			);
		}
		if (!($logger instanceof LoggerInterface)) {
			throw new BaseException(
				"Unable to use an instance of '" . get_class($logger) . "' as logger: " .
				"a logger must implements 'Psr\Log\LoggerInterface'."
			);
		}
		$this->_logger = $logger;
	}

	/**
	 * Get the Logger instance.
	 *
	 * @return @type Psr\Log\LoggerInterface Current Logger instance.
	 */
	public function getLogger()
	{
		return $this->_logger;
	}

	/**
	 * Set the Provider Certificate passphrase.
	 *
	 * @param  $sProviderCertificatePassphrase @type string Provider Certificate
	 *         passphrase.
	 */
	public function setProviderCertificatePassphrase($sProviderCertificatePassphrase)
	{
		$this->_sProviderCertificatePassphrase = $sProviderCertificatePassphrase;
	}

	/**
	 * Set the Team Identifier.
	 *
	 * @param  string $sTeamId Apple Team Identifier.
	 */
	public function setTeamId($sTeamId)
	{
		$this->_sProviderTeamId = $sTeamId;
	}

	/**
	 * Set the Key Identifier.
	 *
	 * @param  string $sKeyId Apple Key Identifier.
	 */
	public function setKeyId($sKeyId)
	{
		$this->_sProviderKeyId = $sKeyId;
	}

	/**
	 * Set the Root Certification Authority file.
	 *
	 * Setting the Root Certification Authority file automatically set peer verification
	 * on connect.
	 *
	 * @see http://tinyurl.com/GeneralProviderRequirements
	 * @see http://www.entrust.net/
	 * @see https://www.entrust.net/downloads/root_index.cfm
	 *
	 * @param  $sRootCertificationAuthorityFile @type string Root Certification
	 *         Authority file.
	 * @throws BaseException if Root Certification Authority
	 *         file is not readable.
	 */
	public function setRootCertificationAuthority($sRootCertificationAuthorityFile)
	{
		if (!is_readable($sRootCertificationAuthorityFile)) {
			throw new BaseException(
				"Unable to read Certificate Authority file '{$sRootCertificationAuthorityFile}'"
			);
		}
		$this->_sRootCertificationAuthorityFile = $sRootCertificationAuthorityFile;
	}

	/**
	 * Get the Root Certification Authority file path.
	 *
	 * @return @type string Current Root Certification Authority file path.
	 */
	public function getCertificateAuthority()
	{
		return $this->_sRootCertificationAuthorityFile;
	}

	/**
	 * Set the write interval.
	 *
	 * After each socket write operation we are sleeping for this
	 * time interval. To speed up the sending operations, use Zero
	 * as parameter but some messages may be lost.
	 *
	 * @param  $nWriteInterval @type integer Write interval in micro seconds.
	 */
	public function setWriteInterval($nWriteInterval)
	{
		$this->_nWriteInterval = (int)$nWriteInterval;
	}

	/**
	 * Get the write interval.
	 *
	 * @return @type integer Write interval in micro seconds.
	 */
	public function getWriteInterval()
	{
		return $this->_nWriteInterval;
	}

	/**
	 * Set the connection timeout.
	 *
	 * The default connection timeout is the PHP internal value "default_socket_timeout".
	 * @see http://php.net/manual/en/filesystem.configuration.php
	 *
	 * @param  $nTimeout @type integer Connection timeout in seconds.
	 */
	public function setConnectTimeout($nTimeout)
	{
		$this->_nConnectTimeout = (int)$nTimeout;
	}

	/**
	 * Get the connection timeout.
	 *
	 * @return @type integer Connection timeout in seconds.
	 */
	public function getConnectTimeout()
	{
		return $this->_nConnectTimeout;
	}

	/**
	 * Set the connect retry times value.
	 *
	 * If the client is unable to connect to the server retries at least for this
	 * value. The default connect retry times is 3.
	 *
	 * @param  $nRetryTimes @type integer Connect retry times.
	 */
	public function setConnectRetryTimes($nRetryTimes)
	{
		$this->_nConnectRetryTimes = (int)$nRetryTimes;
	}

	/**
	 * Get the connect retry time value.
	 *
	 * @return @type integer Connect retry times.
	 */
	public function getConnectRetryTimes()
	{
		return $this->_nConnectRetryTimes;
	}

	/**
	 * Set the connect retry interval.
	 *
	 * If the client is unable to connect to the server retries at least for ConnectRetryTimes
	 * and waits for this value between each attempts.
	 *
	 * @see setConnectRetryTimes
	 *
	 * @param  $nRetryInterval @type integer Connect retry interval in micro seconds.
	 */
	public function setConnectRetryInterval($nRetryInterval)
	{
		$this->_nConnectRetryInterval = (int)$nRetryInterval;
	}

	/**
	 * Get the connect retry interval.
	 *
	 * @return @type integer Connect retry interval in micro seconds.
	 */
	public function getConnectRetryInterval()
	{
		return $this->_nConnectRetryInterval;
	}

	/**
	 * Set the TCP socket select timeout.
	 *
	 * After writing to socket waits for at least this value for read stream to
	 * change status.
	 *
	 * In Apple Push Notification protocol there isn't a real-time
	 * feedback about the correctness of notifications pushed to the server; so after
	 * each write to server waits at least SocketSelectTimeout. If, during this
	 * time, the read stream change its status and socket received an end-of-file
	 * from the server the notification pushed to server was broken, the server
	 * has closed the connection and the client needs to reconnect.
	 *
	 * @see http://php.net/stream_select
	 *
	 * @param  $nSelectTimeout @type integer Socket select timeout in micro seconds.
	 */
	public function setSocketSelectTimeout($nSelectTimeout)
	{
		$this->_nSocketSelectTimeout = (int)$nSelectTimeout;
	}

	/**
	 * Get the TCP socket select timeout.
	 *
	 * @return @type integer Socket select timeout in micro seconds.
	 */
	public function getSocketSelectTimeout()
	{
		return $this->_nSocketSelectTimeout;
	}

	/**
	 * Connects to Apple Push Notification service server.
	 *
	 * Retries ConnectRetryTimes if unable to connect and waits setConnectRetryInterval
	 * between each attempts.
	 *
	 * @throws BaseException if is unable to connect after
	 *         ConnectRetryTimes.
	 */
	public function connect()
	{
		$bConnected = false;
		$nRetry = 0;
		while (!$bConnected) {
			try {
				$bConnected = $this->_connect();
			} catch (BaseException $e) {
				$this->_logger()->error($e->getMessage());
				if ($nRetry >= $this->_nConnectRetryTimes) {
					throw $e;
				} else {
					$this->_logger()->info(
						"Retry to connect (" . ($nRetry+1) .
						"/{$this->_nConnectRetryTimes})..."
					);
					usleep($this->_nConnectRetryInterval);
				}
			}
			$nRetry++;
		}
	}

	/**
	 * Disconnects from Apple Push Notifications service server.
	 *
	 * @return @type boolean True if successful disconnected.
	 */
	public function disconnect()
	{
		if (is_resource($this->_hSocket)) {
			$this->_logger()->info('Disconnected.');
			if ($this->_nProtocol === self::PROTOCOL_HTTP) {
				curl_close($this->_hSocket);
				return true;
			} else {
				return fclose($this->_hSocket);
			}
		}
		return false;
	}

	/**
	 * Connects to Apple Push Notification service server.
	 *
	 * @return @type boolean True if successful connected.
	 */
	protected function _connect()
	{
		return $this->_nProtocol === self::PROTOCOL_HTTP ? $this->_httpInit() : $this->_binaryConnect($this->_aServiceURLs[$this->_nEnvironment]);
	}

	/**
	 * Initializes cURL, the HTTP/2 backend used to connect to Apple Push Notification
	 * service server via HTTP/2 API protocol.
	 *
	 * @return @type boolean True if successful initialized.
	 *@throws BaseException if is unable to initialize.
	 */
	protected function _httpInit()
	{
		$this->_logger()->info("Trying to initialize HTTP/2 backend...");

		$this->_hSocket = curl_init();
		if (!$this->_hSocket) {
			throw new BaseException(
                "Unable to initialize HTTP/2 backend."
			);
		}

		if (!defined('CURL_HTTP_VERSION_2_0')) {
			define('CURL_HTTP_VERSION_2_0', 3);
		}
		$aCurlOpts = array(
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_2_0,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERAGENT => 'ApnsPHP',
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_VERBOSE => false
        );

		if (strpos($this->_sProviderCertificateFile, '.pem') !== false) {
            $this->_logger()->info("Initializing HTTP/2 backend with certificate.");
		    $aCurlOpts[CURLOPT_SSLCERT] = $this->_sProviderCertificateFile;
		    $aCurlOpts[CURLOPT_SSLCERTPASSWD] = empty($this->_sProviderCertificatePassphrase) ? null : $this->_sProviderCertificatePassphrase;
        }

        if (strpos($this->_sProviderCertificateFile, '.p8') !== false) {
            $this->_logger()->info("Initializing HTTP/2 backend with key.");
            $cKey   = new Key\LocalFileReference('file://' . $this->_sProviderCertificateFile);
            $cToken = Configuration::forUnsecuredSigner()->builder()
                                                        ->issuedBy($this->_sProviderTeamId)
                                                        ->issuedAt(new DateTimeImmutable())
                                                        ->withHeader('kid', $this->_sProviderKeyId)
                                                        ->getToken(new Sha256(), $cKey);

            $this->_sProviderToken = (string) $cToken;
        }

		if (!curl_setopt_array($this->_hSocket, $aCurlOpts)) {
			throw new BaseException(
                "Unable to initialize HTTP/2 backend."
			);
		}

		$this->_logger()->info("Initialized HTTP/2 backend.");

		return true;
	}

	/**
	 * Connects to Apple Push Notification service server via binary protocol.
	 *
	 * @return @type boolean True if successful connected.
	 */
	protected function _binaryConnect($sURL)
	{
		$this->_logger()->info("Trying {$sURL}...");
		$sURL = $this->_aServiceURLs[$this->_nEnvironment];

		$this->_logger()->info("Trying {$sURL}...");

		/**
		 * @see http://php.net/manual/en/context.ssl.php
		 */
		$streamContext = stream_context_create(array('ssl' => array(
			'verify_peer' => isset($this->_sRootCertificationAuthorityFile),
			'cafile' => $this->_sRootCertificationAuthorityFile,
			'local_cert' => $this->_sProviderCertificateFile
		)));

		if (!empty($this->_sProviderCertificatePassphrase)) {
			stream_context_set_option($streamContext, 'ssl',
				'passphrase', $this->_sProviderCertificatePassphrase);
		}

		$this->_hSocket = @stream_socket_client($sURL, $nError, $sError,
			$this->_nConnectTimeout, STREAM_CLIENT_CONNECT, $streamContext);

		if (!$this->_hSocket) {
			throw new BaseException(
                "Unable to connect to '{$sURL}': {$sError} ({$nError})"
			);
		}

		stream_set_blocking($this->_hSocket, 0);
		stream_set_write_buffer($this->_hSocket, 0);

		$this->_logger()->info("Connected to {$sURL}.");

		return true;
	}
	
	/**
	 * Return the Logger (with lazy loading)
	 */
	protected function _logger()
	{
		if (!isset($this->_logger)) {
			$this->_logger = new EmbeddedLogger();
		}

		return $this->_logger;
	}
}