<?php
require_once(APPROOT.'collectors/msbase/src/MSJsonCollector.class.inc.php');

class AzurePublicIPAddressAzureCollector extends MSJsonCollector
{
	// Required parameters to build URL
	protected static $aURIParameters = [
		1 => self::URI_PARAM_SUBSCRIPTION,
		2 => self::URI_PARAM_RESOURCEGROUP,
	];

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
	protected function BuildUrl($aParameters): string
	{
		if (!array_key_exists(self::URI_PARAM_SUBSCRIPTION, $aParameters) || !array_key_exists(self::URI_PARAM_RESOURCEGROUP,
				$aParameters)) {
			return '';
		} else {
			$sUrl = $this->sResource.'subscriptions/'.$aParameters[self::URI_PARAM_SUBSCRIPTION];
			$sUrl .= '/resourceGroups/'.$aParameters[self::URI_PARAM_RESOURCEGROUP];
			$sUrl .= '/providers/Microsoft.Network/publicIPAddresses?api-version='.$this->sApiVersion;

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
			$aData['allocation_method'] = strtolower($this->aJson[$this->aJsonKey[$iJsonIdx]]['properties']['publicIPAllocationMethod']);
			if (array_key_exists('ipAddress', $this->aJson[$this->aJsonKey[$iJsonIdx]]['properties'])) {
				$aData['ip'] = strtolower($this->aJson[$this->aJsonKey[$iJsonIdx]]['properties']['ipAddress']);
			} else {
				$aData['ip'] = '';
			}
			$aData['provisioning_status'] = strtolower($this->aJson[$this->aJsonKey[$iJsonIdx]]['properties']['provisioningState']);
			$aData['version'] = strtolower($this->aJson[$this->aJsonKey[$iJsonIdx]]['properties']['publicIPAddressVersion']);
			if (array_key_exists('dnsSettings', $this->aJson[$this->aJsonKey[$iJsonIdx]]['properties'])) {
				$aData['fqdn'] = $this->aJson[$this->aJsonKey[$iJsonIdx]]['properties']['dnsSettings']['fqdn'];
				$aData['short_name'] = $this->aJson[$this->aJsonKey[$iJsonIdx]]['properties']['dnsSettings']['domainNameLabel'];
			} else {
				$aData['fqdn'] = '';
				$aData['short_name'] = '';
			}
			if (array_key_exists('ipConfiguration', $this->aJson[$this->aJsonKey[$iJsonIdx]]['properties'])) {
				$aData['azureipconfig_id'] = $this->aJson[$this->aJsonKey[$iJsonIdx]]['properties']['ipConfiguration']['id'];
			} else {
				$aData['azureipconfig_id'] = 0;
			}
		}

		return $aData;
	}

}

