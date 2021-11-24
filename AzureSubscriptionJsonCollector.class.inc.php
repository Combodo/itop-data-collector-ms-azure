<?php

class AzureSubscriptionJsonCollector extends AzureJsonCollector {
	/**
	 * @inheritdoc
	 */
	protected function GetUrl($iSubscription) {
		$sUrl = $this->sResource.'/subscriptions?api-version='.$this->sApiVersion;

		return $sUrl;
	}

	/**
	 * @inheritdoc
	 */
	protected function RetrieveDataFromAzure() {
		$sUrl = $this->GetUrl(0);
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
					"Data collection for ".$this->sAzureClass." failed: 
					                Error code: ".$aResults['error']['code']."
					                Message: ".$aResults['error']['message']);

				return false;
			} else {
				Utils::Log(LOG_DEBUG,
					'Data for class '.$this->sAzureClass.' have been retrieved from Azure. Count '.$aResults['count']['type'].' = '.$aResults['count']['value']);
			}
		} catch (Exception $e) {
			Utils::Log(LOG_WARNING, "Query failed: ".$e->getMessage());

			return false;
		}

		$hJSON = file_put_contents($this->sJsonFile, $sResponse);
		if ($hJSON === false) {
			Utils::Log(LOG_ERR, "Failed to write retrieved data in '$this->sJsonFile' !");

			return false;
		}

		return true;
	}

}
