<?php

/**
 * Base class for Azure collectors
 *
 */
class AzureJsonCollector extends JsonCollector {
	// Defaults to handle the authentication query
	const DEFAULT_MICROSOFT_LOGIN_URL = 'https://login.microsoftonline.com/';
	const DEFAULT_MICROSOFT_AUTH_MODE = '/oauth2/token';
	const DEFAULT_MICROSOFT_RESOURCE = 'https://management.azure.com/';

	// Parameters of the file where the token is stored
	const BEARER_TOKEN_FILE_NAME = 'BearerToken.csv';
	const BEARER_TOKEN_NAME = 'TokenName';
	const BEARER_TOKEN_REQUEST_TIME = 'TokenRequestTime';
	const BEARER_TOKEN_EXPIRATION_DELAY_NAME = 'TokenExpirationDelay';
	const BEARER_EXPIRATION_GRACE_PERIOD = 5;

	private $sLoginUrl;
	private $sAuthMode;
	protected $sResource;
	private $sClientId;
	private $sClientSecret;
	private $sTenantId;
	private $bIsAuthenticated = false;
	protected $sBearerToken = '';
	private $sBearerTokenRequestTime;
	private $sBearerTokenExpirationDelay;
	protected $aParamsSourceJson = [];
	protected $sAzureClass = '';
	protected $sApiVersion = '';
	protected $sJsonFile = '';
	protected $aFieldsPos = [];
	protected $oAzureCollectionPlan;

	public function __construct() {
		parent::__construct();

		$this->sLoginUrl = Utils::GetConfigurationValue('microsoft_login_url', self::DEFAULT_MICROSOFT_LOGIN_URL);
		$this->sAuthMode = Utils::GetConfigurationValue('microsoft_auth_mode', self::DEFAULT_MICROSOFT_AUTH_MODE);
		$this->sResource = Utils::GetConfigurationValue('microsoft_resource', self::DEFAULT_MICROSOFT_RESOURCE);
		$this->sClientId = Utils::GetConfigurationValue('azure_clientid', '');
		$this->sClientSecret = Utils::GetConfigurationValue('azure_clientsecret', '');
		$this->sTenantId = Utils::GetConfigurationValue('azure_tenantid', '');

		$this->aParamsSourceJson = Utils::GetConfigurationValue(strtolower(get_class($this)), array());
		if (isset($this->aParamsSourceJson['azure_class'])) {
			$this->sAzureClass = $this->aParamsSourceJson['azure_class'];
		}
		if (isset($this->aParamsSourceJson['azure_api_version'])) {
			$this->sApiVersion = $this->aParamsSourceJson['azure_api_version'];
		}
		if (isset($this->aParamsSourceJson['jsonfile'])) {
			$this->sJsonFile = $this->aParamsSourceJson['jsonfile'];
		}

		$this->oAzureCollectionPlan = AzureCollectionPlan::GetPlan();
	}

	/**
	 * Read authentication parameters stored in file
	 *
	 * @return boolean
	 * @throws \Exception
	 */
	private function ReadAuthParamsFromFile(): bool {
		$bStatus = false;
		$sTokenFile = Utils::GetDataFilePath(self::BEARER_TOKEN_FILE_NAME);

		if (!file_exists($sTokenFile)) {
			Utils::Log(LOG_DEBUG, 'File '.$sTokenFile.' doesn\'t exist');
		} else {
			$hCSV = fopen($sTokenFile, 'r');
			if ($hCSV === false) {
				Utils::Log(LOG_ERR, "Failed to open '$sTokenFile' for reading...");
			} else {
				while (($aData = fgetcsv($hCSV, 0, $this->sSeparator)) !== false) {
					//process
					switch ($aData[0]) {
						case self::BEARER_TOKEN_NAME:
							$this->sBearerToken = $aData[1];
							break;

						case self::BEARER_TOKEN_REQUEST_TIME:
							$this->sBearerTokenRequestTime = $aData[1];
							break;

						case self::BEARER_TOKEN_EXPIRATION_DELAY_NAME:
							$this->sBearerTokenExpirationDelay = $aData[1];
							break;

						default:
							break;
					}
				}
				$bStatus = true;
				Utils::Log(LOG_DEBUG, 'File '.$sTokenFile.' has been read');
			}
			fclose($hCSV);
		}

		return $bStatus;
	}

