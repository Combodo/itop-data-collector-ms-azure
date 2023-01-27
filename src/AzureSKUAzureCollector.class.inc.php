<?php
require_once(APPROOT.'collectors/msbase/src/MSJsonCollector.class.inc.php');

class AzureSKUAzureCollector extends MSJsonCollector
{
	// What location to use in the filter
	const LOCATION_FILTER = 'westeurope';
	protected $sLocationFilter;

	// Required parameters to build URL
	protected static $aURIParameters = [
		1 => self::URI_PARAM_SUBSCRIPTION,
	];

	/**
	 * @inheritdoc
	 */
	public function Init(): void
	{
		parent::Init();

		$this->aParamsSourceJson = Utils::GetConfigurationValue(strtolower(get_class($this)), array());
		$this->sLocationFilter = (isset($this->aParamsSourceJson['location_filter'])) ? $this->aParamsSourceJson['location_filter'] : self::LOCATION_FILTER;
	}

	/**
	 * @inheritdoc
	 */
	public function AttributeIsOptional($sAttCode): bool
	{
		if ($sAttCode == 'services_list') {
			return true;
		}

		return parent::AttributeIsOptional($sAttCode);
	}

	/**
	 * @inheritdoc
	 */
	protected function BuildUrl($aParameters): string
	{
		if (!array_key_exists(self::URI_PARAM_SUBSCRIPTION, $aParameters)) {
			return '';
		} else {
			$sUrl = $this->sResource.'subscriptions/'.$aParameters[self::URI_PARAM_SUBSCRIPTION];
			$sUrl .= '/providers/Microsoft.Compute/skus?api-version='.$this->sApiVersion.'&$filter=location%20eq%20\''.$this->sLocationFilter.'\'';

			return $sUrl;
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
			$aSKU = $this->aJson[$this->aJsonKey[$iJsonIdx]];
			// Complete primary_key
			if (array_key_exists('size', $aSKU)) {
				$aData['primary_key'] .= '-'.$aSKU['size'];
			}
			$sResourceType = $aSKU['resourceType'];
			if (array_key_exists('capabilities', $aSKU)) {
				$aCapabilities = $aSKU['capabilities'];
				foreach ($aCapabilities as $aCapability) {
					$sCapability = strtolower($aCapability['name']);
					if ($sResourceType == 'disks') {
						switch ($sCapability) {
							case 'maxbandwidthmbps':
								$aData['maximaxbandwidthmbps'] = $aCapability['value'];

							case 'maxiops':
								$aData['maxiops'] = $aCapability['value'];

							case 'maxsizegib':
								$aData['maxsizegib'] = $aCapability['value'];

							default:
								break;
						}
					} elseif ($sResourceType == 'virtualMachines') {
						switch ($sCapability) {
							case 'maxnetworkinterfaces':
								$aData['maxnetworkinterfaces'] = $aCapability['value'];

							case 'maxresourcevolumemb':
								$aData['maxresourcevolumemb'] = $aCapability['value'];

							case 'memorygb':
								$aData['memorygb'] = $aCapability['value'];

							case 'osvhdsizemb':
								$aData['osvhdsizemb'] = $aCapability['value'];

							case 'vcpus':
								$aData['vcpus'] = $aCapability['value'];

							case 'vcpuspercore':
								$aData['vcpuspercore'] = $aCapability['value'];

							default:
								break;
						}
					}
				}
			}
		}

		return $aData;
	}

}

