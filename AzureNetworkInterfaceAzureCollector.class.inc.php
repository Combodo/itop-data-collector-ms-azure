<?php
require_once(APPROOT.'collectors/msbase/MSJsonCollector.class.inc.php');

class AzureNetworkInterfaceAzureCollector extends MSJsonCollector
{
	// Required parameters to build URL
	protected static $aURIParameters = [
		1 => self::URI_PARAM_SUBSCRIPTION,
	];

	/**
	 * @inheritdoc
	 */
	protected function BuildUrl($aParameters): string
	{
		if (!array_key_exists(self::URI_PARAM_SUBSCRIPTION, $aParameters)) {
			return '';
		} else {
			$sUrl = $this->sResource.'subscriptions/'.$aParameters[self::URI_PARAM_SUBSCRIPTION];
			$sUrl .= '/providers/Microsoft.Network/networkInterfaces?api-version='.$this->sApiVersion;

			return $sUrl;
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

			case 'azurevnets_list':
				if (array_key_exists('id', $aLookupKey) && ($aLookupKey['id'] != '')) {
					$sData = strstr($aLookupKey['id'], 'virtualNetworks');
					if ($sData !== false) {
						$aData = explode('/', $sData);
						$sData = $aData[1];
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
			$aData['azurevirtualmachine_id'] = $this->aJson[$this->aJsonKey[$iJsonIdx]]['properties']['virtualMachine']['id'];
			$sVnetsList = '';
			$bFirstVnet = true;
			$aIpConfigurations = $this->aJson[$this->aJsonKey[$iJsonIdx]]['properties']['ipConfigurations'];
			foreach ($aIpConfigurations as $aIpConfiguration) {
				list($sResult, $sData) = $this->DoLookup($aIpConfiguration['properties']['subnet'], 'azurevnets_list');
				if ($sResult) {
					if ($bFirstVnet) {
						$bFirstVnet = false;
					} else {
						$sVnetsList .= '|';
					}
					$sVnetsList .= 'azurevnet_id->name:'.$sData;
				}
			}
			$aData['azurevnets_list'] = $sVnetsList;
			$aData['provisioning_status'] = strtolower($this->aJson[$this->aJsonKey[$iJsonIdx]]['properties']['provisioningState']);
		}

		return $aData;
	}

}
