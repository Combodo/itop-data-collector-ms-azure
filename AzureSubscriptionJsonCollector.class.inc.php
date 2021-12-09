<?php

class AzureSubscriptionJsonCollector extends AzureJsonCollector {
	private $oStatusMapping;

	/**
	 * @inheritdoc
	 */
	protected function GetUrl($iSubscription, $sResourceGroupName): string {
		return $this->sResource.'/subscriptions?api-version='.$this->sApiVersion;
	}

	/**
	 * @inheritdoc
	 */
	protected function RetrieveDataFromAzure(): array {
		$sUrl = $this->GetUrl('', '');
		$aEmpty = [];
		$aOptionnalHeaders = [
			'Content-type: application/json',
			'Authorization: Bearer '.$this->sBearerToken,
		];
		$sOptionnalHeaders = implode("\n", $aOptionnalHeaders);
		$aCurlOptions = array(CURLOPT_POSTFIELDS => "");
		try {
			$sResponse = utils::DoPostRequest($sUrl, $aEmpty, $sOptionnalHeaders, $aEmpty, $aCurlOptions);
			$aResults = json_decode($sResponse, true);
			if (isset($aResults['error'])) {
				Utils::Log(LOG_ERR,
					'Data collection for '.$this->sAzureClass.' failed: Error code: '.$aResults['error']['code'].' Message: '.$aResults['error']['message']);

				return [false, $aEmpty];
			} else {
				Utils::Log(LOG_DEBUG,
					'Data for class '.$this->sAzureClass.' have been retrieved from Azure. Count '.$aResults['count']['type'].' = '.$aResults['count']['value']);
			}
		} catch (Exception $e) {
			Utils::Log(LOG_WARNING, "Query failed: ".$e->getMessage());

			return [false, $aEmpty];
		}

		return [true, $aResults];
	}

	/**
	 * @inheritdoc
	 */
	public function Prepare(): bool {
		// Create MappingTable
		$this->oStatusMapping = new MappingTable('subscription_status_mapping');

		return parent::Prepare();
	}

	/**
	 * @inheritdoc
	 */
	public function Fetch() {
		$aData = parent::Fetch();
		if ($aData !== false) {
			// Then process each collected status
			$aData['status'] = $this->oStatusMapping->MapValue($aData['status'], 'implementation');
		}

		return $aData;
	}

}