	/**
	 * Tells if authentication is already done
	 *
	 * @return boolean
	 * @throws \Exception
	 */
	private function IsAuthenticated(): bool {
		if (!$this->bIsAuthenticated) {
			// Read stored parameters
			if ($this->ReadAuthParamsFromFile()) {
				if ($this->sBearerToken == '') {
					Utils::Log(LOG_WARNING, "No Bearer Token found in file.");
				} else {
					// Check expiration date is not over
					$sExpirationTime = $this->sBearerTokenRequestTime + $this->sBearerTokenExpirationDelay;
					if ($sExpirationTime <= time()) {
						Utils::Log(LOG_INFO, "Bearer Token has expired.");
					} elseif ($sExpirationTime <= (time() - self::BEARER_EXPIRATION_GRACE_PERIOD)) {
						Utils::Log(LOG_INFO, "Bearer Token is about to expire.");
					} else {
						$this->bIsAuthenticated = true;
					}
				}
			}
		}

		if ($this->bIsAuthenticated) {
			Utils::Log(LOG_INFO, 'Collector is already authenticated.');
		} else {
			Utils::Log(LOG_INFO, 'Collector is not authenticated yet or needs to re-authenticate !');
		}

		return $this->bIsAuthenticated;
	}

	/**
	 * Perform the authentication to Azure. A token is expected in return.
	 *
	 * @return boolean
	 * @throws \Exception
	 */
	private function Authenticate(): bool {
		// Check we are notalready authenticated, first
		if ($this->IsAuthenticated()) {
			return true;
		}

		Utils::Log(LOG_INFO, "Start authentication.");

		$sURL = $this->sLoginUrl.$this->sTenantId.$this->sAuthMode;
		$aData = [
			'grant_type' => "client_credentials",
			'client_id' => $this->sClientId,
			'client_secret' => $this->sClientSecret,
			'resource' => $this->sResource,
		];
		$aEmpty = [];

		try {
			$sResponse = utils::DoPostRequest($sURL, $aData, null, $aEmpty, $aEmpty);
			$aResults = json_decode($sResponse, true);
		} catch (Exception $e) {
			Utils::Log(LOG_ERR, "Authentication failed: ".$e->getMessage());

			return false;
		}

		$this->sBearerToken = $aResults['access_token'];
		$this->sBearerTokenExpirationDelay = $aResults['expires_in'];

		// Remove token in file
		$sTokenFile = Utils::GetDataFilePath(self::BEARER_TOKEN_FILE_NAME);
		if (file_exists($sTokenFile)) {
			$bResult = @unlink($sTokenFile);
			Utils::Log(LOG_DEBUG, "Erasing previous token file. unlink('$sTokenFile') returned ".($bResult ? 'true' : 'false'));
		}

		// Store token in file
		$hCSV = fopen($sTokenFile, 'w');
		if ($hCSV === false) {
			Utils::Log(LOG_ERR, "Failed to open '$sTokenFile' for writing !");
		} else {
			$aData = [
				array(self::BEARER_TOKEN_NAME, $this->sBearerToken),
				array(self::BEARER_TOKEN_REQUEST_TIME, time()),
				array(self::BEARER_TOKEN_EXPIRATION_DELAY_NAME, $this->sBearerTokenExpirationDelay),
			];
			foreach ($aData as $aValue) {
				fputcsv($hCSV, $aValue, $this->sSeparator);
			}
			fclose($hCSV);

			$this->bIsAuthenticated = true;
		}

		Utils::Log(LOG_INFO, "Authentication succeeded !");

		return true;
	}

	/**
	 * Tells if resource groups are necessary to collect the class
	 *
	 * @return bool
	 */
	public static function NeedsResourceGroupsForCollector(): bool {
		return false;
	}

	/**
	 *  Retrieve data from Azure for the class that implements the method and store them in given file
	 *
	 * @return bool
	 */
	protected function RetrieveDataFromAzure(): bool {
		return true;
	}

	/**
	 * Store JSON encoded data in  file
	 *
	 * @param $sData
	 *
	 * @return bool
	 * @throws \Exception
	 */
	protected function StoreJsonDataInFile($sData): bool {
		$hJSON = file_put_contents($this->sJsonFile, $sData);
		if ($hJSON === false) {
			Utils::Log(LOG_ERR, "Failed to write retrieved data in '$this->sJsonFile' !");

			return false;
		}

		return true;
	}

