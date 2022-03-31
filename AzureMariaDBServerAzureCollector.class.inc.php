<?php
require_once(APPROOT.'collectors/msbase/MSJsonCollector.class.inc.php');

class AzureMariaDBServerAzureCollector extends MSJsonCollector
{
	// Required parameters to build URL
	protected static $aURIParameters = [
		1 => self::URI_PARAM_SUBSCRIPTION,
		2 => self::URI_PARAM_RESOURCEGROUP,
	];

	/**
	 * @inheritdoc
	 */
	protected function BuildUrl($aParameters): string
	{
		if (!array_key_exists(self::URI_PARAM_SUBSCRIPTION, $aParameters) || !array_key_exists(self::URI_PARAM_RESOURCEGROUP,
				$aParameters)) {
			return '';
		} else {
			$sUrl = $this->sResource.'subscriptions/'.$aParameters[self::URI_PARAM_SUBSCRIPTION];
			$sUrl .= '/resourceGroups/'.$aParameters[self::URI_PARAM_RESOURCEGROUP];
			$sUrl .= '/providers/Microsoft.DBforMariaDB/servers?api-version='.$this->sApiVersion;

			return $sUrl;
		}
	}

	/**
	 * @inheritdoc
	 */
	protected function ReportObjects($aData, $sObjectL1, $sObjectL2, $sObjectL3): void
	{
		foreach ($aData['value'] as $aObject) {
			$this->oMSCollectionPlan->AddMSObjectsToConsider($sObjectL1, $sObjectL2, $aObject['name']);
		}
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
	protected function DoLookup($aLookupKey, $sDestField): array
	{
		$sResult = false;
		$sData = '';
		switch ($sDestField) {
			case 'azureresourcegroup_id':
				if (array_key_exists('primary_key', $aLookupKey) && ($aLookupKey['primary_key'] != '')) {
					$sData = strstr($aLookupKey['primary_key'], 'resourceGroups');
					if ($sData !== false) {
						$aData = explode('/', $sData);
						$sData = $aData[1];
						$sResult = true;
					}
				}
				break;

			case 'azuresubscription_id':
				if (array_key_exists('primary_key', $aLookupKey) && ($aLookupKey['primary_key'] != '')) {
					$sData = strstr($aLookupKey['primary_key'], 'subscriptions');
					if ($sData !== false) {
						$aData = explode('/', $sData);
						$sData = $aData[1];
						$sResult = true;
					}
				}
				break;

			case 'type':
				if (array_key_exists('type', $aLookupKey) && ($aLookupKey['type'] != '')) {
					$sData = $aLookupKey['type'];
					if ($sData == 'Microsoft.DBforMariaDB/servers') {
						$sData = 'mariadb';
						$sResult = true;
					}
				}
				break;

			default:
				break;
		}

		return [$sResult, $sData];
	}

	/**
	 * @inheritdoc
	 */
	protected function ProcessLineBeforeSynchro(&$aLineData, $iLineIndex)
	{
		// Process each line of the CSV
		if (!$this->Lookup($aLineData, array('primary_key'), 'azureresourcegroup_id', $iLineIndex, true, false)) {
			throw new IgnoredRowException('Unknown code');
		}
		if (!$this->Lookup($aLineData, array('primary_key'), 'azuresubscription_id', $iLineIndex, true, false)) {
			throw new IgnoredRowException('Unknown code');
		}
		if (!$this->Lookup($aLineData, array('type'), 'type', $iLineIndex, true, false)) {
			throw new IgnoredRowException('Unknown code');
		}
	}

	/**
	 * @inheritdoc
	 */
	public function Fetch()
	{
		$aData = parent::Fetch();
		if ($aData !== false) {
			// Then process specific data
			$iJsonIdx = $this->iIdx - 1; // Increment is done at the end of parent::Fetch()
			$aData['capacity'] = $this->aJson[$this->aJsonKey[$iJsonIdx]]['sku']['capacity'];
			$aData['fqdn'] = $this->aJson[$this->aJsonKey[$iJsonIdx]]['properties']['fullyQualifiedDomainName'];
			$aData['provisioning_status'] = strtolower($this->aJson[$this->aJsonKey[$iJsonIdx]]['properties']['userVisibleState']);
			$aData['storage'] = str_replace(',', '.',
				$this->aJson[$this->aJsonKey[$iJsonIdx]]['properties']['storageProfile']['storageMB'] / 1000);
			$aData['tier'] = $this->aJson[$this->aJsonKey[$iJsonIdx]]['sku']['tier'];
			$aData['version'] = $this->aJson[$this->aJsonKey[$iJsonIdx]]['properties']['version'];
		}

		return $aData;
	}

}


