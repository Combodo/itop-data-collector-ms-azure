<?php

class IPv4AddressAzureCollector extends CSVCollector
{
	protected static $sIPv4AddressCsvFilePath = null;
	protected static $aIPv4AddressHeaderColumns = null;
	protected static $aIPv4AddressJsonToCsv = null;
	protected $oMSCollectionPlan;

	/**
	 * @inheritdoc
	 */
	public function Init(): void
	{
		parent::Init();

		// Remove file where IPs are stored, if any
		$sCsvFilePath = IPv4AddressAzureCollector::GetCsvFilePath();
		if ($sCsvFilePath != '') {
			if (file_exists($sCsvFilePath)) {
				unlink($sCsvFilePath);
			}
		}

		// Get a copy of the collection plan
		$this->oMSCollectionPlan = MSCollectionPlan::GetPlan();
	}

	/**
	 * Find out where the CSV source file should be located
	 *
	 * @return string
	 * @throws Exception
	 */
	public static function GetCsvFilePath(): string
	{
		if (is_null(self::$sIPv4AddressCsvFilePath)) {
			// Path has not been computed yet. Do it now.
			self::$sIPv4AddressCsvFilePath = '';
			$aClassConfig = Utils::GetConfigurationValue(strtolower(get_called_class()));
			if (is_array($aClassConfig)) {
				if (array_key_exists('csv_file', $aClassConfig)) {
					self::$sIPv4AddressCsvFilePath = $aClassConfig['csv_file'];
				}
				if (self::$sIPv4AddressCsvFilePath === '') {
					Utils::Log(LOG_ERR, 'IPv4AddressAzureCollector: no CSV file has been setup !');

					return self::$sIPv4AddressCsvFilePath;
				}
				if (strpos(self::$sIPv4AddressCsvFilePath, '/') != 0) {
					self::$sIPv4AddressCsvFilePath = APPROOT.self::$sIPv4AddressCsvFilePath;
				}
			}
		}

		return self::$sIPv4AddressCsvFilePath;
	}

	/**
	 * Define the header of the CSV source file
	 *
	 * @return array
	 * @throws Exception
	 */
	public static function GetHeader(): array
	{
		if (is_null(self::$aIPv4AddressHeaderColumns)) {
			// Header has not been computed yet. Do it now.
			self::$aIPv4AddressHeaderColumns = [];
			$aClassConfig = Utils::GetConfigurationValue(strtolower(get_called_class()));
			if (is_array($aClassConfig)) {
				if (array_key_exists('fields', $aClassConfig)) {
					$aFields = $aClassConfig['fields'];
					if (!is_array($aFields)) {
						Utils::Log(LOG_ERR, 'IPv4AddressAzureCollector: fields section configuration is not correct. Please see documentation.');
					} else {
						foreach ($aFields as $key => $value) {
							self::$aIPv4AddressHeaderColumns[] = $key;
						}
					}
				}
			}
		}

		return self::$aIPv4AddressHeaderColumns;
	}

	/**
	 * Check if CSV ource file exists and create it if required
	 *
	 * @return bool
	 * @throws Exception
	 */
	public static function GetCsvSourceFile(): string
	{
		$sCsvFilePath = self::GetCsvFilePath();
		if ($sCsvFilePath == '') {
			return false;
		}

		if (!file_exists($sCsvFilePath)) {
			// Create file and initialize it
			$hOutputCSV = fopen($sCsvFilePath, 'w');
			if ($hOutputCSV === false) {
				Utils::Log(LOG_ERR, "Failed to open '$sCsvFilePath' for writing...");

				return false;
			} else {
				// Copy header parameters
				$aHeaderData = self::GetHeader();
				if (!empty($aHeaderData)) {
					try {
						// Write the CSV data
						fputcsv($hOutputCSV, $aHeaderData, ';');
					} catch (IgnoredRowException $e) {
						// Skip this line
						Utils::Log(LOG_DEBUG, "Ignoring the line $iLineIndex. Reason: ".$e->getMessage());
					}
				}
			}
			fclose ($hOutputCSV);
		}
		return $sCsvFilePath;
	}

	/**
	 * List the JSOn attributes used to build the CSV file
	 *
	 * @return array
	 * @throws Exception
	 */
	public static function GetJsonToCsv(): array
	{
		if (is_null(self::$aIPv4AddressJsonToCsv)) {
			// JsonToCsv has not been computed yet. Do it now.
			self::$aIPv4AddressJsonToCsv = [];
			$aClassConfig = Utils::GetConfigurationValue(strtolower(get_called_class()));
			if (is_array($aClassConfig)) {
				if (array_key_exists('fields', $aClassConfig)) {
					$aFields = $aClassConfig['fields'];
					if (!is_array($aFields)) {
						Utils::Log(LOG_ERR, 'IPv4AddressAzureCollector: fields section configuration is not correct. Please see documentation.');
					} else {
						foreach ($aFields as $key => $value) {
							self::$aIPv4AddressJsonToCsv[] = $value;
						}
					}
				}
			}
		}

		return self::$aIPv4AddressJsonToCsv;
	}

	/**
	 * Add a line to the CSV source file
	 * @param $aData
	 * @return bool
	 * @throws Exception
	 */
	public static function AddLineToCsvSourceFile($aData): bool
	{
		$sCsvFilePath = self::GetCsvSourceFile();
		if ($sCsvFilePath === false) {
			return false;
		}

		$aNewLine = [];
		$aJsonToCsvData = self::GetJsonToCsv();
		foreach ($aJsonToCsvData as $sColumn) {
			$aNewLine[] = (array_key_exists($sColumn, $aData)) ? $aData[$sColumn] : '';
		}
		try {
			$hHandle = file_put_contents($sCsvFilePath, implode(';', $aNewLine)."\n", FILE_APPEND);
		} catch (Exception $e) {
			Utils::Log(LOG_INFO, "IPv4AddressAzureCollector: Cannot add line to CSV file $sCsvFilePath");

			return false;
		}
		if ($hHandle === false) {
			Utils::Log(LOG_ERR, "IPv4AddressAzureCollector: Cannot add line to CSV file $sCsvFilePath");

			return false;
		}

		return true;
	}

	/**
	 * @inheritdoc
	 */
	public function CheckToLaunch($aOrchestratedCollectors): bool
	{
		if (parent::CheckToLaunch($aOrchestratedCollectors)) {
			if ($this->oMSCollectionPlan->IsTeemIpInstalled()) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @inheritdoc
	 */
	public function AttributeIsOptional($sAttCode): bool
	{
		if ($sAttCode == 'services_list') return true;

		return parent::AttributeIsOptional($sAttCode);
	}

	/**
	 * @inheritdoc
	 */
	protected function MustProcessBeforeSynchro(): bool
	{
		return true;
	}

	/**
	 * @inheritdoc
	 */
	protected function InitProcessBeforeSynchro(): void
	{
		// Create IPConfig mapping table
		$this->oIPv4AddressIPConfigMapping = new LookupTable('SELECT IPConfig', array('org_id_friendlyname'));
	}

	/**
	 * @inheritdoc
	 */
	protected function ProcessLineBeforeSynchro(&$aLineData, $iLineIndex)
	{
		if (!$this->oIPv4AddressIPConfigMapping->Lookup($aLineData, array('org_id'), 'ipconfig_id', $iLineIndex)) {
			throw new IgnoredRowException('Unknown IP Config');
		}
	}

	/**
	 * @inheritdoc
	 */
	public function Collect($iMaxChunkSize = 0): bool
	{
		Utils::Log(LOG_INFO, '----------------');

		return parent::Collect($iMaxChunkSize);
	}

}
