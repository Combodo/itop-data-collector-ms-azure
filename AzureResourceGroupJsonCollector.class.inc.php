<?php

class AzureResourceGroupJsonCollector extends AzureJsonCollector {

	/**
	 * @return string     * @inheritdoc
	 */
	protected function GetUrl($iSubscription) {
		$sUrl = $this->sResource.'/subscriptions/'.$iSubscription.'/resourcegroups?api-version='.$this->sApiVersion;

		return $sUrl;
	}

}

