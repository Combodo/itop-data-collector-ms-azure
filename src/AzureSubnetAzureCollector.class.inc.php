<?php
require_once(APPROOT.'collectors/msbase/src/MSJsonCollector.class.inc.php');

class AzureSubnetAzureCollector extends MSJsonCollector
{
    private $sIPv4Pattern = "((\d|[1-9]\d|1\d\d|2[0-4]\d|25[0-5])\.(\d|[1-9]\d|1\d\d|2[0-4]\d|25[0-5])\.(\d|[1-9]\d|1\d\d|2[0-4]\d|25[0-5])\.(\d|[1-9]\d|1\d\d|2[0-4]\d|25[0-5]))";

    // Required parameters to build URL
	protected static $aURIParameters = [
		1 => self::URI_PARAM_SUBSCRIPTION,
		2 => self::URI_PARAM_RESOURCEGROUP,
		3 => self::URI_PARAM_VNET,
	];

	/**
	 * @inheritdoc
	 */
	public function AttributeIsOptional($sAttCode): bool
	{
		if ($sAttCode == 'services_list') return true;

        if ($this->oMSCollectionPlan->IsTeemIpInstalled()) {
            if ($sAttCode == 'ip') return true;
            if ($sAttCode == 'mask') return true;
            if ($sAttCode == 'ipsubnet_id') return false;
        } else {
            if ($sAttCode == 'ip') return false;
            if ($sAttCode == 'mask') return false;
            if ($sAttCode == 'ipsubnet_id') return true;
        }

		return parent::AttributeIsOptional($sAttCode);
	}

	/**
	 * @inheritdoc
	 */
	protected function BuildUrl($aParameters): string
	{
		if (!array_key_exists(self::URI_PARAM_SUBSCRIPTION, $aParameters) || !array_key_exists(self::URI_PARAM_RESOURCEGROUP,
				$aParameters) || !array_key_exists(self::URI_PARAM_VNET, $aParameters)) {
			return '';
		} else {
			$sUrl = $this->sResource.'subscriptions/'.$aParameters[self::URI_PARAM_SUBSCRIPTION];
			$sUrl .= '/resourceGroups/'.$aParameters[self::URI_PARAM_RESOURCEGROUP];
			$sUrl .= '/providers/Microsoft.Network/virtualNetworks/'.$aParameters[self::URI_PARAM_VNET];
			$sUrl .= '/subnets?api-version='.$this->sApiVersion;

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
    protected function InitProcessBeforeSynchro(): void
    {
        // Create subnet mapping table
        if ($this->oMSCollectionPlan->IsTeemIpInstalled()) {
            $this->oSubnetIPv4Mapping = new LookupTable('SELECT IPv4Subnet', array('org_id_friendlyname', 'ip'));
            $this->oSubnetIPv6Mapping = new LookupTable('SELECT IPv6Subnet', array('org_id_friendlyname', 'ip'));
     }

    }

    public function Prepare(): bool
	{
        // Create mask mapping table
		$this->oIPv4SubnetMaskMapping = new MappingTable('ipv4_subnet_mask_mapping');

		return parent::Prepare();
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

			case 'azurevnet_id':
				if (array_key_exists('primary_key', $aLookupKey) && ($aLookupKey['primary_key'] != '')) {
					$sData = strstr($aLookupKey['primary_key'], 'virtualNetworks');
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
		if (!$this->Lookup($aLineData, array('primary_key'), 'azurevnet_id', $iLineIndex, true, false)) {
			throw new IgnoredRowException('Unknown code');
		}
        if ($this->oMSCollectionPlan->IsTeemIpInstalled()) {
            if ($iLineIndex == 0) {
                // Make sure both lookup tables are correctly initialized
                $this->oSubnetIPv4Mapping->Lookup($aLineData, array('org_id', 'ipsubnet_id'), 'ipsubnet_id', 0);
                $this->oSubnetIPv6Mapping->Lookup($aLineData, array('org_id', 'ipsubnet_id'), 'ipsubnet_id', 0);
            } elseif (!$this->oSubnetIPv4Mapping->Lookup($aLineData, array('org_id', 'ipsubnet_id'), 'ipsubnet_id', $iLineIndex) &&
                      !$this->oSubnetIPv6Mapping->Lookup($aLineData, array('org_id', 'ipsubnet_id'), 'ipsubnet_id', $iLineIndex)) {
                throw new IgnoredRowException('Unknown Subnet');
            }
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
			//$aData['provisioning_status'] = strtolower($this->aJson[$this->aJsonKey[$iJsonIdx]]['properties']['provisioningState']);
			if (array_key_exists('delegations', $this->aJson[$this->aJsonKey[$iJsonIdx]]['properties'])) {
				$aDelegations = $this->aJson[$this->aJsonKey[$iJsonIdx]]['properties']['delegations'];

			}
			$sIP = strstr($this->aJson[$this->aJsonKey[$iJsonIdx]]['properties']['addressPrefix'], '/', true);
			if ($this->oMSCollectionPlan->IsTeemIpInstalled()) {
				$aData['ipsubnet_id'] = $sIP;
				if (array_key_exists('ip', $aData)) {
					unset($aData['ip']);
				}
			} else {
				$aData['ip'] = $sIP;
				if (array_key_exists('ipsubnet_id', $aData)) {
					unset($aData['ipsubnet_id']);
				}
			}
            $sMask = strstr($this->aJson[$this->aJsonKey[$iJsonIdx]]['properties']['addressPrefix'], '/');
            if (preg_match($this->sIPv4Pattern, $sIP)) {
                // Map /n mask format into x.y.z.t in case of IPv4 subnet
                $aData['mask'] = $this->oIPv4SubnetMaskMapping->MapValue(trim($sMask, '/'), '');
            } else {
                $aData['mask'] = $sMask;
            }
		}

		return $aData;
	}
}