	/**
	 * Runs the configured query to start fetching the data from the database
	 * Store result in fson data file
	 * Pass file to parent JsonCollector class to translate it into a csv file
	 *
	 * @see jsonCollector::Prepare()
	 */
	public function Prepare(): bool {
		Utils::Log(LOG_DEBUG, '----------------');

		// Check Azure class is set
		if ($this->sAzureClass == '') {
			Utils::Log(LOG_ERR, 'Parameter "azure_class" is not defined within the current collector parameters!');

			return false;
		}

		// Make sure we are authenticated
		if (!$this->Authenticate()) {
			Utils::Log(LOG_ERR, 'Collect of '.$this->sAzureClass.' is not possible: collector cannot authenticate!');

			return false;
		}

		// Check JSON file name where tor store collection exists
		if ($this->sJsonFile == '') {
			Utils::Log(LOG_ERR, "No file path where to store the retrieved data has been defined!");

			return false;
		}

		// Retrieve data from Azure
		Utils::Log(LOG_DEBUG, 'Retrieve '.$this->sAzureClass.' data from Azure');
		if (!$this->RetrieveDataFromAzure()) {
			Utils::Log(LOG_DEBUG, 'Retrieval failed !');
		}

		return parent::Prepare();
	}

	/**
	 * Initializes the mapping between the column names (given by the first line of the CSV) and their index, for the given columns
	 *
	 * @param hash $aLineHeaders An array of strings (the "headers" i.e. first line of the CSV file)
	 * @param array $aFields The fields for which a mapping is requested, as an array of strings
	 */
	protected function InitLineMappings($aLineHeaders, $aFields) {
		foreach ($aLineHeaders as $idx => $sHeader) {
			if (in_array($sHeader, $aFields)) {
				$this->aFieldsPos[$sHeader] = $idx;
			}
		}

		// Check that all requested fields were found in the headers
		foreach ($aFields as $sField) {
			if (!array_key_exists($sField, $this->aFieldsPos)) {
				Utils::Log(LOG_ERR, "'$sField' is not a valid column name in the CSV file. Mapping will fail.");
			}
		}
	}

	/**
	 * Compute the lookup
	 *
	 * @param $aLookupKey
	 * @param $sDestField
	 *
	 * @return array
	 */
	protected function DoLookup($aLookupKey, $sDestField): array {
		return [false, ''];
	}

	/**
	 * Replaces a given field in the CSV data by the content of given lookup fields
	 *
	 * @param $aLineData
	 * @param $aLookupFields
	 * @param $sDestField
	 * @param $iLineIndex
	 * @param $bIgnoreMappingErrors
	 *
	 * @return bool
	 * @throws \Exception
	 */
	protected function Lookup(&$aLineData, $aLookupFields, $sDestField, $iLineIndex, $bIgnoreMappingErrors): bool {
		$bRet = true;
		if ($iLineIndex == 0) {
			$this->InitLineMappings($aLineData, array_merge($aLookupFields, array($sDestField)));
		} else {
			$aLookupKey = array();
			foreach ($aLookupFields as $sField) {
				$iPos = $this->aFieldsPos[$sField];
				if ($iPos !== null) {
					$aLookupKey[$sField] = $aLineData[$iPos];
				} else {
					$aLookupKey[$sField] = ''; // missing column ??
				}
			}
			list($bResult, $sField) = $this->DoLookup($aLookupKey, $sDestField);
			if (!$bResult) {
				if ($bIgnoreMappingErrors) {
					// Mapping *errors* are expected, just report them in debug mode
					Utils::Log(LOG_DEBUG, "No mapping found for attribute '$sDestField' which will be set to zero.");
				} else {
					Utils::Log(LOG_WARNING, "No mapping found for attribute '$sDestField' which will be set to zero.");
				}
				$bRet = false;
			} else {
				$iPos = $this->aFieldsPos[$sDestField];
				if ($iPos !== null) {
					$aLineData[$iPos] = $sField;
				} else {
					Utils::Log(LOG_WARNING, "'$sDestField' is not a valid column name in the CSV file. Mapping will be ignored.");
				}
			}
		}

		return $bRet;
	}


}
