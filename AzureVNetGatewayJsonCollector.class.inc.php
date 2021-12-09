<?php

class AzureVNetGatewayJsonCollector extends AzureJsonCollector {
	/**
	 * @inheritdoc
	 */
	public static function NeedsResourceGroupsForCollector(): bool {
		return true;
	}

	/**
	 * @inheritdoc
	 */
	protected function GetUrl($iSubscription, $sResourceGroupName): string {
		return $this->sResource.'/subscriptions/'.$iSubscription.'/resourceGroups/'.$sResourceGroupName.'/providers/Microsoft.Network/virtualNetworkGateways?api-version='.$this->sApiVersion;
	}

	/**
	 * @inheritdoc
	 */
	protected function MustProcessBeforeSynchro(): bool {
		return true;
	}

	/**
	 * @inheritdoc
	 */
	protected function DoLookup($aLookupKey, $sDestField): array {
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
	protected function ProcessLineBeforeSynchro(&$aLineData, $iLineIndex) {
		// Process each line of the CSV
		if (!$this->Lookup($aLineData, array('primary_key'), 'azureresourcegroup_id', $iLineIndex, true, false)) {
			throw new IgnoredRowException('Unknown code');
		}
		if (!$this->Lookup($aLineData, array('primary_key'), 'azuresubscription_id', $iLineIndex, true, false)) {
			throw new IgnoredRowException('Unknown code');
		}
	}

}

