<?php

class AzureLocationJsonCollector extends AzureJsonCollector {
	private $aLookupFields = ['code'];

	/**
	 * @inheritdoc
	 */
	protected function GetUrl($iSubscription, $sResourceGroupName): string {
		return $this->sResource.'/subscriptions/'.$iSubscription.'/providers/Microsoft.DocumentDB/locations?api-version='.$this->sApiVersion;
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
			case 'code':
				if (array_key_exists('primary_key', $aLookupKey) && ($aLookupKey['primary_key'] != '')) {
					$sData = strstr($aLookupKey['primary_key'], 'locations');
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
		if (!$this->Lookup($aLineData, array('primary_key'), 'code', $iLineIndex, true, false)) {
			throw new IgnoredRowException('Unknown code');
		}
	}
}

